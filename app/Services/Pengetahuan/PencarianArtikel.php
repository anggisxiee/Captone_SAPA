<?php

namespace App\Services\Pengetahuan;

use App\Models\ArtikelPengetahuan;
use Illuminate\Support\Facades\DB;

/**
 * Pencarian artikel basis pengetahuan dengan SQLite FTS5 (peringkat bm25).
 *
 * Skor 0..1 yang ditampilkan dan dipakai terhadap ambang relevansi dapat dijelaskan:
 *   skor = 0,6 x cakupan + 0,4 x relatif
 *   cakupan : bagian istilah pertanyaan yang ditemukan pada artikel (0..1)
 *   relatif : bm25 artikel dibanding bm25 terbaik pada pencarian yang sama (0..1)
 * Cakupan mencegah artikel yang hanya "terbaik di antara yang buruk" lolos ambang.
 * Rumus ini heuristik awal; kalibrasi ambang dilakukan dengan artikel dan pertanyaan nyata.
 *
 * Keterbatasan yang sudah diukur:
 *  - Tidak ada stemming bahasa Indonesia ("daftar" tidak menemukan "pendaftaran").
 *    Kolom label pada artikel dipakai untuk menambah sinonim.
 *  - Pertanyaan yang menyentuh dua topik sekaligus membuat tiap artikel hanya menutup
 *    sebagian istilah, sehingga skornya rendah. Tahap jawaban AI perlu memilih artikel
 *    secara bertahap (istilah yang belum tertutup) sebelum memakai ambang.
 */
class PencarianArtikel
{
    private const STOPWORD = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'atau', 'adalah', 'apakah', 'apa', 'berapa',
        'bagaimana', 'gimana', 'kapan', 'dimana', 'siapa', 'kenapa', 'mengapa', 'ada', 'itu', 'ini', 'saya',
        'aku', 'kami', 'kita', 'mau', 'ingin', 'tanya', 'nanya', 'kak', 'kakak', 'bu', 'ibu', 'pak', 'bapak',
        'admin', 'min', 'dong', 'ya', 'yah', 'nya', 'bisa', 'boleh', 'sudah', 'belum', 'akan', 'pada', 'dalam',
        'tolong', 'mohon', 'halo', 'hai', 'selamat', 'pagi', 'siang', 'sore', 'malam', 'terima', 'kasih',
        'saja', 'juga', 'lagi', 'dulu', 'sih', 'kok', 'tentang', 'soal', 'info', 'informasi', 'cara',
    ];

    private const MAKS_ISTILAH = 12;

    /**
     * @return array<int, array{artikel: ArtikelPengetahuan, bm25: float, cakupan: float, skor: float}>
     */
    public function cari(string $pertanyaan, int $batas = 5, bool $hanyaAktif = true): array
    {
        $istilah = $this->istilah($pertanyaan);
        if (!$istilah) {
            return [];
        }

        // Setiap istilah hanya berisi huruf/angka, jadi aman dari sintaks FTS5.
        $cocok = implode(' OR ', array_map(fn ($t) => '"' . $t . '"*', $istilah));

        $baris = DB::select(
            'SELECT a.id AS id, bm25(artikel_pengetahuan_fts, 5.0, 1.0, 3.0) AS bm25
             FROM artikel_pengetahuan_fts
             JOIN artikel_pengetahuan a ON a.id = artikel_pengetahuan_fts.rowid
             WHERE artikel_pengetahuan_fts MATCH ?' . ($hanyaAktif ? ' AND a.aktif = 1' : '') . '
             ORDER BY bm25
             LIMIT ?',
            [$cocok, $batas]
        );

        if (!$baris) {
            return [];
        }

        $artikel = ArtikelPengetahuan::whereIn('id', array_column($baris, 'id'))->get()->keyBy('id');
        $terbaik = max(array_map(fn ($b) => abs((float) $b->bm25), $baris));

        $hasil = [];
        foreach ($baris as $b) {
            $a = $artikel[$b->id];
            $bm25 = abs((float) $b->bm25);
            $cakupan = $this->cakupan($istilah, $a);
            $relatif = $terbaik > 0 ? $bm25 / $terbaik : 1.0;

            $hasil[] = [
                'artikel' => $a,
                'bm25' => round($bm25, 4),
                'cakupan' => round($cakupan, 2),
                'skor' => round(0.6 * $cakupan + 0.4 * $relatif, 2),
            ];
        }

        // Urutan akhir mengikuti skor yang dijelaskan, bukan bm25 mentah.
        usort($hasil, fn ($x, $y) => [$y['skor'], $y['bm25']] <=> [$x['skor'], $x['bm25']]);

        return $hasil;
    }

    /**
     * Hasil yang boleh dipakai AI: skor minimal sama dengan ambang relevansi.
     *
     * @param  array<int, array{skor: float}>  $hasil
     */
    public function relevan(array $hasil, ?float $ambang = null): array
    {
        $ambang ??= (float) config('ai.ambang_relevansi');

        return array_values(array_filter($hasil, fn ($h) => $h['skor'] >= $ambang));
    }

    /** Istilah pencarian dari pertanyaan: huruf kecil, tanpa kata umum, unik, dibatasi. */
    public function istilah(string $pertanyaan): array
    {
        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($pertanyaan), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $istilah = [];
        foreach ($kata as $k) {
            $angka = ctype_digit($k);
            if ((!$angka && mb_strlen($k) < 2) || in_array($k, self::STOPWORD, true)) {
                continue;
            }
            $istilah[$k] = true;
        }

        // Kunci array numerik (mis. "8") otomatis menjadi integer; kembalikan ke string.
        return array_map('strval', array_slice(array_keys($istilah), 0, self::MAKS_ISTILAH));
    }

    /** Bagian istilah yang menjadi awalan suatu kata pada judul, isi, atau label artikel. */
    private function cakupan(array $istilah, ArtikelPengetahuan $a): float
    {
        $kata = preg_split(
            '/[^\p{L}\p{N}]+/u',
            mb_strtolower($a->judul . ' ' . $a->isi . ' ' . $a->label),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $ditemukan = 0;
        foreach ($istilah as $t) {
            foreach ($kata as $k) {
                if (str_starts_with($k, $t)) {
                    $ditemukan++;
                    break;
                }
            }
        }

        return $ditemukan / count($istilah);
    }
}
