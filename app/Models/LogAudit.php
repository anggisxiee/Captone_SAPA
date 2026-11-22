<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAudit extends Model
{
    protected $table = 'log_audit';

    protected $fillable = [
        'user_id',
        'aksi',
        'objek',
        'keterangan',
        'alamat_ip',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mencatat satu aksi ke log audit.
     */
    public static function catat(
        string $aksi,
        ?string $keterangan = null,
        ?string $objek = null,
        ?int $userId = null
    ): self {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi,
            'objek' => $objek,
            'keterangan' => $keterangan,
            'alamat_ip' => request()?->ip(),
        ]);
    }
}
