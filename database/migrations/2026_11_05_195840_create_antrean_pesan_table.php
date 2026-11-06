<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrean_pesan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomor_whatsapp_id')
                ->nullable()
                ->constrained('nomor_whatsapp')
                ->nullOnDelete();

            $table->string('pengirim');
            $table->string('penerima');
            $table->text('isi_pesan');

            $table->unsignedTinyInteger('skor_risiko')->default(0);

            $table->enum('keputusan', [
                'Kirim',
                'Ditunda',
                'Ditahan',
                'Terkirim',
                'Gagal'
            ])->default('Kirim');

            $table->unsignedInteger('percobaan')->default(0);
            $table->unsignedInteger('maks_percobaan')->default(3);

            $table->timestamp('jadwal_kirim')->nullable();
            $table->timestamp('terkirim_pada')->nullable();

            $table->text('alasan_penahanan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrean_pesan');
    }
};
