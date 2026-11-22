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
        'entitas',     // nama tabel yang terdampak
        'entitas_id',  // id baris yang terdampak
        'detail',      // detail perubahan (JSON)
        'alamat_ip',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mencatat satu aksi ke log audit.
     *
     * @param  string|null  $entitas  nama tabel yang terdampak, mis. "nomor_whatsapp"
     * @param  array<string, mixed>  $detail  tambahan untuk kolom detail (JSON)
     */
    public static function catat(
        string $aksi,
        ?string $keterangan = null,
        ?string $entitas = null,
        ?int $userId = null,
        ?int $entitasId = null,
        array $detail = []
    ): self {
        $detail = array_filter(
            ['keterangan' => $keterangan] + $detail,
            fn ($nilai) => $nilai !== null && $nilai !== ''
        );

        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'detail' => $detail ?: null,
            'alamat_ip' => request()?->ip(),
        ]);
    }
}
