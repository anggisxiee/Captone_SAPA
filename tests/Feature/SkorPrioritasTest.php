<?php

namespace Tests\Feature;

use App\Models\Kontak;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\Prospek;
use App\Services\Prospek\SkorPrioritas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** BR06: skor prioritas dari lima komponen berbobot, dapat dijelaskan, dan dihitung ulang. */
class SkorPrioritasTest extends TestCase
{
    use RefreshDatabase;

    private function prospek(array $kontak = [], string $tahap = 'baru'): Prospek
    {
        $k = Kontak::create($kontak + ['nomor_telepon' => '6285711110001']);

        return Prospek::create(['kontak_id' => $k->id, 'tahap' => $tahap]);
    }

    private function chat(Prospek $p, array $pesan): Percakapan
    {
        $nomor = NomorWhatsapp::firstOrCreate(['nomor' => '6281200000001'], ['label' => 'Admisi', 'status_sesi' => 'Tersambung']);
        $c = Percakapan::firstOrCreate(['nomor_whatsapp_id' => $nomor->id, 'kontak_id' => $p->kontak_id]);
        foreach ($pesan as $i => [$arah, $isi, $menit]) {
            $m = new PesanWhatsapp([
                'percakapan_id' => $c->id, 'arah' => $arah, 'isi' => $isi,
                'jenis_pengirim' => $arah === 'masuk' ? 'pelanggan' : 'ai',
                'status' => $arah === 'masuk' ? 'diterima' : 'terkirim',
            ]);
            $m->created_at = now()->startOfDay()->addMinutes($menit);
            $m->save();
        }

        return $c;
    }

    private function komponen(Prospek $p): array
    {
        return collect(app(SkorPrioritas::class)->hitung($p)['rincian'])->keyBy('kunci')->all();
    }

    public function test_prospek_kosong_bernilai_rendah_dan_rincian_lima_komponen(): void
    {
        $p = $this->prospek();

        $h = app(SkorPrioritas::class)->hitung($p);

        $this->assertCount(5, $h['rincian']);
        $this->assertLessThan(15, $h['skor']);
        $this->assertSame(['kelengkapan', 'urgensi', 'minat', 'responsivitas', 'riwayat'], array_column($h['rincian'], 'kunci'));
    }

    public function test_prospek_lengkap_mendesak_dan_berminat_bernilai_tinggi(): void
    {
        $p = $this->prospek(['nama' => 'Bu Ratna', 'jenjang' => 'SMA', 'kelas' => '12', 'mapel' => 'Matematika', 'sumber' => 'WhatsApp'], 'tertarik');
        $this->chat($p, [
            ['masuk', 'Mau daftar program utbk, ujian bulan depan', 0],
            ['keluar', 'Baik, ini infonya', 1],
            ['masuk', 'Berapa biayanya?', 5],
            ['masuk', 'Bisa segera mulai?', 9],
            ['masuk', 'Jadwalnya kapan?', 12],
        ]);
        $p->aktivitas()->createMany(array_fill(0, 3, ['jenis' => 'catatan', 'catatan' => 'telepon', 'waktu' => now()]));

        $this->assertGreaterThanOrEqual(85, app(SkorPrioritas::class)->hitung($p)['skor']);
    }

    public function test_kelengkapan_menghitung_lima_data_dan_nomor_bukan_nama(): void
    {
        $p = $this->prospek(['nama' => '6285711110001', 'jenjang' => 'SMP', 'kelas' => '8']);

        $k = $this->komponen($p)['kelengkapan'];

        $this->assertSame(0.4, $k['nilai']);
        $this->assertStringContainsString('nama', $k['keterangan']);
        $this->assertStringContainsString('mata pelajaran', $k['keterangan']);
    }

    public function test_urgensi_bertingkat_menurut_jumlah_penanda_berbeda(): void
    {
        $p = $this->prospek();
        $this->assertSame(0.0, $this->komponen($p)['urgensi']['nilai']);

        $this->chat($p, [['masuk', 'ujian saya minggu depan', 0]]);
        $this->assertSame(1.0, $this->komponen($p)['urgensi']['nilai']); // ujian + minggu depan
    }

    public function test_satu_penanda_urgensi_bernilai_setengah(): void
    {
        $p = $this->prospek();
        $this->chat($p, [['masuk', 'ada tryout nggak', 0], ['masuk', 'tryout lagi dong', 3]]);

        $this->assertSame(0.5, $this->komponen($p)['urgensi']['nilai']); // penanda sama tidak dihitung dua kali
    }

    public function test_minat_memakai_maksud_terkuat_dari_semua_pesan(): void
    {
        $p = $this->prospek();
        $this->chat($p, [['masuk', 'ada wifi?', 0], ['masuk', 'cara daftar gimana', 2], ['masuk', 'selamat siang', 4]]);

        $k = $this->komponen($p)['minat'];

        $this->assertSame(1.0, $k['nilai']);
        $this->assertStringContainsString('pendaftaran', $k['keterangan']);
    }

    public function test_responsivitas_menggabungkan_jumlah_pesan_dan_balasan_dalam_24_jam(): void
    {
        $p = $this->prospek();
        // 2 pesan pelanggan (0,5 x 2/4) + 1 dari 2 pesan kita dibalas (0,5 x 0,5)
        $this->chat($p, [['masuk', 'halo', 0], ['keluar', 'jawaban 1', 1], ['masuk', 'oke', 10], ['keluar', 'jawaban 2', 20]]);

        $this->assertEqualsWithDelta(0.5, $this->komponen($p)['responsivitas']['nilai'], 0.001);
    }

    public function test_balasan_setelah_lebih_dari_24_jam_tidak_dihitung(): void
    {
        $p = $this->prospek();
        // pesan kita pukul 00:01; pelanggan baru menulis 25 jam kemudian
        $this->chat($p, [['keluar', 'info program', 1], ['masuk', 'oke terima kasih', 25 * 60 + 1]]);

        $this->assertStringContainsString('0 dari 1', $this->komponen($p)['responsivitas']['keterangan']);
    }

    public function test_riwayat_menggabungkan_tahap_dan_aktivitas(): void
    {
        $p = $this->prospek([], 'penawaran');
        $p->aktivitas()->create(['jenis' => 'catatan', 'catatan' => 'x', 'waktu' => now()]);

        // 0,7 x 0,9 + 0,3 x 1/3
        $this->assertEqualsWithDelta(0.73, $this->komponen($p)['riwayat']['nilai'], 0.001);
    }

    public function test_draf_ai_tidak_dihitung_sebagai_pesan_kita(): void
    {
        $p = $this->prospek();
        $c = $this->chat($p, [['masuk', 'halo', 0]]);
        PesanWhatsapp::create(['percakapan_id' => $c->id, 'arah' => 'keluar', 'jenis_pengirim' => 'ai', 'isi' => 'saran', 'status' => 'draf']);

        $this->assertStringContainsString('belum ada pesan kita', $this->komponen($p)['responsivitas']['keterangan']);
    }

    public function test_bobot_dapat_diubah_dan_skor_tetap_0_sampai_100(): void
    {
        $p = $this->prospek([], 'daftar');
        config(['prospek.bobot' => ['kelengkapan' => 0, 'urgensi' => 0, 'minat' => 0, 'responsivitas' => 0, 'riwayat' => 1]]);
        $this->assertSame(70, app(SkorPrioritas::class)->hitung($p)['skor']); // riwayat 0,7 x 1,0 tahap daftar tanpa aktivitas

        // Bobot dikalikan 10 menghasilkan skor yang sama: yang dipakai adalah proporsinya.
        $dasar = ['kelengkapan' => 25, 'urgensi' => 25, 'minat' => 20, 'responsivitas' => 15, 'riwayat' => 15];
        config(['prospek.bobot' => $dasar]);
        $normal = app(SkorPrioritas::class)->hitung($p)['skor'];
        config(['prospek.bobot' => array_map(fn ($b) => $b * 10, $dasar)]);
        $this->assertSame($normal, app(SkorPrioritas::class)->hitung($p)['skor']);
    }

    public function test_simpan_menyimpan_skor_dan_rincian_dan_poin_berjumlah_skor(): void
    {
        $p = $this->prospek(['nama' => 'Bu Ratna', 'jenjang' => 'SMP'], 'dihubungi');
        $this->chat($p, [['masuk', 'biaya kelas 8 smp berapa?', 0]]);

        app(SkorPrioritas::class)->simpan($p);
        $p->refresh();

        $this->assertEqualsWithDelta($p->skor, array_sum(array_column($p->rincian_skor, 'poin')), 0.6);
        $this->assertGreaterThan(0, $p->skor);
    }

    public function test_perintah_hitung_ulang_memperbarui_semua_prospek(): void
    {
        $p = $this->prospek(['nama' => 'Bu Ratna', 'jenjang' => 'SMP', 'kelas' => '8', 'mapel' => 'Matematika', 'sumber' => 'WhatsApp']);
        $this->assertSame(0, $p->fresh()->skor);

        $this->artisan('prospek:hitung-ulang')->expectsOutputToContain('1 prospek dihitung ulang')->assertSuccessful();

        $this->assertGreaterThan(0, $p->fresh()->skor);
        $this->assertNotNull($p->fresh()->rincian_skor);
    }

    public function test_kolom_identitas_lama_sudah_dibuang_dari_prospek(): void
    {
        foreach (['nama', 'nomor_telepon', 'jenjang', 'sumber', 'catatan'] as $kolom) {
            $this->assertFalse(Schema::hasColumn('prospek', $kolom), $kolom);
        }
        foreach (['kontak_id', 'penanggung_jawab', 'tahap', 'skor', 'rincian_skor', 'program', 'tindak_lanjut_berikut'] as $kolom) {
            $this->assertTrue(Schema::hasColumn('prospek', $kolom), $kolom);
        }
    }
}
