<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanKeluar;
use App\Models\ArtikelPengetahuan;
use App\Models\AntreanPesan;
use App\Models\NomorWhatsapp;
use App\Models\PengaturanAI;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\User;
use App\Services\Ai\FakeAiClient;
use App\Services\Ai\PenangananAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** F04/F05 pada pesan WhatsApp sungguhan: mode per nomor, daftar nomor uji, eskalasi, pengaman. */
class PenangananAiTest extends TestCase
{
    use RefreshDatabase;

    private const PELANGGAN = '6285711110001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        FakeAiClient::reset();

        ArtikelPengetahuan::create([
            'judul' => 'Jadwal dan biaya SMP kelas 8', 'kategori' => 'jadwal', 'aktif' => true,
            'isi' => 'Program Reguler Matematika SMP kelas 8 berlangsung Selasa dan Kamis pukul 16.00. Biaya mulai Rp450.000 per bulan.',
        ]);
        PengaturanAI::create([
            'id' => 1, 'penyedia' => 'x', 'model' => 'm', 'ambang_keyakinan' => 0.5,
            'eskalasi_pelanggan_marah' => true, 'kata_kunci_eskalasi' => 'admin,komplain,refund',
            'jeda_min' => 1, 'jeda_max' => 2,
        ]);
        FakeAiClient::antre(json_encode([
            'jawaban' => 'Biaya mulai Rp450.000 per bulan.', 'sumber' => ['KB-001'],
            'keyakinan' => 0.95, 'cukup' => true, 'maksud' => 'tanya_biaya',
        ]));
    }

    private function masuk(string $mode, string $isi = 'Berapa biaya matematika kelas 8 SMP?', string $telepon = self::PELANGGAN): PesanWhatsapp
    {
        $nomor = NomorWhatsapp::firstOrCreate(['nomor' => '6281200000001'], [
            'label' => 'Admisi', 'status_sesi' => 'Tersambung', 'mode_ai' => $mode,
        ]);
        $nomor->update(['mode_ai' => $mode]);

        $p = Percakapan::untuk($nomor, $telepon, 'Bu Ratna');
        $p->update(['belum_dibaca' => $p->belum_dibaca + 1, 'pesan_terakhir_pada' => now()]);

        return PesanWhatsapp::create([
            'percakapan_id' => $p->id, 'arah' => 'masuk', 'jenis_pengirim' => 'pelanggan',
            'isi' => $isi, 'status' => 'diterima',
        ]);
    }

    private function proses(PesanWhatsapp $pesan): string
    {
        return app(PenangananAi::class)->proses($pesan);
    }

    public function test_mode_draf_hanya_menyimpan_saran_tanpa_mengirim(): void
    {
        Bus::fake();
        $pesan = $this->masuk('draf');

        $this->assertSame('draf', $this->proses($pesan));

        $draf = PesanWhatsapp::where('jenis_pengirim', 'ai')->sole();
        $this->assertSame('draf', $draf->status);
        $this->assertSame($pesan->id, $draf->meta_ai['untuk_pesan_id']);
        $this->assertSame(0, AntreanPesan::count());
        Bus::assertNotDispatched(KirimPesanKeluar::class);
        $p = $pesan->percakapan->fresh();
        $this->assertSame(1, $p->belum_dibaca);
        $this->assertSame('Baru', $p->status);
    }

    public function test_mode_mati_diabaikan(): void
    {
        $this->assertSame('diabaikan', $this->proses($this->masuk('mati')));
        $this->assertSame(0, PesanWhatsapp::where('jenis_pengirim', 'ai')->count());
    }

    public function test_mode_otomatis_mengirim_jawaban_lewat_antrean_dengan_jeda(): void
    {
        Bus::fake();
        $pesan = $this->masuk('otomatis');

        $this->assertSame('kirim', $this->proses($pesan));

        $keluar = PesanWhatsapp::where('jenis_pengirim', 'ai')->sole();
        $this->assertSame('menunggu', $keluar->status);
        $this->assertStringContainsString('450.000', $keluar->isi);
        $antrean = AntreanPesan::sole();
        $this->assertSame(self::PELANGGAN, $antrean->penerima);
        $this->assertSame($keluar->id, $antrean->pesan_whatsapp_id);
        Bus::assertDispatched(KirimPesanKeluar::class, fn ($j) => $j->delay !== null && $j->antreanPesanId === $antrean->id);

        $p = $pesan->percakapan->fresh();
        $this->assertSame('Ditangani AI', $p->status);
        $this->assertSame(0, $p->belum_dibaca);
        $this->assertNotNull($p->waktu_balas_pertama);
    }

    public function test_otomatis_eskalasi_mengirim_pesan_eskalasi_dan_menyerahkan_ke_agen(): void
    {
        Bus::fake();
        $pesan = $this->masuk('otomatis', 'Saya mau komplain soal tagihan');

        $this->assertSame('eskalasi', $this->proses($pesan));

        $this->assertSame('Pertanyaan Anda kami teruskan ke admin.', PesanWhatsapp::where('jenis_pengirim', 'ai')->sole()->isi);
        $p = $pesan->percakapan->fresh();
        $this->assertSame('Ditangani Agen', $p->status);
        $this->assertNull($p->agen_id);
        $this->assertSame('agen', $p->penangan);
        $this->assertSame(1, $p->belum_dibaca);
        Bus::assertDispatched(KirimPesanKeluar::class);
    }

    public function test_br08_setelah_eskalasi_ai_berhenti_membalas(): void
    {
        Bus::fake();
        $pesan = $this->masuk('otomatis', 'Saya mau komplain');
        $this->proses($pesan);
        $jumlah = PesanWhatsapp::count();

        $lanjutan = $this->masuk('otomatis', 'Berapa biaya matematika kelas 8 SMP?');

        $this->assertSame('diabaikan', $this->proses($lanjutan));
        $this->assertSame($jumlah + 1, PesanWhatsapp::count());
    }

    public function test_percakapan_selesai_tidak_dijawab(): void
    {
        $pesan = $this->masuk('otomatis');
        $pesan->percakapan->update(['status' => 'Selesai']);

        $this->assertSame('diabaikan', $this->proses($pesan));
    }

    public function test_nomor_di_luar_daftar_uji_hanya_mendapat_draf(): void
    {
        Bus::fake();
        PengaturanAI::find(1)->update(['nomor_uji' => '6289999999999']);
        $pesan = $this->masuk('otomatis');

        $this->assertSame('draf', $this->proses($pesan));
        Bus::assertNotDispatched(KirimPesanKeluar::class);
        $this->assertSame('draf', PesanWhatsapp::where('jenis_pengirim', 'ai')->sole()->status);
    }

    public function test_nomor_di_dalam_daftar_uji_dibalas_otomatis(): void
    {
        Bus::fake();
        PengaturanAI::find(1)->update(['nomor_uji' => '6289999999999, ' . self::PELANGGAN]);

        $this->assertSame('kirim', $this->proses($this->masuk('otomatis')));
        Bus::assertDispatched(KirimPesanKeluar::class);
    }

    public function test_hanya_pesan_pelanggan_terbaru_dijawab(): void
    {
        $pertama = $this->masuk('draf');
        $this->masuk('draf', 'Dan jadwalnya?');

        $this->assertSame('diabaikan', $this->proses($pertama));
    }

    public function test_pemrosesan_berulang_idempoten(): void
    {
        $pesan = $this->masuk('draf');

        $this->assertSame('draf', $this->proses($pesan));
        $this->assertSame('diabaikan', $this->proses($pesan));
        $this->assertSame(1, PesanWhatsapp::where('jenis_pengirim', 'ai')->count());
    }

    public function test_batas_balasan_per_jam_menyerahkan_ke_agen_tanpa_membalas(): void
    {
        Bus::fake();
        config(['ai.maks_balasan_per_jam' => 2]);
        $pesan = $this->masuk('otomatis');
        foreach (range(1, 2) as $i) {
            PesanWhatsapp::create(['percakapan_id' => $pesan->percakapan_id, 'arah' => 'keluar', 'jenis_pengirim' => 'ai', 'isi' => 'x', 'status' => 'terkirim']);
        }

        $this->assertSame('batas', $this->proses($pesan));
        $this->assertSame('Ditangani Agen', $pesan->percakapan->fresh()->status);
        $this->assertSame(0, AntreanPesan::count());
        // Alasannya harus terlihat agen, bukan diam-diam.
        $draf = PesanWhatsapp::where('status', 'draf')->sole();
        $this->assertSame('batas_balasan', $draf->meta_ai['alasan']);
        Bus::assertNotDispatched(KirimPesanKeluar::class);
    }

    // --- webhook memicu pemrosesan ---------------------------------------------------------------

    public function test_webhook_memproses_pesan_baru_tetapi_tidak_duplikat(): void
    {
        config(['ai.proses_pesan_masuk' => true, 'whatsapp.webhook_token' => 'tok']);
        NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281200000001', 'status_sesi' => 'Tersambung', 'mode_ai' => 'draf']);
        $kirim = fn () => $this->withHeader('X-Gateway-Token', 'tok')->postJson('/api/webhook/whatsapp/pesan', [
            'nomor' => '6281200000001', 'dari' => self::PELANGGAN, 'isi' => 'Berapa biaya matematika kelas 8 SMP?', 'id_pesan' => 'A1',
        ]);

        $kirim()->assertCreated();
        $kirim()->assertOk();

        $this->assertSame(1, PesanWhatsapp::where('jenis_pengirim', 'ai')->count());
    }

    public function test_webhook_tidak_memproses_bila_dimatikan_lewat_konfigurasi(): void
    {
        config(['ai.proses_pesan_masuk' => false, 'whatsapp.webhook_token' => 'tok']);
        NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281200000001', 'status_sesi' => 'Tersambung', 'mode_ai' => 'otomatis']);

        $this->withHeader('X-Gateway-Token', 'tok')->postJson('/api/webhook/whatsapp/pesan', [
            'nomor' => '6281200000001', 'dari' => self::PELANGGAN, 'isi' => 'Berapa biaya?', 'id_pesan' => 'B1',
        ])->assertCreated();

        $this->assertSame(0, PesanWhatsapp::where('jenis_pengirim', 'ai')->count());
    }

    // --- Kotak Masuk -----------------------------------------------------------------------------

    public function test_kotak_masuk_menyembunyikan_draf_dan_menampilkan_keputusan_ai(): void
    {
        $this->proses($this->masuk('draf'));
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);

        $html = $this->actingAs($admin)->get('/kotak-masuk')->assertOk()->getContent();

        $this->assertStringContainsString('Saran (belum dikirim)', $html);
        $this->assertStringContainsString('Jawab dari artikel', $html);
        $this->assertStringContainsString('Gunakan sebagai balasan', $html);
        $this->assertStringContainsString('Biaya mulai Rp450.000 per bulan.', $html); // hanya sebagai saran
        $chat = $this->actingAs($admin)->get('/kotak-masuk')->viewData('daftar')[0];
        $this->assertCount(1, $chat['pesan']); // hanya pesan pelanggan; draf tidak jadi gelembung
    }

    public function test_kotak_masuk_melabeli_eskalasi_ai(): void
    {
        Bus::fake();
        $this->proses($this->masuk('otomatis', 'Saya mau komplain'));
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);

        $chat = $this->actingAs($admin)->get('/kotak-masuk')->viewData('daftar')[0];

        $this->assertSame('Eskalasi AI', $chat['status']);
        $this->assertStringContainsString('kata kunci eskalasi', $chat['keputusanAi']);
    }

    public function test_endpoint_data_memberi_percakapan_terbaru_untuk_pembaruan_otomatis(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
        $pesan = $this->masuk('draf');
        $this->actingAs($admin)->getJson('/kotak-masuk/data')->assertOk()
            ->assertJsonPath('daftar.0.keputusanAi', '-')->assertJsonPath('jumlahBelumDibalas', 1);

        $this->proses($pesan);

        $this->actingAs($admin)->getJson('/kotak-masuk/data')->assertOk()
            ->assertJsonPath('daftar.0.keputusanAi', 'Jawab dari artikel');
    }

    public function test_endpoint_data_membutuhkan_login(): void
    {
        $this->getJson('/kotak-masuk/data')->assertRedirect('/login');
    }

    // --- mode AI per nomor & nomor uji -----------------------------------------------------------

    public function test_admin_mengubah_mode_ai_nomor_dan_tercatat_di_audit(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
        $nomor = NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281200000001', 'tujuan' => 'Admin']);

        $this->actingAs($admin)->put("/nomor-whatsapp/{$nomor->id}", [
            'nama' => 'Admisi', 'nomor' => '6281200000001', 'tujuan' => 'Admin', 'mode_ai' => 'otomatis',
        ])->assertRedirect();

        $this->assertSame('otomatis', $nomor->fresh()->mode_ai);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Ubah mode AI nomor']);
    }

    public function test_mode_ai_tidak_valid_ditolak_dan_tanpa_mode_tidak_mengubahnya(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
        $nomor = NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281200000001', 'tujuan' => 'Admin', 'mode_ai' => 'mati']);
        $data = ['nama' => 'Admisi', 'nomor' => '6281200000001', 'tujuan' => 'Admin'];

        $this->actingAs($admin)->put("/nomor-whatsapp/{$nomor->id}", $data + ['mode_ai' => 'bebas'])->assertSessionHasErrors('mode_ai');
        $this->actingAs($admin)->put("/nomor-whatsapp/{$nomor->id}", $data)->assertSessionHasNoErrors();

        $this->assertSame('mati', $nomor->fresh()->mode_ai);
    }

    public function test_nomor_uji_dinormalisasi_dan_divalidasi(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
        $data = [
            'penyedia' => 'x', 'model' => 'm', 'ambang_keyakinan' => 0.6, 'eskalasi_pelanggan_marah' => 1,
            'jeda_min' => 1, 'jeda_max' => 2, 'batas_pesan_harian' => 10, 'jam_mulai' => '07:00', 'jam_selesai' => '21:00', 'variasi_kalimat' => 1,
        ];

        $this->actingAs($admin)->put('/pengaturan-ai', $data + ['nomor_uji' => '0857 1111 0001, +62 812-3456-7890'])->assertSessionHasNoErrors();
        $this->assertSame(['6285711110001', '6281234567890'], PengaturanAI::find(1)->daftarNomorUji());

        $this->actingAs($admin)->put('/pengaturan-ai', $data + ['nomor_uji' => '123'])->assertSessionHasErrors('nomor_uji');
    }
}
