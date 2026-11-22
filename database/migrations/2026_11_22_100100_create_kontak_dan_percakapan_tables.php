<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontak', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nomor_telepon', 30)->unique();
            $table->string('jenjang')->nullable();
            $table->string('kelas')->nullable();
            $table->string('mapel')->nullable();
            $table->timestamps();
        });

        Schema::create('percakapan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomor_whatsapp_id')
                ->constrained('nomor_whatsapp')
                ->cascadeOnDelete();

            $table->foreignId('kontak_id')
                ->constrained('kontak')
                ->cascadeOnDelete();

            $table->enum('status', [
                'Baru',
                'Ditangani AI',
                'Ditangani Agen',
                'Selesai',
            ])->default('Baru');

            // True apabila pesan terakhir dari pelanggan belum dibalas.
            $table->boolean('belum_dibalas')->default(false);
            $table->timestamp('pesan_terakhir_pada')->nullable();

            $table->timestamps();

            $table->unique(['nomor_whatsapp_id', 'kontak_id']);
        });

        Schema::table('pesan_whatsapp', function (Blueprint $table) {
            $table->foreignId('percakapan_id')
                ->nullable()
                ->after('nomor_whatsapp_id')
                ->constrained('percakapan')
                ->cascadeOnDelete();
        });

        $this->pindahkanDataLama();
    }

    /**
     * Membentuk kontak dan percakapan dari pesan yang sudah tersimpan.
     */
    private function pindahkanDataLama(): void
    {
        $pesan = DB::table('pesan_whatsapp')->orderBy('id')->get();
        $now = now();

        foreach ($pesan as $p) {
            $kontak = DB::table('kontak')
                ->where('nomor_telepon', $p->kontak_telepon)
                ->first();

            $kontakId = $kontak->id ?? DB::table('kontak')->insertGetId([
                'nama' => $p->kontak_nama,
                'nomor_telepon' => $p->kontak_telepon,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $percakapan = DB::table('percakapan')
                ->where('nomor_whatsapp_id', $p->nomor_whatsapp_id)
                ->where('kontak_id', $kontakId)
                ->first();

            $percakapanId = $percakapan->id ?? DB::table('percakapan')->insertGetId([
                'nomor_whatsapp_id' => $p->nomor_whatsapp_id,
                'kontak_id' => $kontakId,
                'status' => 'Baru',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pesan_whatsapp')
                ->where('id', $p->id)
                ->update(['percakapan_id' => $percakapanId]);

            // Status mengikuti pesan terakhir dalam percakapan.
            DB::table('percakapan')->where('id', $percakapanId)->update([
                'belum_dibalas' => $p->pengirim !== 'agen',
                'status' => $p->pengirim === 'agen' ? 'Ditangani Agen' : 'Baru',
                'pesan_terakhir_pada' => $p->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pesan_whatsapp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('percakapan_id');
        });

        Schema::dropIfExists('percakapan');
        Schema::dropIfExists('kontak');
    }
};
