
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_ai', function (Blueprint $table) {
            $table->id();

            // Penyedia AI
            $table->string('penyedia')->default('OpenAI-compatible');
            $table->string('model')->default('gpt-4o-mini');
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->text('instruksi_persona')->nullable();

            // Ambang keyakinan dan eskalasi
            $table->decimal('ambang_keyakinan', 4, 2)->default(0.62);
            $table->boolean('eskalasi_pelanggan_marah')->default(true);
            $table->text('kata_kunci_eskalasi')->nullable();

            // Anti-ban
            $table->unsignedInteger('jeda_min')->default(3);
            $table->unsignedInteger('jeda_max')->default(8);
            $table->unsignedInteger('batas_pesan_harian')->default(1500);
            $table->time('jam_mulai')->default('07:00:00');
            $table->time('jam_selesai')->default('21:00:00');
            $table->boolean('variasi_kalimat')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_ai');
    }
};
