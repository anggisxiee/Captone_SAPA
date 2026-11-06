<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAntrean extends Model
{
    protected $table = 'riwayat_antrean';

    protected $fillable = [
        'antrean_pesan_id',
        'status_sebelumnya',
        'status_baru',
        'keterangan',
        'diubah_pada',
    ];

    protected function casts(): array
    {
        return [
            'diubah_pada' => 'datetime',
        ];
    }

    public function antreanPesan(): BelongsTo
    {
        return $this->belongsTo(
            AntreanPesan::class,
            'antrean_pesan_id'
        );
    }
}
