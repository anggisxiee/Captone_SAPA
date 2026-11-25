<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kontak extends Model
{
    protected $table = 'kontak';

    protected $fillable = [
        'nama',
        'nomor_telepon',
        'jenjang',
        'kelas',
        'mapel',
        'kota',
        'sumber',
        'label',
    ];

    /** Nama untuk ditampilkan; nomor telepon bila nama belum diketahui. */
    public function getNamaTampilAttribute(): string
    {
        return $this->nama ?: $this->nomor_telepon;
    }

    public function prospek(): HasOne
    {
        return $this->hasOne(Prospek::class, 'kontak_id');
    }

    public function percakapan(): HasMany
    {
        return $this->hasMany(Percakapan::class, 'kontak_id');
    }
}
