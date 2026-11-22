<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Percakapan extends Model
{
    protected $table = 'percakapan';

    // Sama dengan default di database, agar model yang baru dibuat
    // langsung memiliki nilai yang benar.
    protected $attributes = [
        'status' => 'Baru',
        'belum_dibalas' => false,
    ];

    protected $fillable = [
        'nomor_whatsapp_id',
        'kontak_id',
        'status',
        'belum_dibalas',
        'pesan_terakhir_pada',
    ];

    protected function casts(): array
    {
        return [
            'belum_dibalas' => 'boolean',
            'pesan_terakhir_pada' => 'datetime',
        ];
    }

    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(NomorWhatsapp::class, 'nomor_whatsapp_id');
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(Kontak::class, 'kontak_id');
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(PesanWhatsapp::class, 'percakapan_id');
    }

    /**
     * Mencari atau membuat kontak dan percakapan untuk satu nomor
     * WhatsApp dan nomor telepon pelanggan.
     */
    public static function untuk(
        NomorWhatsapp $nomor,
        string $telepon,
        string $nama
    ): self {
        $kontak = Kontak::firstOrCreate(
            ['nomor_telepon' => $telepon],
            ['nama' => $nama]
        );

        return static::firstOrCreate([
            'nomor_whatsapp_id' => $nomor->id,
            'kontak_id' => $kontak->id,
        ]);
    }
}
