<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;

/**
 * Kontrak antara SAPA dan penyedia AI. Semua fitur AI (jawaban, klasifikasi,
 * ekstraksi) memakai kontrak ini, sehingga penyedia dapat diganti tanpa mengubah
 * logika di atasnya.
 */
interface AiClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $pesan
     * @param  array{max_tokens?: int, temperature?: float, json?: bool}  $opsi
     *
     * @throws AiException (AiTidakTersediaException bila perlu beralih ke mode aturan bawaan)
     */
    public function chat(array $pesan, array $opsi = []): HasilAi;

    /**
     * Menguji koneksi dan kunci dengan permintaan sekecil mungkin.
     *
     * @throws AiException
     */
    public function uji(): HasilUji;

    /**
     * Sisa saldo penyedia bila didukung, selain itu null.
     *
     * @return array{sisa: float, anggaran: float, terpakai: float}|null
     */
    public function saldo(): ?array;
}
