<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;

/**
 * Klien simulasi untuk tes dan pengembangan tanpa kunci. Jawaban dapat diprogram:
 *
 *   FakeAiClient::antre('jawaban pertama', '{"maksud":"tanya_biaya"}');
 *   FakeAiClient::gagal(new AiTidakTersediaException('...'));
 *
 * Tanpa antrean, jawabannya deterministik dan mengulang pesan pengguna terakhir.
 */
class FakeAiClient implements AiClient
{
    /** @var array<int, string> */
    private static array $antrean = [];

    private static ?AiException $galat = null;

    /** @var array<int, array<int, array{role: string, content: string}>> */
    public static array $riwayat = [];

    public static function antre(string ...$jawaban): void
    {
        array_push(self::$antrean, ...$jawaban);
    }

    public static function gagal(?AiException $galat): void
    {
        self::$galat = $galat;
    }

    public static function reset(): void
    {
        self::$antrean = [];
        self::$galat = null;
        self::$riwayat = [];
    }

    public function chat(array $pesan, array $opsi = []): HasilAi
    {
        self::$riwayat[] = $pesan;

        if (self::$galat) {
            throw self::$galat;
        }

        $terakhir = '';
        foreach (array_reverse($pesan) as $p) {
            if (($p['role'] ?? '') === 'user') {
                $terakhir = (string) $p['content'];
                break;
            }
        }

        $teks = array_shift(self::$antrean) ?? '[simulasi] ' . $terakhir;

        return new HasilAi($teks, 'fake', max(1, intdiv(strlen($terakhir), 4)), max(1, intdiv(strlen($teks), 4)));
    }

    public function uji(): HasilUji
    {
        if (self::$galat) {
            throw self::$galat;
        }

        return new HasilUji('fake', 0);
    }

    public function saldo(): ?array
    {
        return null;
    }
}
