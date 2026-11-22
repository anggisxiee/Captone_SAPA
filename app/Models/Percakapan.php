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
        'penangan' => 'AI',
        'belum_dibaca' => 0,
    ];

    protected $fillable = [
        'nomor_whatsapp_id',
        'kontak_id',
        'agen_id',
        'status',
        'penangan',
        'belum_dibaca',
        'waktu_balas_pertama',
        'pesan_terakhir_pada',
    ];

    protected function casts(): array
    {
        return [
            'belum_dibaca' => 'integer',
            'waktu_balas_pertama' => 'integer',
            'pesan_terakhir_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // penangan (AI/agen) selalu mengikuti status, sehingga keduanya tidak bisa
        // saling bertentangan. "Selesai" tidak mengubah penangan terakhir.
        static::saving(function (Percakapan $p) {
            if (!$p->isDirty('status')) {
                return;
            }

            if ($p->status === 'Ditangani Agen') {
                $p->penangan = 'agen';
            } elseif (in_array($p->status, ['Baru', 'Ditangani AI'], true)) {
                $p->penangan = 'AI';
            }
        });
    }

    /** Ada pesan pelanggan yang belum dibalas. */
    public function getBelumDibalasAttribute(): bool
    {
        return $this->belum_dibaca > 0;
    }

    public function nomorWhatsapp(): BelongsTo
    {
        return $this->belongsTo(NomorWhatsapp::class, 'nomor_whatsapp_id');
    }

    public function kontak(): BelongsTo
    {
        return $this->belongsTo(Kontak::class, 'kontak_id');
    }

    public function agen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agen_id');
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(PesanWhatsapp::class, 'percakapan_id');
    }

    /**
     * Mencatat waktu balas pertama (detik) sejak pesan pelanggan pertama.
     * Hanya diisi sekali.
     */
    public function catatBalasanPertama(): void
    {
        if ($this->waktu_balas_pertama !== null) {
            return;
        }

        $pertamaMasuk = $this->pesan()->where('arah', 'masuk')->orderBy('id')->first();
        if (!$pertamaMasuk) {
            return;
        }

        $this->waktu_balas_pertama = (int) max(0, $pertamaMasuk->created_at->diffInSeconds(now(), true));
    }

    /**
     * Mencari atau membuat kontak dan percakapan untuk satu nomor
     * WhatsApp dan nomor telepon pelanggan.
     */
    public static function untuk(
        NomorWhatsapp $nomor,
        string $telepon,
        ?string $nama = null
    ): self {
        $kontak = Kontak::firstOrCreate(
            ['nomor_telepon' => $telepon],
            ['nama' => $nama]
        );

        // Nama dari WhatsApp bisa baru diketahui pada pesan berikutnya.
        if (!$kontak->nama && $nama) {
            $kontak->update(['nama' => $nama]);
        }

        return static::firstOrCreate([
            'nomor_whatsapp_id' => $nomor->id,
            'kontak_id' => $kontak->id,
        ]);
    }
}
