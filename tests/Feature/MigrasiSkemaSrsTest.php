<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Menjalankan jalur peningkatan nyata: skema sebelum penyelarasan SRS, diisi data
 * lama, lalu migrasi 2026_11_23_* dijalankan. Memastikan tidak ada data yang hilang
 * atau salah arti.
 */
class MigrasiSkemaSrsTest extends TestCase
{
    private const BATAS = '2026_11_23_';

    /** @return array{lama: string[], baru: string[]} */
    private function berkasMigrasi(): array
    {
        $semua = glob(database_path('migrations/*.php'));
        sort($semua);

        return [
            'lama' => array_values(array_filter($semua, fn ($f) => basename($f) < self::BATAS)),
            'baru' => array_values(array_filter($semua, fn ($f) => str_starts_with(basename($f), self::BATAS))),
        ];
    }

    private function migrasi(array $berkas, string $perintah = 'migrate'): void
    {
        $kode = Artisan::call($perintah, ['--path' => $berkas, '--realpath' => true, '--force' => true]);
        $this->assertSame(0, $kode, Artisan::output());
    }

    private function isiDataLama(): void
    {
        $t = '2026-11-20 10:00:00';
        $at = fn (int $detik) => date('Y-m-d H:i:s', strtotime($t) + $detik);

        DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'email' => 'a@x.id', 'password' => 'x', 'role' => 'Administrator', 'status' => 'Aktif', 'created_at' => $t, 'updated_at' => $t]);
        DB::table('nomor_whatsapp')->insert(['id' => 1, 'nama' => 'Admisi', 'nomor' => '6281200000001', 'tujuan' => 'Admin', 'status' => 'Aktif', 'status_sesi' => 'Tersambung', 'created_at' => $t, 'updated_at' => $t]);
        DB::table('kontak')->insert([
            ['id' => 1, 'nama' => 'Bu Ratna', 'nomor_telepon' => '6285711110001', 'created_at' => $t, 'updated_at' => $t],
            ['id' => 2, 'nama' => 'Pak Anwar', 'nomor_telepon' => '6285711110002', 'created_at' => $t, 'updated_at' => $t],
        ]);
        DB::table('percakapan')->insert([
            ['id' => 1, 'nomor_whatsapp_id' => 1, 'kontak_id' => 1, 'status' => 'Baru', 'belum_dibalas' => 1, 'created_at' => $t, 'updated_at' => $t],
            ['id' => 2, 'nomor_whatsapp_id' => 1, 'kontak_id' => 2, 'status' => 'Selesai', 'belum_dibalas' => 0, 'created_at' => $t, 'updated_at' => $t],
        ]);

        $pesan = fn ($id, $perc, $nama, $telp, $pengirim, $isi, $detik) => [
            'id' => $id, 'nomor_whatsapp_id' => 1, 'percakapan_id' => $perc, 'kontak_nama' => $nama, 'kontak_telepon' => $telp,
            'pengirim' => $pengirim, 'isi' => $isi, 'wa_message_id' => $pengirim === 'pelanggan' ? "WA-{$id}" : null,
            'created_at' => $at($detik), 'updated_at' => $at($detik),
        ];
        DB::table('pesan_whatsapp')->insert([
            $pesan(1, 1, 'Bu Ratna', '6285711110001', 'pelanggan', 'Halo', 0),
            $pesan(2, 1, 'Bu Ratna', '6285711110001', 'agen', 'Balasan', 120),
            $pesan(3, 1, 'Bu Ratna', '6285711110001', 'pelanggan', 'Satu lagi', 200),
            // Percakapan yang sudah selesai: pesan terakhir dari pelanggan, tetapi belum_dibalas = false.
            $pesan(4, 2, 'Pak Anwar', '6285711110002', 'pelanggan', 'Terima kasih', 10),
            // Baris yatim (belum terhubung ke percakapan) tidak boleh hilang.
            $pesan(5, null, 'Yatim', '6285799990000', 'pelanggan', 'Pesan yatim', 20),
        ]);

        DB::table('antrean_pesan')->insert([
            ['id' => 1, 'nomor_whatsapp_id' => 1, 'pengirim' => 'Admisi', 'penerima' => 'Bu Ratna', 'penerima_telepon' => '6285711110001', 'isi_pesan' => 'Balasan', 'keputusan' => 'Terkirim', 'created_at' => $t, 'updated_at' => $t],
            // Baris lama tanpa nomor: penerima hanya berisi nama.
            ['id' => 2, 'nomor_whatsapp_id' => 1, 'pengirim' => 'Admisi', 'penerima' => 'Nama Saja', 'penerima_telepon' => null, 'isi_pesan' => 'tidak ada pesannya', 'keputusan' => 'Gagal', 'created_at' => $t, 'updated_at' => $t],
        ]);

        DB::table('log_audit')->insert([
            ['id' => 1, 'user_id' => 1, 'aksi' => 'Login', 'objek' => 'Autentikasi', 'keterangan' => 'Pengguna berhasil masuk.', 'alamat_ip' => '127.0.0.1', 'created_at' => $t, 'updated_at' => $t],
            ['id' => 2, 'user_id' => 1, 'aksi' => 'Nomor tersambung', 'objek' => 'Nomor WhatsApp', 'keterangan' => 'Admisi berhasil tertaut', 'alamat_ip' => null, 'created_at' => $t, 'updated_at' => $t],
            ['id' => 3, 'user_id' => null, 'aksi' => 'Aksi tanpa objek', 'objek' => null, 'keterangan' => null, 'alamat_ip' => null, 'created_at' => $t, 'updated_at' => $t],
        ]);

        DB::table('prospek')->insert(['id' => 1, 'nama' => 'Calon Siswa', 'nomor_telepon' => '0812-3456-7890', 'jenjang' => 'SMP', 'sumber' => 'Instagram', 'tahap' => 'baru', 'skor' => 10, 'created_at' => $t, 'updated_at' => $t]);
    }

    public function test_data_lama_selamat_dan_dikonversi_dengan_benar(): void
    {
        $berkas = $this->berkasMigrasi();
        $this->assertNotEmpty($berkas['lama']);
        $this->assertCount(6, $berkas['baru']);

        $this->migrasi($berkas['lama'], 'migrate:fresh');
        $this->isiDataLama();
        $this->migrasi($berkas['baru']);

        // nomor_whatsapp: nama -> label
        $nomor = DB::table('nomor_whatsapp')->first();
        $this->assertSame('Admisi', $nomor->label);
        $this->assertSame('draf', $nomor->mode_ai);
        $this->assertSame(100, (int) $nomor->skor_kesehatan);

        // percakapan: belum_dibaca, penangan, waktu balas pertama
        $p1 = DB::table('percakapan')->find(1);
        $this->assertSame(1, (int) $p1->belum_dibaca, 'satu pesan pelanggan setelah balasan terakhir');
        $this->assertSame(120, (int) $p1->waktu_balas_pertama);
        $this->assertSame('AI', $p1->penangan);

        $p2 = DB::table('percakapan')->find(2);
        $this->assertSame(0, (int) $p2->belum_dibaca, 'percakapan selesai tidak boleh tiba-tiba punya pesan belum dibaca');
        $this->assertNull($p2->waktu_balas_pertama);

        // pesan_whatsapp: semua pesan selamat, termasuk yang yatim
        $this->assertSame(5, DB::table('pesan_whatsapp')->count());
        $agen = DB::table('pesan_whatsapp')->find(2);
        $this->assertSame(['keluar', 'agen', 'terkirim'], [$agen->arah, $agen->jenis_pengirim, $agen->status]);
        $masuk = DB::table('pesan_whatsapp')->find(1);
        $this->assertSame(['masuk', 'pelanggan', 'diterima', 'WA-1'], [$masuk->arah, $masuk->jenis_pengirim, $masuk->status, $masuk->wa_message_id]);

        $yatim = DB::table('pesan_whatsapp')->find(5);
        $kontakYatim = DB::table('percakapan as p')->join('kontak as k', 'k.id', '=', 'p.kontak_id')
            ->where('p.id', $yatim->percakapan_id)->first();
        $this->assertSame('Yatim', $kontakYatim->nama);
        $this->assertSame('6285799990000', $kontakYatim->nomor_telepon);

        // antrean_pesan: penerima = nomor, terhubung ke pesan keluar yang cocok
        $a1 = DB::table('antrean_pesan')->find(1);
        $this->assertSame('6285711110001', $a1->penerima);
        $this->assertSame(2, (int) $a1->pesan_whatsapp_id);
        $a2 = DB::table('antrean_pesan')->find(2);
        $this->assertSame('Nama Saja', $a2->penerima, 'baris lama tanpa nomor dibiarkan apa adanya');
        $this->assertNull($a2->pesan_whatsapp_id);

        // log_audit: entitas = nama tabel, detail JSON, alamat_ip tetap
        $l1 = DB::table('log_audit')->find(1);
        $this->assertSame('users', $l1->entitas);
        $this->assertSame(['keterangan' => 'Pengguna berhasil masuk.'], json_decode($l1->detail, true));
        $this->assertSame('127.0.0.1', $l1->alamat_ip);
        $this->assertSame('nomor_whatsapp', DB::table('log_audit')->find(2)->entitas);
        $l3 = DB::table('log_audit')->find(3);
        $this->assertNull($l3->entitas);
        $this->assertNull($l3->detail);

        // prospek: terhubung ke kontak (dibuat dengan nomor yang diseragamkan), kolom lama tetap
        $prospek = DB::table('prospek')->first();
        $kontak = DB::table('kontak')->find($prospek->kontak_id);
        $this->assertSame('6281234567890', $kontak->nomor_telepon);
        $this->assertSame('Calon Siswa', $kontak->nama);
        $this->assertSame('SMP', $kontak->jenjang);
        $this->assertSame('0812-3456-7890', $prospek->nomor_telepon, 'kolom lama dipertahankan untuk halaman Prospek');

        // jumlah data lain tidak berubah
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(2, DB::table('antrean_pesan')->count());
        $this->assertSame(3, DB::table('log_audit')->count());
    }

    public function test_migrasi_terbaru_dapat_dibalik_untuk_yang_memang_reversibel(): void
    {
        $berkas = $this->berkasMigrasi();
        $this->migrasi($berkas['lama'], 'migrate:fresh');
        $this->migrasi($berkas['baru']);

        $kode = Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $this->assertSame(0, $kode, Artisan::output());
        $this->assertFalse(\Schema::hasTable('metrik_harian'));
        $this->assertFalse(\Schema::hasColumn('prospek', 'kontak_id'));
    }
}
