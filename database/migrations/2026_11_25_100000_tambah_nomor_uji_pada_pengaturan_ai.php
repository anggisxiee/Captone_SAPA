<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_ai', function (Blueprint $table) {
            // Daftar nomor pelanggan (format 62..., dipisah koma). Bila terisi, AI hanya
            // membalas otomatis ke nomor-nomor ini; pelanggan lain hanya mendapat saran (draf).
            // Kosong = tanpa pembatasan. Dipakai saat uji dengan nomor sungguhan.
            $table->text('nomor_uji')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_ai', function (Blueprint $table) {
            $table->dropColumn('nomor_uji');
        });
    }
};
