<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Kolom yang dapat diisi.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * Kolom yang disembunyikan.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Konversi tipe data.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi ke tabel user_whatsapp_access.
     */
    public function aksesWhatsapp(): HasMany
    {
        return $this->hasMany(
            UserWhatsappAccess::class,
            'user_id'
        );
    }

    /**
     * Relasi pengguna dengan nomor WhatsApp.
     */
    public function nomorWhatsapp(): BelongsToMany
    {
        return $this->belongsToMany(
            NomorWhatsapp::class,
            'user_whatsapp_access',
            'user_id',
            'nomor_whatsapp_id'
        )
        ->withPivot('hak_akses')
        ->withTimestamps();
    }
}
