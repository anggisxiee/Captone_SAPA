<?php

namespace Tests\Feature;

use App\Exceptions\GagalKirimPesanException;
use App\Exceptions\GatewayException;
use App\Exceptions\SudahTersambungException;
use App\Jobs\KirimPesanKeluar;
use App\Models\AntreanPesan;
use App\Models\NomorWhatsapp;
use App\Models\User;
use App\Services\WhatsApp\BaileysWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Driver baileys: Laravel -> wa-gateway lewat HTTP (gateway dipalsukan dengan Http::fake). */
class BaileysGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '6281200000001';
    private const TOKEN = 'token-uji-baileys';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'whatsapp.driver' => 'baileys',
            'whatsapp.webhook_token' => self::TOKEN,
            'whatsapp.baileys.url' => 'http://gateway.test:3001',
        ]);
        app()->forgetInstance(WhatsAppGateway::class);
    }

    private function nomor(array $o = []): NomorWhatsapp
    {
        return NomorWhatsapp::create($o + ['label' => 'Admisi', 'nomor' => self::NOMOR]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
    }

    public function test_driver_baileys_dipilih_dari_konfigurasi(): void
    {
        $this->assertInstanceOf(BaileysWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    public function test_driver_tidak_dikenal_ditolak_dengan_pesan_jelas(): void
    {
        config(['whatsapp.driver' => 'tidak-ada']);
        app()->forgetInstance(WhatsAppGateway::class);

        $this->expectException(\InvalidArgumentException::class);
        app(WhatsAppGateway::class);
    }

    public function test_minta_qr_memanggil_gateway_dengan_token_dan_nomor(): void
    {
        Http::fake(['gateway.test:3001/sesi' => Http::response(['status' => 'qr', 'qr' => '2@abc,def,ghi'])]);

        $qr = app(WhatsAppGateway::class)->mintaQr($this->nomor());

        $this->assertSame('2@abc,def,ghi', $qr);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'http://gateway.test:3001/sesi'
            && $r->header('X-Gateway-Token') === [self::TOKEN]
            && $r['nomor'] === self::NOMOR);
    }

    public function test_minta_qr_saat_gateway_sudah_tersambung(): void
    {
        Http::fake(['*' => Http::response(['status' => 'tersambung'])]);

        $this->expectException(SudahTersambungException::class);
        app(WhatsAppGateway::class)->mintaQr($this->nomor());
    }

    public function test_minta_qr_menerjemahkan_galat_gateway_dan_jaringan(): void
    {
        $gateway = app(WhatsAppGateway::class);
        $nomor = $this->nomor();

        Http::fake(['*' => Http::response(['pesan' => 'Gateway tidak menerima QR dari WhatsApp tepat waktu.'], 504)]);
        try {
            $gateway->mintaQr($nomor);
            $this->fail('Seharusnya melempar GatewayException');
        } catch (GatewayException $e) {
            $this->assertStringContainsString('tepat waktu', $e->getMessage());
        }

        Http::fake(fn () => throw new ConnectionException('refused'));
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');
        $gateway->mintaQr($nomor);
    }

    public function test_minta_qr_tanpa_isi_qr_dianggap_galat(): void
    {
        Http::fake(['*' => Http::response(['status' => 'qr'])]);

        $this->expectException(GatewayException::class);
        app(WhatsAppGateway::class)->mintaQr($this->nomor());
    }

    public function test_kirim_mengembalikan_id_pesan_dari_gateway(): void
    {
        Http::fake(['*' => Http::response(['id_pesan' => 'WAMID-77'])]);

        $id = app(WhatsAppGateway::class)->kirim($this->nomor(), '6285711110001', 'Halo!');

        $this->assertSame('WAMID-77', $id);
        Http::assertSent(fn (Request $r) => $r->url() === 'http://gateway.test:3001/sesi/' . self::NOMOR . '/pesan'
            && $r['telepon'] === '6285711110001'
            && $r['isi'] === 'Halo!');
    }

    public function test_kirim_gagal_memakai_pesan_gateway_atau_jaringan(): void
    {
        $gateway = app(WhatsAppGateway::class);
        $nomor = $this->nomor();

        Http::fake(['*' => Http::response(['pesan' => 'Nomor tujuan tidak terdaftar di WhatsApp.'], 422)]);
        try {
            $gateway->kirim($nomor, '6285711110001', 'Halo');
            $this->fail('Seharusnya gagal');
        } catch (GagalKirimPesanException $e) {
            $this->assertSame('Nomor tujuan tidak terdaftar di WhatsApp.', $e->getMessage());
        }

        Http::fake(fn () => throw new ConnectionException('refused'));
        $this->expectException(GagalKirimPesanException::class);
        $gateway->kirim($nomor, '6285711110001', 'Halo');
    }

    public function test_putuskan_memanggil_delete_dan_tidak_melempar_saat_gateway_mati(): void
    {
        $gateway = app(WhatsAppGateway::class);
        $nomor = $this->nomor();

        Http::fake(['*' => Http::response(['ok' => true])]);
        $gateway->putuskan($nomor);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/sesi/' . self::NOMOR));

        Http::fake(fn () => throw new ConnectionException('refused'));
        $gateway->putuskan($nomor);
        Http::fake(['*' => Http::response([], 500)]);
        $gateway->putuskan($nomor);
        $this->addToAssertionCount(1); // tidak ada pengecualian
    }

    // --- Alur lewat controller dan job -------------------------------------------------

    public function test_tautkan_menyimpan_qr_dari_gateway_dan_menampilkannya(): void
    {
        Http::fake(['*' => Http::response(['status' => 'qr', 'qr' => '2@kode-qr-nyata'])]);
        $nomor = $this->nomor();

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertOk()
            ->assertJson(['status_sesi' => 'Menunggu QR', 'kedaluwarsa' => false]);

        $this->assertSame('2@kode-qr-nyata', $nomor->fresh()->qr_token);
    }

    public function test_tautkan_saat_gateway_mati_memberi_502_dan_status_tidak_berubah(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $nomor = $this->nomor();

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertStatus(502)
            ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'tidak dapat dihubungi'));

        $nomor->refresh();
        $this->assertSame('Belum tertaut', $nomor->status_sesi);
        $this->assertNull($nomor->qr_token);
    }

    public function test_tautkan_saat_gateway_sudah_tersambung_menyelaraskan_status(): void
    {
        Http::fake(['*' => Http::response(['status' => 'tersambung'])]);
        $nomor = $this->nomor();

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertOk()
            ->assertJson(['status_sesi' => 'Tersambung']);

        $this->assertSame('Tersambung', $nomor->fresh()->status_sesi);
    }

    public function test_tombol_simulasi_pindai_tidak_tersedia_pada_driver_baileys(): void
    {
        $nomor = $this->nomor(['status_sesi' => 'Menunggu QR', 'qr_token' => 'x', 'qr_kedaluwarsa_pada' => now()->addMinute()]);

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.simulasi-pindai', $nomor))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('nomor-whatsapp'))
            ->assertOk()
            ->assertDontSee('Simulasikan pindai');
    }

    public function test_putuskan_lewat_ui_memanggil_gateway_dan_menandai_terputus(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $nomor = $this->nomor(['status_sesi' => 'Tersambung']);

        $this->actingAs($this->admin())
            ->post(route('nomor-whatsapp.putuskan', $nomor))
            ->assertRedirect(route('nomor-whatsapp'));

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE');
        $this->assertSame('Terputus', $nomor->fresh()->status_sesi);
    }

    public function test_job_mengirim_pesan_lewat_gateway(): void
    {
        Http::fake(['*' => Http::response(['id_pesan' => 'WAMID-1'])]);
        $nomor = $this->nomor(['status_sesi' => 'Tersambung']);
        $antrean = AntreanPesan::create([
            'nomor_whatsapp_id' => $nomor->id, 'pengirim' => 'Admisi', 'penerima' => '6285711110001', 'isi_pesan' => 'Halo', 'keputusan' => 'Kirim',
            'maks_percobaan' => 3, 'jadwal_kirim' => now(),
        ]);

        KirimPesanKeluar::dispatchSync($antrean->id);

        $this->assertSame('Terkirim', $antrean->fresh()->keputusan);
        Http::assertSentCount(1);
    }

    public function test_job_mencoba_ulang_lalu_menyerah_bila_gateway_menolak(): void
    {
        Http::fake(['*' => Http::response(['pesan' => 'Nomor WhatsApp tidak tersambung di gateway.'], 409)]);
        $nomor = $this->nomor(['status_sesi' => 'Tersambung']);
        $antrean = AntreanPesan::create([
            'nomor_whatsapp_id' => $nomor->id, 'pengirim' => 'Admisi', 'penerima' => '6285711110001', 'isi_pesan' => 'Halo', 'keputusan' => 'Kirim',
            'maks_percobaan' => 3, 'jadwal_kirim' => now(),
        ]);

        KirimPesanKeluar::dispatchSync($antrean->id);

        $antrean->refresh();
        $this->assertSame('Gagal', $antrean->keputusan);
        $this->assertSame(3, $antrean->percobaan);
        $this->assertStringContainsString('tidak tersambung di gateway', $antrean->alasan_penahanan);
        $this->assertDatabaseHas('insiden_risiko', ['jenis_insiden' => 'Pesan gagal terkirim']);
    }

    // --- Webhook sesi: event QR --------------------------------------------------------

    private function webhookSesi(array $data)
    {
        config(['whatsapp.webhook_token' => self::TOKEN]);

        return $this->postJson('/api/webhook/whatsapp/sesi', $data + ['nomor' => self::NOMOR], ['X-Gateway-Token' => self::TOKEN]);
    }

    public function test_webhook_qr_memperbarui_kode_saat_menunggu_qr(): void
    {
        $nomor = $this->nomor(['status_sesi' => 'Menunggu QR', 'qr_token' => 'lama', 'qr_kedaluwarsa_pada' => now()->addSeconds(40)]);

        $this->webhookSesi(['status' => 'qr', 'qr' => '2@kode-baru'])
            ->assertOk()
            ->assertJson(['diabaikan' => false]);

        $this->assertSame('2@kode-baru', $nomor->fresh()->qr_token);
    }

    public function test_webhook_qr_diabaikan_bila_nomor_tidak_menunggu_qr_atau_sudah_kedaluwarsa(): void
    {
        $tersambung = $this->nomor(['status_sesi' => 'Tersambung']);
        $this->webhookSesi(['status' => 'qr', 'qr' => 'x'])->assertOk()->assertJson(['diabaikan' => true]);
        $this->assertNull($tersambung->fresh()->qr_token);

        $tersambung->update(['status_sesi' => 'Menunggu QR', 'qr_token' => 'lama', 'qr_kedaluwarsa_pada' => now()->subSecond()]);
        $this->webhookSesi(['status' => 'qr', 'qr' => 'x'])->assertOk()->assertJson(['diabaikan' => true]);
        $this->assertSame('lama', $tersambung->fresh()->qr_token);
    }

    public function test_webhook_qr_tanpa_isi_qr_ditolak(): void
    {
        $this->nomor(['status_sesi' => 'Menunggu QR']);

        $this->webhookSesi(['status' => 'qr'])->assertUnprocessable();
    }

    public function test_webhook_gagal_dari_gateway_karena_nomor_dipindai_berbeda_masuk_pusat_risiko(): void
    {
        $nomor = $this->nomor(['status_sesi' => 'Menunggu QR']);

        $this->webhookSesi([
            'status' => 'gagal',
            'alasan' => 'Nomor yang dipindai (6289999999999) berbeda dengan nomor terdaftar (6281200000001).',
        ])->assertOk();

        $this->assertSame('Gagal tertaut', $nomor->fresh()->status_sesi);
        $this->assertDatabaseHas('insiden_risiko', ['nomor_whatsapp_id' => $nomor->id, 'jenis_insiden' => 'Gagal tertaut']);
    }
}
