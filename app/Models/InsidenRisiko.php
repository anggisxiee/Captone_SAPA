<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsidenRisiko extends Model
{
    protected $table = 'insiden_risiko';

    protected $fillable = [
        'nomor_whatsapp_id',
        'jenis_insiden',
        'tingkat_risiko',
        'deskripsi',
        'status',
        'terjadi_pada',
    ];

    protected function casts(): array
    {
        return [
            'terjadi_pada' => 'datetime',
        ];
    }

    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(
            NomorWhatsapp::class,
            'nomor_whatsapp_id'
        );
    }
}