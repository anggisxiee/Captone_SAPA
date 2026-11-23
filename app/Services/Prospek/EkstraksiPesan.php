<?php

namespace App\Services\Prospek;

/**
 * Klasifikasi maksud dan ekstraksi entitas (nama, jenjang, kelas, mata pelajaran) dari
 * satu pesan pelanggan (F03). Berbasis aturan: gratis, deterministik, dan isi pesan tidak
 * dikirim ke penyedia AI. Hasil yang tidak yakin dikosongkan, bukan ditebak.
 */
class EkstraksiPesan
{
    private const MAKSUD = [
        'keluhan' => ['komplain', 'kecewa', 'refund', 'marah', 'keluhan'],
        'tanya_biaya' => ['biaya', 'harga', 'tarif', 'bayar', 'spp', 'cicil', 'angsur', 'diskon', 'promo', 'potongan'],
        'tanya_pendaftaran' => ['daftar', 'pendaftaran', 'registrasi', 'trial', 'percobaan', 'tryout', 'try out'],
        'tanya_jadwal' => ['jadwal', 'jam', 'hari', 'kapan', 'pertemuan'],
        'tanya_fasilitas' => ['fasilitas', 'ruang', 'wifi', 'wi-fi', 'ac', 'lokasi', 'alamat'],
        'tanya_program' => ['program', 'kelas', 'les', 'bimbel', 'bimbingan', 'intensif', 'utbk', 'snbt', 'brosur'],
    ];

    /** Nama baku => pola (huruf kecil, kata utuh). */
    private const MAPEL = [
        'Matematika' => 'matematika|mtk|math',
        'Fisika' => 'fisika',
        'Kimia' => 'kimia',
        'Biologi' => 'biologi',
        'Bahasa Inggris' => 'bahasa inggris|b\.? ?inggris|english',
        'Bahasa Indonesia' => 'bahasa indonesia|b\.? ?indonesia',
        'IPA' => 'ipa',
        'IPS' => 'ips',
        'Ekonomi' => 'ekonomi',
        'Sejarah' => 'sejarah',
        'Geografi' => 'geografi',
        'Sosiologi' => 'sosiologi',
    ];

    private const ROMAWI = ['vii' => 7, 'viii' => 8, 'ix' => 9, 'xii' => 12, 'xi' => 11, 'x' => 10, 'iv' => 4, 'vi' => 6, 'v' => 5, 'iii' => 3, 'ii' => 2, 'i' => 1];

    private const BUKAN_NAMA = ['kelas', 'kls', 'di', 'mau', 'ingin', 'untuk', 'dan', 'yang', 'saya', 'sekolah', 'kak', 'bu', 'pak', 'tanya', 'ya', 'nih', 'dari', 'sd', 'smp', 'sma', 'smk', 'bimbel', 'les', 'minta', 'mendaftar', 'daftar', 'adalah', 'ini', 'itu', 'dengan', 'kelasnya', 'umur', 'usia'];

    /**
     * @return array{maksud: string, nama: ?string, jenjang: ?string, kelas: ?string, mapel: ?string}
     */
    public function ekstrak(string $pesan): array
    {
        $teks = mb_strtolower(trim($pesan));

        [$jenjang, $kelas] = $this->jenjangDanKelas($teks);

        return [
            'maksud' => $this->maksud($teks),
            'nama' => $this->nama($pesan),
            'jenjang' => $jenjang,
            'kelas' => $kelas,
            'mapel' => $this->mapel($teks),
        ];
    }

    private function maksud(string $teks): string
    {
        foreach (self::MAKSUD as $maksud => $kata) {
            foreach ($kata as $k) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($k, '/') . '(?![\p{L}\p{N}])/u', $teks)) {
                    return $maksud;
                }
            }
        }

        return 'lainnya';
    }

    /** @return array{0: ?string, 1: ?string} */
    private function jenjangDanKelas(string $teks): array
    {
        $jenjang = null;
        if (preg_match('/(?<![\p{L}\p{N}\/])(sd|smp|sma|smk)n?(?![\p{L}\p{N}\/])/u', $teks, $m)) {
            $jenjang = strtoupper($m[1]);
        } elseif (preg_match('/(?<![\p{L}\p{N}])(utbk|snbt|sbmptn)(?![\p{L}\p{N}])/u', $teks)) {
            $jenjang = 'SMA';
        }

        $kelas = null;
        if (preg_match('/(?<![\p{L}\p{N}])(?:kelas|kls)\s*(\d{1,2}|[ivx]{1,4})(?![\p{L}\p{N}])/u', $teks, $m)) {
            $nilai = ctype_digit($m[1]) ? (int) $m[1] : (self::ROMAWI[$m[1]] ?? 0);
            if ($nilai >= 1 && $nilai <= 12) {
                $kelas = (string) $nilai;
                // Tanpa jenjang eksplisit, jenjang diturunkan dari nomor kelas.
                $jenjang ??= $nilai <= 6 ? 'SD' : ($nilai <= 9 ? 'SMP' : 'SMA');
            }
        }

        return [$jenjang, $kelas];
    }

    private function mapel(string $teks): ?string
    {
        $ditemukan = [];
        foreach (self::MAPEL as $baku => $pola) {
            if (preg_match('/(?<![\p{L}\p{N}])(?:' . $pola . ')(?![\p{L}\p{N}])/u', $teks, $m, PREG_OFFSET_CAPTURE)) {
                $ditemukan[$baku] = $m[0][1];
            }
        }
        asort($ditemukan);

        return $ditemukan ? implode(', ', array_keys($ditemukan)) : null;
    }

    private function nama(string $pesan): ?string
    {
        $pola = '/(?:nama(?:\s+(?:saya|anak(?:\s+saya)?|anaknya|putra\s+saya|putri\s+saya|siswa|adik))?\s*(?:adalah|:|=)?|saya\s+bernama|perkenalkan,?(?:\s+saya)?)\s+([\p{L}]{2,20}(?:\s+[\p{L}]{2,20}){0,2})/iu';

        if (!preg_match($pola, $pesan, $m)) {
            return null;
        }

        $kata = [];
        foreach (preg_split('/\s+/u', trim($m[1])) as $k) {
            if (in_array(mb_strtolower($k), self::BUKAN_NAMA, true)) {
                break;
            }
            $kata[] = mb_convert_case($k, MB_CASE_TITLE);
        }

        return $kata ? implode(' ', $kata) : null;
    }
}
