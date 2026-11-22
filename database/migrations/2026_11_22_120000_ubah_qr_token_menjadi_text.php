<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // QR dari WhatsApp panjangnya sekitar 280 karakter, melebihi varchar(255).
    public function up(): void
    {
        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->text('qr_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nomor_whatsapp', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->change();
        });
    }
};
