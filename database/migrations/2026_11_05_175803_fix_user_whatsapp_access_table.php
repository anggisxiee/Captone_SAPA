<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_whatsapp_access', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('nomor_whatsapp_id')
                ->after('user_id')
                ->constrained('nomor_whatsapp')
                ->cascadeOnDelete();

            $table->enum('hak_akses', [
                'Tidak ada',
                'Lihat saja',
                'Balas chat',
                'Kelola',
            ])->default('Tidak ada')->after('nomor_whatsapp_id');

            $table->unique(['user_id', 'nomor_whatsapp_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_whatsapp_access', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'nomor_whatsapp_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['nomor_whatsapp_id']);
            $table->dropColumn([
                'user_id',
                'nomor_whatsapp_id',
                'hak_akses',
            ]);
        });
    }
};
