<?php

namespace App\Services\Ai;

/**
 * Hasil satu keputusan mesin jawaban: kirim jawaban atau eskalasi ke agen,
 * beserta jejak yang dapat diperiksa (F13 "Uji Keputusan" dan kolom meta_ai).
 */
final class KeputusanAi
{
    public const KIRIM = 'kirim';
    public const ESKALASI = 'eskalasi';

    public const ALASAN = [
        'kata_kunci' => 'Pesan memuat kata kunci eskalasi',
        'nada_marah' => 'Nada pesan terdeteksi marah',
        'tidak_ada_artikel' => 'Tidak ada artikel relevan yang menjawab pertanyaan',
        'keyakinan_rendah' => 'Keyakinan jawaban di bawah ambang',
    ];

    /**
     * @param  array<int, array{kode: string, judul: string, skor: float}>  $sumber  artikel yang dipakai jawaban
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public readonly string $keputusan,
        public readonly ?string $alasan,
        public readonly ?string $jawaban,
        public readonly array $sumber,
        public readonly float $keyakinan,
        public readonly float $ambang,
        public readonly string $mode,
        public readonly ?string $maksud,
        public readonly array $detail,
    ) {
    }

    public function eskalasi(): bool
    {
        return $this->keputusan === self::ESKALASI;
    }

    public function teksAlasan(): ?string
    {
        return $this->alasan ? (self::ALASAN[$this->alasan] ?? $this->alasan) : null;
    }

    /** Bentuk ringkas untuk kolom pesan_whatsapp.meta_ai. */
    public function keJejak(): array
    {
        return [
            'keputusan' => $this->keputusan,
            'alasan' => $this->alasan,
            'mode' => $this->mode,
            'maksud' => $this->maksud,
            'keyakinan' => $this->keyakinan,
            'ambang' => $this->ambang,
            'sumber' => $this->sumber,
        ] + $this->detail;
    }
}
