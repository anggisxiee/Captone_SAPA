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

        $admisi = NomorWhatsapp::updateOrCreate(['nomor' => '6281200000001'], [
            'nama' => 'Admisi Asentra',
            'status' => 'Aktif',
        ]);

        $sales = NomorWhatsapp::updateOrCreate(['nomor' => '6281200000002'], [
            'nama' => 'Sales Asentra',
            'status' => 'Aktif',
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

        $this->buatPercakapan($admisi, '6285711110001', 'Bu Ratna', 'Halo, bimbel kelas 6 SD ada jadwal apa saja?');
        $this->buatPercakapan($sales, '6285711110002', 'Pak Anwar', 'Berapa biaya paket intensif UTBK?');
    }

    private function buatPercakapan(
        NomorWhatsapp $nomor,
        string $telepon,
        string $nama,
        string $isi
    ): void {
        $percakapan = Percakapan::untuk($nomor, $telepon, $nama);

        if ($percakapan->pesan()->exists()) {
            return;
        }

        PesanWhatsapp::create([
            'nomor_whatsapp_id' => $nomor->id,
            'percakapan_id' => $percakapan->id,
            'kontak_nama' => $nama,
            'kontak_telepon' => $telepon,
            'pengirim' => 'pelanggan',
            'isi' => $isi,
        ]);

        $percakapan->update([
            'belum_dibalas' => true,
            'pesan_terakhir_pada' => now(),
        ]);
    }
}
