<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanKeluar;
use App\Models\AntreanPesan;
use App\Models\NomorWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** Pengiriman pesan keluar dari antrean lewat gateway. */
class KirimPesanKeluarTest extends TestCase
{
    use RefreshDatabase;

    private function antrean(string $isi = 'Halo!', string $sesi = 'Tersambung', ?string $telepon = '6285711110001'): AntreanPesan
    {
        $nomor = NomorWhatsapp::create([
            'label' => 'Admisi',
            'nomor' => '6281200000001',
            'status_sesi' => $sesi,
        ]);

        return AntreanPesan::create([
            'nomor_whatsapp_id' => $nomor->id,
            'pengirim' => 'Admisi',
            'penerima' => $telepon ?? 'Bu Ratna',   // baris lama berisi nama, bukan nomor
            'isi_pesan' => $isi,
            'keputusan' => 'Kirim',
            'maks_percobaan' => 3,
            'jadwal_kirim' => now(),
        ]);
    }

    public function test_pesan_terkirim_dan_tercatat_di_riwayat(): void
    {
        $a = $this->antrean();

        KirimPesanKeluar::dispatchSync($a->id);

        $a->refresh();
        $this->assertSame('Terkirim', $a->keputusan);
        $this->assertNotNull($a->terkirim_pada);
        $this->assertSame(1, $a->percobaan);
        $this->assertDatabaseHas('riwayat_antrean', [
            'antrean_pesan_id' => $a->id,
            'status_baru' => 'Terkirim',
        ]);
    }

    public function test_pesan_yang_bukan_berkeputusan_kirim_diabaikan(): void
    {
        $a = $this->antrean();
        $a->update(['keputusan' => 'Ditahan']);

        KirimPesanKeluar::dispatchSync($a->id);

        $this->assertSame('Ditahan', $a->fresh()->keputusan);
        $this->assertSame(0, $a->fresh()->percobaan);
    }

    public function test_gagal_dicoba_ulang_lalu_menyerah_dan_masuk_pusat_risiko(): void
    {
        $a = $this->antrean('Tes [gagal]');

        // Antrean sync: percobaan ulang berjalan langsung sampai batas.
        KirimPesanKeluar::dispatchSync($a->id);

        $a->refresh();
        $this->assertSame('Gagal', $a->keputusan);
        $this->assertSame(3, $a->percobaan);
        $this->assertStringContainsString('Simulasi gagal', $a->alasan_penahanan);
        $this->assertDatabaseHas('insiden_risiko', ['jenis_insiden' => 'Pesan gagal terkirim']);
    }

    public function test_gagal_pertama_menjadwalkan_percobaan_ulang_dengan_jeda(): void
    {
        Bus::fake();
        $a = $this->antrean('Tes [gagal]');

        (new KirimPesanKeluar($a->id))->handle(app(\App\Services\WhatsApp\WhatsAppGateway::class));

        $a->refresh();
        $this->assertSame('Kirim', $a->keputusan);
        $this->assertSame(1, $a->percobaan);
        Bus::assertDispatched(KirimPesanKeluar::class, fn ($job) => $job->delay !== null);
    }

    public function test_nomor_tidak_tersambung_gagal_setelah_percobaan_habis(): void
    {
        $a = $this->antrean('Halo', 'Terputus');

        KirimPesanKeluar::dispatchSync($a->id);

        $a->refresh();
        $this->assertSame('Gagal', $a->keputusan);
        $this->assertStringContainsString('tidak tersambung', $a->alasan_penahanan);
    }

    public function test_penerima_yang_bukan_nomor_telepon_langsung_gagal_tanpa_percobaan_ulang(): void
    {
        // Antrean lama menyimpan nama di kolom penerima; sejak SRS kolom ini berisi nomor.
        $a = $this->antrean('Halo', 'Tersambung', null);

        KirimPesanKeluar::dispatchSync($a->id);

        $a->refresh();
        $this->assertSame('Gagal', $a->keputusan);
        $this->assertSame(0, $a->percobaan);
        $this->assertStringContainsString('tidak valid', $a->alasan_penahanan);
    }

    public function test_balasan_agen_di_kotak_masuk_langsung_dikirim(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
        $nomor = NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281', 'status_sesi' => 'Tersambung']);
        $p = \App\Models\Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');

        $this->actingAs($user)->post(route('kotak-masuk.kirim'), [
            'percakapan_id' => $p->id,
            'isi' => 'Jadwalnya Senin dan Rabu.',
        ])->assertRedirect();

        $antrean = AntreanPesan::firstOrFail();
        $this->assertSame('6285711110001', $antrean->penerima);
        $this->assertSame('Terkirim', $antrean->keputusan);
    }

    public function test_perintah_proses_menjadwalkan_pesan_yang_tertinggal(): void
    {
        $a = $this->antrean();

        $this->artisan('antrean:proses')->assertSuccessful();

        $this->assertSame('Terkirim', $a->fresh()->keputusan);
    }
}
