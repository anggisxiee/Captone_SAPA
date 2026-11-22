<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_audit', function (Blueprint $table) {
            $table->id();

            // Pelaku aksi (null jika akun dihapus atau percobaan login gagal)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('aksi');
            $table->string('objek')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('alamat_ip', 45)->nullable();

            $table->timestamps();

            $table->index('aksi');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_audit');
    }
};
