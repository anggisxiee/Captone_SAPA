<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        
    public function up(): void
    {
        Schema::create('nomor_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nomor')->unique();
            $table->string('status')->default('Aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_whatsapp');
    }

};
