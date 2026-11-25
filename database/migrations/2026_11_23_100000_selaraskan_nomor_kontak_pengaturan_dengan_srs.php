<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan nomor_whatsapp, kontak, dan pengaturan_ai dengan kamus data SRS
 * (Tabel 3.19). Kolom tambahan di luar SRS (status_sesi, qr_token, base_url, dst.)
 * sengaja dipertahankan; lihat docs/skema-vs-srs.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        // nomor_whatsapp: nama -> label, tambah mode_ai dan skor_kesehatan
        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->renameColumn('nama', 'label');
        });

        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            // Otomatis, draf, atau mati. "draf": AI hanya menyarankan, agen yang mengirim.
            $table->string('mode_ai', 20)->default('draf');
            $table->unsignedSmallInteger('skor_kesehatan')->default(100);
        });

        // kontak: nama boleh kosong; tambah kota, sumber, label
        Schema::table('kontak', function (Blueprint $table) {
            $table->string('nama')->nullable()->change();
        });

        Schema::table('kontak', function (Blueprint $table) {
            $table->string('kota', 100)->nullable();
            $table->string('sumber', 50)->nullable();
            $table->string('label', 100)->nullable();
        });

        // pengaturan_ai: pengaturan per nomor (kosong = berlaku umum) dan batas laju 3 lapis
        Schema::table('pengaturan_ai', function (Blueprint $table) {
            $table->foreignId('nomor_whatsapp_id')
                ->nullable()
                ->constrained('nomor_whatsapp')
                ->nullOnDelete();

            // Nilai awal; dituning pada Sprint 3 (mesin anti-ban).
            $table->unsignedInteger('batas_per_menit')->default(10);
            $table->unsignedInteger('batas_per_jam')->default(200);
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_ai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nomor_whatsapp_id');
            $table->dropColumn(['batas_per_menit', 'batas_per_jam']);
        });

        Schema::table('kontak', function (Blueprint $table) {
            $table->dropColumn(['kota', 'sumber', 'label']);
        });

        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->dropColumn(['mode_ai', 'skor_kesehatan']);
        });

        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->renameColumn('label', 'nama');
        });
    }
};
