<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtikelPengetahuan extends Model
{
    protected $table = 'artikel_pengetahuan';

    /** Kategori menurut kamus data SRS, beserta nama tampilannya. */
    public const KATEGORI = [
        'program' => 'Program',
        'jadwal' => 'Jadwal',
        'biaya' => 'Biaya',
        'fasilitas' => 'Fasilitas',
        'pendaftaran' => 'Pendaftaran',
    ];

    protected $fillable = [
        'judul',
        'kategori',
        'isi',
        'label',   // kata kunci tambahan / sinonim agar artikel mudah ditemukan
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /** Kode tampilan, mis. KB-012. */
    public function getKodeAttribute(): string
    {
        return 'KB-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function getNamaKategoriAttribute(): string
    {
        return self::KATEGORI[$this->kategori] ?? ucfirst((string) $this->kategori);
    }
}
