<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AntreanPesan extends Model
{
    protected $table = 'antrean_pesan';

    protected $fillable = [
        'nomor_whatsapp_id',
        'pengirim',
        'penerima',
        'isi_pesan',
        'skor_risiko',
        'keputusan',
        'percobaan',
        'maks_percobaan',
        'jadwal_kirim',
        'terkirim_pada',
        'alasan_penahanan',
    ];

    protected function casts(): array
    {
        return [
            'skor_risiko' => 'integer',
            'percobaan' => 'integer',
            'maks_percobaan' => 'integer',
            'jadwal_kirim' => 'datetime',
            'terkirim_pada' => 'datetime',
        ];
    }

    /**
     * Relasi ke nomor WhatsApp.
     */
    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(
            NomorWhatsapp::class,
            'nomor_whatsapp_id'
        );
    }

    /**
     * Relasi ke riwayat perubahan status antrean.
     */
    public function riwayat(): HasMany
    {
        return $this->hasMany(
            RiwayatAntrean::class,
            'antrean_pesan_id'
        )->latest('diubah_pada');
    }
}
