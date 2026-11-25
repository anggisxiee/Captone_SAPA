<?php

namespace Tests\Feature;

use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TC02 - Menerima pesan WhatsApp lewat webhook gateway (F02). */
class WebhookWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-uji';

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.webhook_token' => self::TOKEN]);
    }

    private function nomor(string $sesi = 'Tersambung', string $status = 'Aktif'): NomorWhatsapp
    {
        return NomorWhatsapp::create([
            'label' => 'Admisi',
            'nomor' => '081200000001',
            'status' => $status,
            'status_sesi' => $sesi,
        ]);
    }

    private function kirimPesan(array $data = [], string $token = self::TOKEN)
    {
        return $this->postJson('/api/webhook/whatsapp/pesan', array_merge([
            'nomor' => '6281200000001',
            'dari' => '0857-1111-0001',
            'nama' => 'Bu Ratna',
            'isi' => 'Halo, ada kelas 6 SD?',
            'id_pesan' => 'wamid-1',
        ], $data), ['X-Gateway-Token' => $token]);
    }

    public function test_tanpa_token_atau_token_salah_ditolak(): void
    {
        $this->nomor();

        $this->kirimPesan([], 'salah')->assertUnauthorized();
        $this->postJson('/api/webhook/whatsapp/pesan', [])->assertUnauthorized();
        $this->assertDatabaseCount('pesan_whatsapp', 0);
    }

    public function test_token_kosong_di_konfigurasi_menolak_semua(): void
    {
        config(['whatsapp.webhook_token' => '']);

        $this->kirimPesan([], '')->assertUnauthorized();
    }

    public function test_pesan_membuat_kontak_percakapan_dan_pesan(): void
    {
        $nomor = $this->nomor();

        $this->kirimPesan()->assertCreated();

        // Nomor pengirim diseragamkan ke format 62.
        $this->assertDatabaseHas('kontak', ['nomor_telepon' => '6285711110001', 'nama' => 'Bu Ratna']);

        $percakapan = Percakapan::firstOrFail();
        $this->assertTrue($percakapan->belum_dibalas);
        $this->assertSame($nomor->id, $percakapan->nomor_whatsapp_id);

        $this->assertDatabaseHas('pesan_whatsapp', [
            'percakapan_id' => $percakapan->id,
            'arah' => 'masuk',
            'jenis_pengirim' => 'pelanggan',
            'status' => 'diterima',
            'isi' => 'Halo, ada kelas 6 SD?',
        ]);
        $this->assertNotNull($nomor->fresh()->terakhir_aktif_pada);
    }

    public function test_pesan_kedua_masuk_ke_percakapan_yang_sama(): void
    {
        $this->nomor();

        $this->kirimPesan();
        $this->kirimPesan(['id_pesan' => 'wamid-2', 'isi' => 'Dan biayanya?'])->assertCreated();

        $this->assertSame(1, Percakapan::count());
        $this->assertSame(2, PesanWhatsapp::count());
    }

    public function test_pesan_dengan_id_sama_tidak_tersimpan_ganda(): void
    {
        $this->nomor();

        $this->kirimPesan()->assertCreated();
        $this->kirimPesan()->assertOk()->assertJson(['duplikat' => true]);

        $this->assertSame(1, PesanWhatsapp::count());
    }

    public function test_percakapan_selesai_dibuka_kembali(): void
    {
        $this->nomor();
        $this->kirimPesan();
        Percakapan::query()->update(['status' => 'Selesai', 'belum_dibaca' => 0]);

        $this->kirimPesan(['id_pesan' => 'wamid-2', 'isi' => 'Maaf, satu lagi.']);

        $p = Percakapan::firstOrFail();
        $this->assertSame('Baru', $p->status);
        $this->assertTrue($p->belum_dibalas);
    }

    public function test_percakapan_yang_ditangani_agen_tetap_dan_ditandai_belum_dibalas(): void
    {
        $this->nomor();
        $this->kirimPesan();
        Percakapan::query()->update(['status' => 'Ditangani Agen', 'belum_dibaca' => 0]);

        $this->kirimPesan(['id_pesan' => 'wamid-2']);

        $p = Percakapan::firstOrFail();
        $this->assertSame('Ditangani Agen', $p->status);
        $this->assertTrue($p->belum_dibalas);
    }

    public function test_nomor_terputus_atau_nonaktif_tidak_menerima_pesan(): void
    {
        $nomor = $this->nomor('Terputus');
        $this->kirimPesan()->assertStatus(409);

        $nomor->update(['status_sesi' => 'Tersambung', 'status' => 'Nonaktif']);
        $this->kirimPesan()->assertStatus(409);

        $this->assertDatabaseCount('pesan_whatsapp', 0);
    }

    public function test_nomor_tidak_dikenal_ditolak(): void
    {
        $this->kirimPesan(['nomor' => '6289999999999'])->assertNotFound();
    }

    public function test_validasi_isi_wajib(): void
    {
        $this->nomor();

        $this->kirimPesan(['isi' => ''])->assertUnprocessable();
    }

    public function test_webhook_sesi_mengubah_status_dan_gagal_tercatat_di_pusat_risiko(): void
    {
        $nomor = $this->nomor('Menunggu QR');
        $kirim = fn (array $d) => $this->postJson(
            '/api/webhook/whatsapp/sesi',
            $d + ['nomor' => '081200000001'],
            ['X-Gateway-Token' => self::TOKEN]
        );

        $kirim(['status' => 'tersambung'])->assertOk();
        $this->assertSame('Tersambung', $nomor->fresh()->status_sesi);

        $kirim(['status' => 'terputus'])->assertOk();
        $this->assertSame('Terputus', $nomor->fresh()->status_sesi);

        $kirim(['status' => 'gagal', 'alasan' => 'Timeout'])->assertOk();
        $this->assertSame('Gagal tertaut', $nomor->fresh()->status_sesi);
        $this->assertDatabaseHas('insiden_risiko', [
            'nomor_whatsapp_id' => $nomor->id,
            'jenis_insiden' => 'Gagal tertaut',
        ]);

        $kirim(['status' => 'asal'])->assertUnprocessable();
    }
}
