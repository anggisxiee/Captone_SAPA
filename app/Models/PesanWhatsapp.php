<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesanWhatsapp extends Model
{
    protected $table = 'pesan_whatsapp';

    protected $fillable = [
        'nomor_whatsapp_id',
        'kontak_nama',
        'kontak_telepon',
        'pengirim',
        'isi',
    ];

    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(
            NomorWhatsapp::class,
            'nomor_whatsapp_id'
        );
    }
}