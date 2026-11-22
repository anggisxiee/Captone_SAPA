<?php

use App\Support\Telepon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel SRS yang belum ada (dipakai Sprint 2 dan 3) dan kolom baru pada prospek.
 * Kolom lama prospek (nama, nomor_telepon, jenjang, sumber, catatan) dipertahankan
 * sampai halaman CRM ditulis ulang pada Sprint 2, karena halaman Prospek masih
 * membacanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas_prospek', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospek_id')->constrained('prospek')->cascadeOnDelete();
            // nullOnDelete: riwayat aktivitas tetap ada walau penggunanya dihapus.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis', 30);
            $table->text('catatan');                       // wajib (BR05)
            $table->timestamp('waktu')->useCurrent();
            $table->timestamps();
        });

        Schema::create('artikel_pengetahuan', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('kategori', 50);                // program, jadwal, biaya, fasilitas, pendaftaran
            $table->text('isi');
            $table->string('label')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('metrik_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomor_whatsapp_id')->constrained('nomor_whatsapp')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedInteger('pesan_masuk')->default(0);
            $table->unsignedInteger('pesan_keluar')->default(0);
            $table->unsignedInteger('balasan_ai')->default(0);
            $table->unsignedInteger('balasan_manusia')->default(0);
            $table->unsignedInteger('belum_dibalas')->default(0);
            $table->unsignedInteger('rata_waktu_balas')->default(0); // detik
            $table->unsignedInteger('prospek_baru')->default(0);
            $table->unsignedInteger('konversi')->default(0);
            $table->timestamps();

            $table->unique(['nomor_whatsapp_id', 'tanggal']);
        });

        Schema::table('prospek', function (Blueprint $table) {
            $table->foreignId('kontak_id')->nullable()->constrained('kontak')->nullOnDelete();
            $table->text('rincian_skor')->nullable();      // lima komponen skor (JSON)
            $table->date('tindak_lanjut_berikut')->nullable();
        });

        // Hubungkan prospek yang sudah ada ke kontak (dibuat bila belum ada).
        $now = now();
        foreach (DB::table('prospek')->get() as $p) {
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
    }

    public function down(): void
    {
        Schema::table('prospek', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kontak_id');
            $table->dropColumn(['rincian_skor', 'tindak_lanjut_berikut']);
        });

        Schema::dropIfExists('metrik_harian');
        Schema::dropIfExists('artikel_pengetahuan');
        Schema::dropIfExists('aktivitas_prospek');
    }
};
