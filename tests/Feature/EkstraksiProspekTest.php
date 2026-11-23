<?php

namespace Tests\Feature;

use App\Models\Kontak;
use App\Models\NomorWhatsapp;
use App\Models\Prospek;
use App\Services\Prospek\EkstraksiPesan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F03 / BR04: kontak dan prospek otomatis serta ekstraksi data calon siswa. */
class EkstraksiProspekTest extends TestCase
{
    use RefreshDatabase;

    private function e(string $pesan): array
    {
        return (new EkstraksiPesan())->ekstrak($pesan);
    }

    // --- ekstraksi ---------------------------------------------------------------------------

    public function test_jenjang_kelas_dan_mapel_terbaca_dari_kalimat_wajar(): void
    {
        $h = $this->e('Halo admin, mau tanya biaya bimbel matematika kelas 8 SMP.');

        $this->assertSame('SMP', $h['jenjang']);
        $this->assertSame('8', $h['kelas']);
        $this->assertSame('Matematika', $h['mapel']);
        $this->assertSame('tanya_biaya', $h['maksud']);
    }

    public function test_jenjang_diturunkan_dari_nomor_kelas_dan_angka_romawi(): void
    {
        $this->assertSame(['SD', '6'], [$this->e('anak saya kls 6')['jenjang'], $this->e('anak saya kls 6')['kelas']]);
        $this->assertSame(['SMA', '12'], [$this->e('kelas 12')['jenjang'], $this->e('kelas 12')['kelas']]);
        $this->assertSame('8', $this->e('kelas VIII ya')['kelas']);
        $this->assertSame('SMP', $this->e('kelas VIII ya')['jenjang']);
    }

    public function test_jenjang_eksplisit_menang_atas_turunan_dan_utbk_berarti_sma(): void
    {
        $this->assertSame('SMA', $this->e('kelas 3 SMA')['jenjang']);
        $this->assertSame('SMA', $this->e('info program utbk dong')['jenjang']);
        $this->assertNull($this->e('info program utbk dong')['kelas']);
    }

    public function test_beberapa_mapel_berurutan_sesuai_kemunculan(): void
    {
        $this->assertSame('Fisika, Matematika', $this->e('butuh les fisika dan matematika')['mapel']);
        $this->assertSame('Bahasa Inggris', $this->e('les b inggris ada?')['mapel']);
    }

    public function test_tidak_menebak_bila_tidak_ada_data(): void
    {
        $h = $this->e('Selamat siang');

        $this->assertSame(['lainnya', null, null, null, null], [$h['maksud'], $h['nama'], $h['jenjang'], $h['kelas'], $h['mapel']]);
    }

    public function test_kata_mirip_tidak_dianggap_jenjang_atau_mapel(): void
    {
        $this->assertNull($this->e('Hari ini jam 3 s/d 5 sore, bisa? Smart sekali')['jenjang']);
        $this->assertNull($this->e('kelas 99')['kelas']);
        $this->assertNull($this->e('ipanema')['mapel']);
    }

    public function test_nama_dari_pola_perkenalan_dan_berhenti_di_kata_bukan_nama(): void
    {
        $this->assertSame('Budi Santoso', $this->e('nama anak saya budi santoso kelas 7')['nama']);
        $this->assertSame('Rina', $this->e('Perkenalkan saya Rina dari Bekasi')['nama']);
        $this->assertSame('Dewi', $this->e('saya bernama dewi mau daftar')['nama']);
        $this->assertNull($this->e('nama saya kelas 8')['nama']);
        $this->assertNull($this->e('Berapa biaya kelas 8?')['nama']);
    }

    public function test_maksud_diklasifikasi_dengan_prioritas(): void
    {
        $this->assertSame('keluhan', $this->e('Saya mau komplain soal biaya')['maksud']);
        $this->assertSame('tanya_biaya', $this->e('biaya dan jadwalnya?')['maksud']);
        $this->assertSame('tanya_pendaftaran', $this->e('cara daftar gimana')['maksud']);
        $this->assertSame('tanya_jadwal', $this->e('hari apa saja kelasnya')['maksud']);
        $this->assertSame('tanya_fasilitas', $this->e('ada wifi?')['maksud']);
    }

    // --- pipeline webhook --------------------------------------------------------------------

    private function kirim(string $isi, string $dari = '6285711110001', ?string $nama = null, string $id = null): void
    {
        config(['whatsapp.webhook_token' => 'tok']);
        NomorWhatsapp::firstOrCreate(['nomor' => '6281200000001'], ['label' => 'Admisi', 'status_sesi' => 'Tersambung', 'mode_ai' => 'mati']);

        $this->postJson('/api/webhook/whatsapp/pesan', array_filter([
            'nomor' => '6281200000001', 'dari' => $dari, 'nama' => $nama, 'isi' => $isi, 'id_pesan' => $id ?? uniqid(),
        ]), ['X-Gateway-Token' => 'tok'])->assertSuccessful();
    }

    public function test_pengirim_baru_menjadi_kontak_dan_prospek_baru_dengan_data_terisi(): void
    {
        $this->kirim('Halo, mau tanya biaya bimbel matematika kelas 8 SMP', nama: 'Bu Ratna');

        $kontak = Kontak::where('nomor_telepon', '6285711110001')->sole();
        $this->assertSame(['Bu Ratna', 'SMP', '8', 'Matematika', 'WhatsApp'], [$kontak->nama, $kontak->jenjang, $kontak->kelas, $kontak->mapel, $kontak->sumber]);

        $prospek = Prospek::sole();
        $this->assertSame($kontak->id, $prospek->kontak_id);
        $this->assertSame('baru', $prospek->tahap);
        $this->assertGreaterThan(0, $prospek->skor); // skor dihitung saat prospek dibuat
        $this->assertCount(5, $prospek->rincian_skor);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Buat prospek otomatis']);
    }

    public function test_pesan_berikutnya_melengkapi_kolom_kosong_tanpa_menimpa_dan_tanpa_prospek_ganda(): void
    {
        $this->kirim('Halo, saya mau tanya');
        $this->kirim('anak saya kelas 8 SMP, butuh les fisika');
        $this->kirim('eh sebenarnya kelas 9 SMA, les kimia');

        $kontak = Kontak::sole();
        $this->assertSame(['SMP', '8', 'Fisika'], [$kontak->jenjang, $kontak->kelas, $kontak->mapel]);
        $this->assertSame(1, Prospek::count());
    }

    public function test_pesan_duplikat_tidak_memproses_ulang(): void
    {
        $this->kirim('kelas 8 SMP', id: 'X1');
        $this->kirim('kelas 12 SMA', id: 'X1');

        $this->assertSame('8', Kontak::sole()->kelas);
        $this->assertSame(1, Prospek::count());
    }

    public function test_nama_dari_isi_pesan_menggantikan_nomor_sebagai_nama(): void
    {
        $this->kirim('Halo, nama anak saya Budi');

        $this->assertSame('Budi', Kontak::sole()->nama);
        $this->assertSame('Budi', Prospek::sole()->kontak->nama);
    }

    public function test_kontak_lama_tanpa_prospek_dibuatkan_prospek(): void
    {
        $kontak = Kontak::create(['nomor_telepon' => '6285711110001']);

        $this->kirim('info kelas 6 SD');

        $this->assertSame($kontak->id, Prospek::sole()->kontak_id);
        $this->assertSame('SD', $kontak->fresh()->jenjang);
    }

    public function test_nama_berupa_nomor_telepon_dianggap_belum_diketahui(): void
    {
        // Pesan baru dari agen menyimpan nomor sebagai nama sementara.
        Kontak::create(['nomor_telepon' => '6285711110001', 'nama' => '6285711110001']);

        $this->kirim('nama saya Siti');

        $this->assertSame('Siti', Kontak::sole()->nama);
    }

    public function test_nama_yang_sudah_ada_tidak_ditimpa(): void
    {
        Kontak::create(['nomor_telepon' => '6285711110001', 'nama' => 'Bu Ratna']);

        $this->kirim('nama anak saya Budi');

        $this->assertSame('Bu Ratna', Kontak::sole()->nama);
    }
}
