<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasProspek extends Model
{
    protected $table = 'aktivitas_prospek';

    protected $fillable = ['prospek_id', 'user_id', 'jenis', 'catatan', 'waktu'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function prospek(): BelongsTo
    {
        return $this->belongsTo(Prospek::class, 'prospek_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
