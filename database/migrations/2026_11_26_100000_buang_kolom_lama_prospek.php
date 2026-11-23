<?php

use App\Support\Telepon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM (PB-10): data identitas calon siswa kini hanya ada di tabel kontak, sesuai SRS.
 * Prospek menyimpan kontak_id, tahap, skor, rincian skor, program, dan jadwal tindak lanjut.
 *
 * Sebelum kolom lama dibuang, prospek tanpa kontak dihubungkan ke kontak, dan isi `catatan`
 * dipindahkan menjadi aktivitas agar tidak hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('prospek')->whereNull('kontak_id')->get() as $p) {
            $telepon = Telepon::normalisasi((string) $p->nomor_telepon);
            if ($telepon === '') {
                continue;
            }

            $kontak = DB::table('kontak')->where('nomor_telepon', $telepon)->first();
            $kontakId = $kontak->id ?? DB::table('kontak')->insertGetId([
                'nama' => $p->nama, 'nomor_telepon' => $telepon, 'jenjang' => $p->jenjang,
                'sumber' => $p->sumber, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('prospek')->where('id', $p->id)->update(['kontak_id' => $kontakId]);
        }

        foreach (DB::table('prospek')->whereNotNull('catatan')->where('catatan', '!=', '')->get() as $p) {
            DB::table('aktivitas_prospek')->insert([
                'prospek_id' => $p->id, 'user_id' => $p->penanggung_jawab, 'jenis' => 'catatan',
                'catatan' => $p->catatan, 'waktu' => $p->created_at ?? $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('prospek', function (Blueprint $table) {
            $table->dropColumn(['nama', 'nomor_telepon', 'jenjang', 'sumber', 'catatan']);
        });
    }

    public function down(): void
    {
        Schema::table('prospek', function (Blueprint $table) {
            $table->string('nama')->nullable();
            $table->string('nomor_telepon')->nullable();
            $table->string('jenjang')->nullable();
            $table->string('sumber')->nullable();
            $table->text('catatan')->nullable();
        });

        // Salin kembali dari kontak agar halaman versi lama tetap berfungsi.
        foreach (DB::table('prospek')->whereNotNull('kontak_id')->get() as $p) {
            $k = DB::table('kontak')->find($p->kontak_id);
            if ($k) {
                DB::table('prospek')->where('id', $p->id)->update([
                    'nama' => $k->nama ?: $k->nomor_telepon, 'nomor_telepon' => $k->nomor_telepon,
                    'jenjang' => $k->jenjang, 'sumber' => $k->sumber,
                ]);
            }
        }
    }
};
