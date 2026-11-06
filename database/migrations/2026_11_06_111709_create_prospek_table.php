<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospek', function (Blueprint $table) {
            $table->id();

            // Identitas calon siswa
            $table->string('nama');
            $table->string('nomor_telepon', 20);
            $table->string('jenjang')->nullable();
            $table->string('program')->nullable();

            // Informasi prospek
            $table->unsignedTinyInteger('skor')->default(0);
            $table->enum('tahap', [
                'baru',
                'dihubungi',
                'tertarik',
                'penawaran',
                'daftar',
                'batal'
            ])->default('baru');

            // Penanggung jawab
            $table->foreignId('penanggung_jawab')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Sumber prospek dan catatan
            $table->string('sumber')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospek');
    }
};
