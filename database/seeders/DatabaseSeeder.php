<?php

namespace Database\Seeders;

use App\Models\ArtikelPengetahuan;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Data demo untuk pengembangan. Semua akun berpassword "password123".
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        $admin = User::updateOrCreate(['email' => 'admin@asentra.sch.id'], [
            'name' => 'Administrator',
            'password' => $password,
            'role' => 'Administrator',
            'status' => 'Aktif',
        ]);

        $supervisor = User::updateOrCreate(['email' => 'supervisor@asentra.sch.id'], [
            'name' => 'Supervisor',
            'password' => $password,
            'role' => 'Supervisor',
            'status' => 'Aktif',
        ]);

        $dewi = User::updateOrCreate(['email' => 'dewi@asentra.sch.id'], [
            'name' => 'Dewi',
            'password' => $password,
            'role' => 'Agen/CS',
            'status' => 'Aktif',
        ]);

        User::updateOrCreate(['email' => 'nonaktif@asentra.sch.id'], [
            'name' => 'Akun Nonaktif',
            'password' => $password,
            'role' => 'Agen/CS',
            'status' => 'Nonaktif',
        ]);

        // Dengan driver fake, nomor demo langsung "Tersambung". Dengan gateway
        // sungguhan tidak ada sesi sama sekali sampai QR dipindai, jadi mulai
        // dari "Belum tertaut" agar status tidak menyesatkan.
        $sesiAwal = config('whatsapp.driver') === 'fake' ? 'Tersambung' : 'Belum tertaut';
        $aktifAwal = $sesiAwal === 'Tersambung' ? now() : null;

        $admisi = NomorWhatsapp::updateOrCreate(['nomor' => '6281200000001'], [
            'label' => 'Admisi Asentra',
            'tujuan' => 'Admin',
            'status' => 'Aktif',
            'status_sesi' => $sesiAwal,
            'terakhir_aktif_pada' => $aktifAwal,
        ]);

        $sales = NomorWhatsapp::updateOrCreate(['nomor' => '6281200000002'], [
            'label' => 'Sales Asentra',
            'tujuan' => 'Sales',
            'status' => 'Aktif',
            'status_sesi' => $sesiAwal,
            'terakhir_aktif_pada' => $aktifAwal,
        ]);

        // Hak akses per nomor.
        $supervisor->nomorWhatsapp()->syncWithoutDetaching([
            $admisi->id => ['hak_akses' => 'Kelola'],
            $sales->id => ['hak_akses' => 'Lihat saja'],
        ]);

        $dewi->nomorWhatsapp()->syncWithoutDetaching([
            $admisi->id => ['hak_akses' => 'Balas chat'],
            $sales->id => ['hak_akses' => 'Tidak ada'],
        ]);

        $this->artikelContoh();

        $this->buatPercakapan($admisi, '6285711110001', 'Bu Ratna', [
            ['pelanggan', 'Halo, bimbel kelas 6 SD ada jadwal apa saja?'],
        ], 'Baru', true);

        $this->buatPercakapan($admisi, '6285711110003', 'Rina Kusuma', [
            ['pelanggan', 'Halo admin, mau tanya biaya dan jadwal bimbel matematika kelas 8 SMP.'],
            ['ai', 'Halo Kak Rina! Program Reguler Matematika tersedia Selasa & Kamis. Apakah ingin dijadwalkan trial gratis?'],
        ], 'Ditangani AI', false);

        $this->buatPercakapan($admisi, '6285711110004', 'Bapak Hendra', [
            ['pelanggan', 'Besok sore saya transfer untuk pendaftarannya.'],
            ['agen', 'Baik Pak, ditunggu ya. Terima kasih.'],
        ], 'Ditangani Agen', false);

        $this->buatPercakapan($sales, '6285711110002', 'Pak Anwar', [
            ['pelanggan', 'Berapa biaya paket intensif UTBK?'],
        ], 'Baru', true);

        $this->buatPercakapan($sales, '6285711110005', 'Agus Wibowo', [
            ['pelanggan', 'Boleh minta brosur program bimbel untuk kelas 12?'],
            ['ai', 'Tentu Pak Agus, berikut brosur programnya.'],
            ['pelanggan', 'Terima kasih, brosur sudah saya terima.'],
        ], 'Selesai', false);
    }

    /**
     * Artikel basis pengetahuan CONTOH (fiktif) untuk pengembangan dan demo. Isi sebenarnya
     * berasal dari mitra; basis data produksi dimulai kosong (BR15).
     */
    private function artikelContoh(): void
    {
        $artikel = [
            ['Jadwal dan biaya SMP kelas 8', 'jadwal', true, 'reguler,matematika,trial,kelas 8,smp',
                'Program Reguler Matematika SMP kelas 8 berlangsung Selasa dan Kamis pukul 16.00-17.30 WIB, serta kelas intensif Sabtu. Biaya mulai Rp450.000 per bulan. Tersedia kelas percobaan gratis satu pertemuan.'],
            ['Skema diskon kakak-beradik', 'biaya', true, 'diskon,kakak adik,saudara,potongan,promo',
                'Siswa kedua dari keluarga yang sama mendapat potongan 10 persen dari biaya bulanan. Potongan berlaku selama kedua siswa aktif dan tidak dapat digabung dengan promo lain.'],
            ['Prosedur pendaftaran tryout SNBT', 'pendaftaran', true, 'tryout,snbt,utbk,ujian,daftar',
                'Pendaftaran tryout SNBT dilakukan melalui admin dengan menyebutkan nama, asal sekolah, dan pilihan jurusan. Tryout diadakan setiap akhir bulan dan hasilnya dibahas pada pertemuan berikutnya.'],
            ['Fasilitas kelas dan ruang belajar', 'fasilitas', true, 'ruang,kelas,wifi,ac,belajar mandiri',
                'Setiap kelas maksimal 12 siswa dengan pendingin ruangan, papan tulis digital, dan Wi-Fi. Ruang belajar mandiri dapat dipakai siswa terdaftar di luar jam kelas.'],
            ['Program intensif UTBK', 'program', true, 'utbk,snbt,intensif,kelas 12,persiapan',
                'Program intensif UTBK untuk kelas 12 mencakup penalaran, literasi, dan matematika dengan latihan soal mingguan. Kelas berlangsung Senin, Rabu, dan Jumat pukul 18.30-20.30 WIB.'],
            ['Cara pembayaran dan cicilan', 'biaya', true, 'bayar,cicilan,transfer,angsuran',
                'Pembayaran dilakukan bulanan melalui transfer bank. Cicilan dua kali per bulan tersedia untuk program intensif dengan persetujuan admin.'],
            ['Kebijakan garansi peningkatan nilai', 'program', false, 'garansi,jaminan,nilai',
                'DRAF: ketentuan garansi masih dibahas dengan pemilik dan belum boleh dijawab oleh AI.'],
        ];

        foreach ($artikel as [$judul, $kategori, $aktif, $label, $isi]) {
            ArtikelPengetahuan::updateOrCreate(
                ['judul' => $judul],
                ['kategori' => $kategori, 'aktif' => $aktif, 'label' => $label, 'isi' => $isi]
            );
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pesan  [pengirim, isi]
     */
    private function buatPercakapan(
        NomorWhatsapp $nomor,
        string $telepon,
        string $nama,
        array $pesan,
        string $status,
        bool $belumDibalas
    ): void {
        $percakapan = Percakapan::untuk($nomor, $telepon, $nama);

        if ($percakapan->pesan()->exists()) {
            return;
        }

        foreach ($pesan as $i => [$pengirim, $isi]) {
            $masuk = $pengirim === 'pelanggan';
            $m = new PesanWhatsapp([
                'percakapan_id' => $percakapan->id,
                'arah' => $masuk ? 'masuk' : 'keluar',
                'jenis_pengirim' => $pengirim,
                'isi' => $isi,
                'status' => $masuk ? 'diterima' : 'terkirim',
            ]);
            $m->created_at = now()->subMinutes(30 - $i);
            $m->save();
        }

        $percakapan->update([
            'status' => $status,
            'belum_dibaca' => $belumDibalas ? 1 : 0,
            'pesan_terakhir_pada' => now()->subMinutes(30 - count($pesan)),
        ]);
    }
}
