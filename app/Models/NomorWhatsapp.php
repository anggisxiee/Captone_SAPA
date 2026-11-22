<?php

namespace App\Models;

use App\Support\Telepon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomorWhatsapp extends Model
{
    protected $table = 'nomor_whatsapp';

    protected $fillable = [
        'nama',
        'nomor',
        'tujuan',
        'status',
        'status_sesi',
        'qr_token',
        'qr_kedaluwarsa_pada',
        'terakhir_aktif_pada',
    ];

    protected function casts(): array
    {
        return [
            'qr_kedaluwarsa_pada' => 'datetime',
            'terakhir_aktif_pada' => 'datetime',
        ];
    }

    /**
     * Nomor selalu disimpan dalam format 62xxxxxxxx.
     */
    public function setNomorAttribute(string $value): void
    {
        $this->attributes['nomor'] = Telepon::normalisasi($value);
    }

    /**
     * True bila sesi tersambung dan nomor tidak dinonaktifkan.
     */
    public function dapatMenerimaDanMengirim(): bool
    {
        return $this->status === 'Aktif' && $this->status_sesi === 'Tersambung';
    }

    public function qrKedaluwarsa(): bool
    {
        return $this->status_sesi === 'Menunggu QR'
            && $this->qr_kedaluwarsa_pada !== null
            && $this->qr_kedaluwarsa_pada->isPast();
    }

    public function aksesPengguna(): HasMany
    {
        return $this->hasMany(
            UserWhatsappAccess::class,
            'nomor_whatsapp_id'
        );
    }

    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_whatsapp_access',
            'nomor_whatsapp_id',
            'user_id'
        )->withPivot('hak_akses')->withTimestamps();
    }
}
