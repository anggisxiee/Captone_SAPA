<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prospek extends Model
{
    protected $table = 'prospek';

    protected $fillable = [
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
        ];
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'penanggung_jawab'
        );
    }
}
