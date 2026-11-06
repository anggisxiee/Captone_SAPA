<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanAI extends Model
{
    protected $table = 'pengaturan_ai';

    protected $fillable = [
        'penyedia',
        'model',
        'api_key',
        'base_url',
        'instruksi_persona',
        'ambang_keyakinan',
        'eskalasi_pelanggan_marah',
        'kata_kunci_eskalasi',
        'jeda_min',
        'jeda_max',
        'batas_pesan_harian',
        'jam_mulai',
        'jam_selesai',
        'variasi_kalimat',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'ambang_keyakinan' => 'decimal:2',
            'eskalasi_pelanggan_marah' => 'boolean',
            'variasi_kalimat' => 'boolean',
        ];
    }
}
