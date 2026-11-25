<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesanWhatsapp extends Model
{
    protected $table = 'pesan_whatsapp';

    protected $fillable = [
        'percakapan_id',
        'arah',            // masuk | keluar
        'jenis_pengirim',  // pelanggan | ai | agen
        'isi',
        'status',          // diterima | menunggu | terkirim | gagal
        'meta_ai',         // jejak keputusan AI (JSON)
        'wa_message_id',
    ];

    protected function casts(): array
    {
        return [
            'meta_ai' => 'array',
        ];
    }

    public function percakapan(): BelongsTo
    {
        return $this->belongsTo(Percakapan::class, 'percakapan_id');
    }
}
