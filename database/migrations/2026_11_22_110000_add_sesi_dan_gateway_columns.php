<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            // Tujuan nomor: Admin atau Sales.
            $table->string('tujuan')->default('Admin')->after('nomor');

            // Status sesi WhatsApp (terpisah dari status Aktif/Nonaktif).
            $table->string('status_sesi')->default('Belum tertaut')->after('status');
            $table->string('qr_token')->nullable();
            $table->timestamp('qr_kedaluwarsa_pada')->nullable();
            $table->timestamp('terakhir_aktif_pada')->nullable();
        });

        Schema::table('antrean_pesan', function (Blueprint $table) {
            // Nomor telepon tujuan; kolom "penerima" hanya menyimpan nama.
            $table->string('penerima_telepon', 30)->nullable()->after('penerima');
        });

        Schema::table('pesan_whatsapp', function (Blueprint $table) {
            // ID pesan dari gateway, mencegah pesan masuk tersimpan ganda.
            $table->string('wa_message_id')->nullable();
            $table->unique(['nomor_whatsapp_id', 'wa_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pesan_whatsapp', function (Blueprint $table) {
            $table->dropUnique(['nomor_whatsapp_id', 'wa_message_id']);
            $table->dropColumn('wa_message_id');
        });

        Schema::table('antrean_pesan', function (Blueprint $table) {
            $table->dropColumn('penerima_telepon');
        });

        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->dropColumn([
                'tujuan',
                'status_sesi',
                'qr_token',
                'qr_kedaluwarsa_pada',
                'terakhir_aktif_pada',
            ]);
        });
    }
};
