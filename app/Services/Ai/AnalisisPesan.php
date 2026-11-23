<?php

namespace App\Services\Ai;

/**
 * Pemeriksaan pesan berbasis aturan: kata kunci eskalasi dan nada marah.
 * Gratis, deterministik, dan dapat dijelaskan; tidak memanggil penyedia AI.
 */
class AnalisisPesan
{
    /** Kata/frasa beserta bobotnya. Nada marah bila total skor >= AMBANG_MARAH. */
    private const LEKSIKON = [
        // kasar atau ancaman (2)
        'penipu' => 2, 'menipu' => 2, 'tipu' => 2, 'bodoh' => 2, 'goblok' => 2, 'tolol' => 2, 'sampah' => 2,
        'sialan' => 2, 'brengsek' => 2, 'bangsat' => 2, 'anjing' => 2, 'somasi' => 2, 'viralkan' => 2,
        'laporkan' => 2, 'saya laporkan' => 2,
        // kecewa atau kesal (1)
        'kecewa' => 1, 'mengecewakan' => 1, 'marah' => 1, 'kesal' => 1, 'dongkol' => 1, 'muak' => 1,
        'parah' => 1, 'payah' => 1, 'ngawur' => 1, 'asal-asalan' => 1, 'tidak profesional' => 1,
        'gak profesional' => 1, 'lambat banget' => 1, 'lama banget' => 1, 'tidak becus' => 1,
    ];

    public const AMBANG_MARAH = 2;

    /**
     * Kata kunci eskalasi pertama yang muncul pada pesan, atau null.
     *
     * @param  string  $daftar  dipisah koma, titik koma, atau baris baru (dari Pengaturan AI)
     */
    public function kataKunci(string $pesan, string $daftar): ?string
    {
        $pesan = mb_strtolower($pesan);

        foreach (preg_split('/[,;\n]+/', $daftar) ?: [] as $kata) {
            $kata = mb_strtolower(trim($kata));

            if ($kata !== '' && $this->memuat($pesan, $kata)) {
                return $kata;
            }
        }

        return null;
    }

    /**
     * @return array{marah: bool, skor: int, pemicu: array<int, string>}
     */
    public function nadaMarah(string $pesan): array
    {
        $kecil = mb_strtolower($pesan);
        $skor = 0;
        $pemicu = [];

        foreach (self::LEKSIKON as $kata => $bobot) {
            if ($this->memuat($kecil, $kata)) {
                $skor += $bobot;
                $pemicu[] = $kata;
            }
        }

        if (preg_match('/!{3,}/', $pesan)) {
            $skor += 1;
            $pemicu[] = 'tanda seru berulang';
        }

        $huruf = preg_replace('/[^\p{L}]/u', '', $pesan) ?? '';
        if (mb_strlen($huruf) >= 12) {
            $kapital = mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $huruf) ?? '');
            if ($kapital / mb_strlen($huruf) >= 0.7) {
                $skor += 1;
                $pemicu[] = 'huruf kapital berlebihan';
            }
        }

        return ['marah' => $skor >= self::AMBANG_MARAH, 'skor' => $skor, 'pemicu' => $pemicu];
    }

    /** Kata utuh (bukan potongan dari kata lain): "tipu" tidak cocok dengan "stipulasi". */
    private function memuat(string $teks, string $kata): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kata, '/') . '(?![\p{L}\p{N}])/u', $teks);
    }
}
