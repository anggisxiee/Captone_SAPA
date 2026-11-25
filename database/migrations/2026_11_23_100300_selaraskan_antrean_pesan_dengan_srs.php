<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * antrean_pesan: penerima kini berisi nomor tujuan (sesuai SRS), bukan nama, dan
 * terhubung ke pesan_whatsapp lewat pesan_whatsapp_id. Kolom penerima_telepon
 * (tambahan sementara) digabung ke penerima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antrean_pesan', function (Blueprint $table) {
            $table->foreignId('pesan_whatsapp_id')
                ->nullable()
                ->constrained('pesan_whatsapp')
                ->nullOnDelete();
        });

        DB::statement("UPDATE antrean_pesan SET penerima = penerima_telepon WHERE penerima_telepon IS NOT NULL AND penerima_telepon <> ''");

        // Hubungkan antrean lama ke pesan keluar yang isinya sama pada nomor yang sama.
        foreach (DB::table('antrean_pesan')->get() as $a) {
            $pesanId = DB::table('pesan_whatsapp as m')
                ->join('percakapan as p', 'p.id', '=', 'm.percakapan_id')
                ->where('p.nomor_whatsapp_id', $a->nomor_whatsapp_id)
                ->where('m.arah', 'keluar')
                ->where('m.isi', $a->isi_pesan)
                ->orderByDesc('m.id')
                ->value('m.id');

            if ($pesanId) {
                DB::table('antrean_pesan')->where('id', $a->id)->update(['pesan_whatsapp_id' => $pesanId]);
            }
        }

        Schema::table('antrean_pesan', function (Blueprint $table) {
            $table->dropColumn('penerima_telepon');
        });
    }

    public function down(): void
    {
        Schema::table('antrean_pesan', function (Blueprint $table) {
            $table->string('penerima_telepon', 30)->nullable();
        });

        DB::statement('UPDATE antrean_pesan SET penerima_telepon = penerima');

        Schema::table('antrean_pesan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pesan_whatsapp_id');
        });
    }
};
