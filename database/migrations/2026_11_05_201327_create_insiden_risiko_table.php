<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insiden_risiko', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomor_whatsapp_id')
                ->nullable()
                ->constrained('nomor_whatsapp')
                ->nullOnDelete();

            $table->string('jenis_insiden');
            
            $table->enum('tingkat_risiko', [
                'Rendah',
                'Sedang',
                'Tinggi',
                'Kritis'
            ])->default('Rendah');

            $table->text('deskripsi');

            $table->enum('status', [
                'Terbuka',
                'Ditangani',
                'Selesai'
            ])->default('Terbuka');

            $table->timestamp('terjadi_pada')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insiden_risiko');
    }
};
