<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomorWhatsapp extends Model
{
    protected $table = 'nomor_whatsapp';

    protected $fillable = [
        'nama',
        'nomor',
        'status',
    ];

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
