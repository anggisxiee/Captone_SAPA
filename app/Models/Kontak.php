<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kontak extends Model
{
    protected $table = 'kontak';

    protected $fillable = [
        'nama',
        'nomor_telepon',
        'jenjang',
        'kelas',
        'mapel',
    ];

    public function percakapan(): HasMany
    {
        return $this->hasMany(Percakapan::class, 'kontak_id');
    }
}
