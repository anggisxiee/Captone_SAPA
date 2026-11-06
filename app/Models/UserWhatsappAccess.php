<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWhatsappAccess extends Model
{
    protected $table = 'user_whatsapp_access';

    protected $fillable = [
        'user_id',
        'nomor_whatsapp_id',
        'hak_akses',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(
            NomorWhatsapp::class,
            'nomor_whatsapp_id'
        );
    }
}
