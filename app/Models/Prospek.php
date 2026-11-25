<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospek extends Model
{
    public const TAHAP = ['baru', 'dihubungi', 'tertarik', 'penawaran', 'daftar', 'batal'];

    protected $table = 'prospek';

    protected $fillable = [
        'kontak_id',
        'penanggung_jawab',
        'tahap',
        'skor',
        'rincian_skor',
        'program',
        'tindak_lanjut_berikut',
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
        return $this->belongsTo(User::class, 'penanggung_jawab');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(AktivitasProspek::class, 'prospek_id');
    }
}
