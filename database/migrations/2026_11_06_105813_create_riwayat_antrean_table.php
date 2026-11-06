<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_antrean', function (Blueprint $table) {
            $table->id();

            $table->foreignId('antrean_pesan_id')
                ->constrained('antrean_pesan')
                ->cascadeOnDelete();

            $table->string('status_sebelumnya')->nullable();
            $table->string('status_baru');

            $table->text('keterangan')->nullable();

            $table->timestamp('diubah_pada')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_antrean');
    }
};
