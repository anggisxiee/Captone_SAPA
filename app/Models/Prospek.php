<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prospek extends Model
{
    protected $table = 'prospek';

    protected $fillable = [
        'kontak_id',
        'rincian_skor',
        'tindak_lanjut_berikut',
        'nama',
        'nomor_telepon',
        'jenjang',
        'program',
        'skor',
        'tahap',
        'penanggung_jawab',
        'sumber',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'skor' => 'integer',
            'rincian_skor' => 'array',
            'tindak_lanjut_berikut' => 'date',
        ];
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(Kontak::class, 'kontak_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'penanggung_jawab'
        );
    }
}
