<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * percakapan: tambah agen_id, penangan, belum_dibaca (jumlah), waktu_balas_pertama
 * (detik) sesuai SRS, lalu ganti belum_dibalas (boolean) dengan belum_dibaca.
 * Nilai dihitung dari pesan yang sudah tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('percakapan', function (Blueprint $table) {
            $table->foreignId('agen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('penangan', 10)->default('AI');
            $table->unsignedInteger('belum_dibaca')->default(0);
            $table->unsignedInteger('waktu_balas_pertama')->nullable();
        });

        foreach (DB::table('percakapan')->get() as $p) {
            $pesan = DB::table('pesan_whatsapp')->where('percakapan_id', $p->id)->orderBy('id')->get();

            // Flag lama belum_dibalas tetap menjadi acuan (percakapan yang sudah
            // diselesaikan tidak boleh tiba-tiba punya pesan belum dibaca). Bila true,
            // jumlahnya = pesan pelanggan beruntun setelah balasan terakhir (minimal 1).
            $belumDibaca = 0;
            if ($p->belum_dibalas) {
                foreach ($pesan->reverse() as $m) {
                    if ($m->pengirim !== 'pelanggan') {
                        break;
                    }
                    $belumDibaca++;
                }
                $belumDibaca = max(1, $belumDibaca);
            }

            // Waktu balas pertama: pesan pelanggan pertama -> balasan pertama setelahnya.
            $waktu = null;
            $masuk = $pesan->firstWhere('pengirim', 'pelanggan');
            if ($masuk) {
                $balasan = $pesan->first(fn ($m) => $m->pengirim !== 'pelanggan' && $m->id > $masuk->id);
                if ($balasan) {
                    $waktu = max(0, Carbon::parse($masuk->created_at)->diffInSeconds(Carbon::parse($balasan->created_at), true));
                }
            }

            DB::table('percakapan')->where('id', $p->id)->update([
                'penangan' => $p->status === 'Ditangani Agen' ? 'agen' : 'AI',
                'belum_dibaca' => $belumDibaca,
                'waktu_balas_pertama' => $waktu === null ? null : (int) $waktu,
            ]);
        }

        Schema::table('percakapan', function (Blueprint $table) {
            $table->dropColumn('belum_dibalas');
        });
    }

    public function down(): void
    {
        Schema::table('percakapan', function (Blueprint $table) {
            $table->boolean('belum_dibalas')->default(false);
        });

        DB::table('percakapan')->where('belum_dibaca', '>', 0)->update(['belum_dibalas' => true]);

        Schema::table('percakapan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agen_id');
            $table->dropColumn(['penangan', 'belum_dibaca', 'waktu_balas_pertama']);
        });
    }
};
