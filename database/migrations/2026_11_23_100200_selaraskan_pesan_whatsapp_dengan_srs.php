<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * pesan_whatsapp sesuai SRS: percakapan_id, arah, jenis_pengirim, isi, status, meta_ai.
 * kontak_nama, kontak_telepon, dan nomor_whatsapp_id dibuang karena dapat ditelusuri
 * lewat percakapan -> kontak. Tabel dibangun ulang (SQLite tidak aman membuang kolom
 * ber-indeks dan berkunci asing). Data lama disalin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->lengkapiPercakapanYangKosong();

        Schema::create('pesan_whatsapp_baru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('percakapan_id')->constrained('percakapan')->cascadeOnDelete();
            $table->string('arah', 10);               // masuk | keluar
            $table->string('jenis_pengirim', 10);     // pelanggan | ai | agen
            $table->text('isi');
            $table->string('status', 20)->default('diterima'); // diterima | menunggu | terkirim | gagal
            $table->text('meta_ai')->nullable();      // jejak keputusan AI (JSON)
            $table->string('wa_message_id')->nullable(); // id dari gateway, mencegah pesan masuk ganda
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO pesan_whatsapp_baru
                (id, percakapan_id, arah, jenis_pengirim, isi, status, wa_message_id, created_at, updated_at)
            SELECT
                id,
                percakapan_id,
                CASE WHEN pengirim = 'pelanggan' THEN 'masuk' ELSE 'keluar' END,
                pengirim,
                isi,
                CASE WHEN pengirim = 'pelanggan' THEN 'diterima' ELSE 'terkirim' END,
                wa_message_id,
                created_at,
                updated_at
            FROM pesan_whatsapp
            WHERE percakapan_id IS NOT NULL
        ");

        Schema::drop('pesan_whatsapp');
        Schema::rename('pesan_whatsapp_baru', 'pesan_whatsapp');

        Schema::table('pesan_whatsapp', function (Blueprint $table) {
            $table->unique(['percakapan_id', 'wa_message_id']);
            $table->index(['percakapan_id', 'created_at']);
        });
    }

    /** Baris yang belum terhubung ke percakapan dihubungkan dulu agar tidak hilang. */
    private function lengkapiPercakapanYangKosong(): void
    {
        $now = now();

        foreach (DB::table('pesan_whatsapp')->whereNull('percakapan_id')->get() as $p) {
            $kontak = DB::table('kontak')->where('nomor_telepon', $p->kontak_telepon)->first();
            $kontakId = $kontak->id ?? DB::table('kontak')->insertGetId([
                'nama' => $p->kontak_nama, 'nomor_telepon' => $p->kontak_telepon,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $percakapan = DB::table('percakapan')
                ->where('nomor_whatsapp_id', $p->nomor_whatsapp_id)->where('kontak_id', $kontakId)->first();
            $percakapanId = $percakapan->id ?? DB::table('percakapan')->insertGetId([
                'nomor_whatsapp_id' => $p->nomor_whatsapp_id, 'kontak_id' => $kontakId,
                'status' => 'Baru', 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('pesan_whatsapp')->where('id', $p->id)->update(['percakapan_id' => $percakapanId]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi ini mengubah dan membuang kolom; tidak dapat dibalik otomatis. Pulihkan dari cadangan basis data.'
        );
    }
};
