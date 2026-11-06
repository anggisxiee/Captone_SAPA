<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesan_whatsapp', function (Blueprint $table) {
            $table->id();

            // Nomor WhatsApp yang digunakan
            $table->foreignId('nomor_whatsapp_id')
                ->constrained('nomor_whatsapp')
                ->cascadeOnDelete();

            // Identitas pelanggan
            $table->string('kontak_nama');
            $table->string('kontak_telepon');

            // Pengirim pesan
            $table->string('pengirim');

            // Isi pesan
            $table->text('isi');

            $table->timestamps();

            // Index untuk pencarian percakapan
            $table->index([
                'nomor_whatsapp_id',
                'kontak_telepon'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_whatsapp');
    }
};