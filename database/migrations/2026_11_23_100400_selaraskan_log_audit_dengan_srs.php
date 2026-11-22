<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * log_audit sesuai SRS: entitas (nama tabel), entitas_id, dan detail (JSON).
 * objek menjadi entitas, keterangan masuk ke detail. alamat_ip dipertahankan.
 */
return new class extends Migration
{
    private const PETA = [
        'Autentikasi' => 'users',
        'Nomor WhatsApp' => 'nomor_whatsapp',
        'Percakapan' => 'percakapan',
    ];

    public function up(): void
    {
        Schema::table('log_audit', function (Blueprint $table) {
            $table->string('entitas', 50)->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();
            $table->text('detail')->nullable();
        });

        foreach (DB::table('log_audit')->get() as $l) {
            DB::table('log_audit')->where('id', $l->id)->update([
                'entitas' => $l->objek ? (self::PETA[$l->objek] ?? Str::snake($l->objek)) : null,
                'detail' => $l->keterangan ? json_encode(['keterangan' => $l->keterangan], JSON_UNESCAPED_UNICODE) : null,
            ]);
        }

        Schema::table('log_audit', function (Blueprint $table) {
            $table->dropColumn(['objek', 'keterangan']);
        });
    }

    public function down(): void
    {
        Schema::table('log_audit', function (Blueprint $table) {
            $table->string('objek')->nullable();
            $table->text('keterangan')->nullable();
        });

        foreach (DB::table('log_audit')->get() as $l) {
            DB::table('log_audit')->where('id', $l->id)->update([
                'objek' => $l->entitas,
                'keterangan' => json_decode($l->detail ?? 'null', true)['keterangan'] ?? null,
            ]);
        }

        Schema::table('log_audit', function (Blueprint $table) {
            $table->dropColumn(['entitas', 'entitas_id', 'detail']);
        });
    }
};
