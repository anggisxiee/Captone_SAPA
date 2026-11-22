<?php

namespace Database\Seeders;

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
            'nama' => 'Admisi Asentra',
            'tujuan' => 'Admin',
            'status' => 'Aktif',
            'status_sesi' => $sesiAwal,
            'terakhir_aktif_pada' => $aktifAwal,
        ]);

        $sales = NomorWhatsapp::updateOrCreate(['nomor' => '6281200000002'], [
            'nama' => 'Sales Asentra',
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
            $m = new PesanWhatsapp([
                'nomor_whatsapp_id' => $nomor->id,
                'percakapan_id' => $percakapan->id,
                'kontak_nama' => $nama,
                'kontak_telepon' => $telepon,
                'pengirim' => $pengirim,
                'isi' => $isi,
            ]);
            $m->created_at = now()->subMinutes(30 - $i);
            $m->save();
        }

        $percakapan->update([
            'status' => $status,
            'belum_dibalas' => $belumDibalas,
            'pesan_terakhir_pada' => now()->subMinutes(30 - count($pesan)),
        ]);
    }
}
