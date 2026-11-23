<?php

namespace App\Services\Prospek;

use App\Models\Prospek;
use Illuminate\Support\Collection;

/**
 * Skor prioritas prospek 0..100 dari lima komponen berbobot (BR06). Seluruh perhitungan
 * deterministik dan dapat dijelaskan: tiap komponen menyimpan nilai, poin, dan alasannya
 * pada `rincian_skor` agar pengguna dapat melihat kenapa suatu prospek diprioritaskan.
 *
 * Bobot ada di config/prospek.php. Skor dihitung ulang saat data kontak, tahap, aktivitas,
 * atau pesan pelanggan berubah (lihat pemanggilnya).
 */
class SkorPrioritas
{
    private const LABEL = [
        'kelengkapan' => 'Kelengkapan data',
        'urgensi' => 'Urgensi',
        'minat' => 'Minat program',
        'responsivitas' => 'Responsivitas chat',
        'riwayat' => 'Riwayat interaksi',
    ];

    /** Seberapa kuat suatu maksud pesan menandakan niat mendaftar. */
    private const NILAI_MAKSUD = [
        'tanya_pendaftaran' => 1.0,
        'tanya_biaya' => 0.8,
        'tanya_jadwal' => 0.6,
        'tanya_program' => 0.5,
        'tanya_fasilitas' => 0.3,
    ];

    private const NILAI_TAHAP = ['baru' => 0.1, 'dihubungi' => 0.4, 'tertarik' => 0.7, 'penawaran' => 0.9, 'daftar' => 1.0, 'batal' => 0.0];

    private const MAKS_PESAN = 100;

    public function __construct(private readonly EkstraksiPesan $ekstraksi)
    {
    }

    /**
     * @return array{skor: int, rincian: array<int, array{kunci: string, label: string, bobot: float, nilai: float, poin: float, keterangan: string}>}
     */
    public function hitung(Prospek $prospek): array
    {
        $prospek->loadMissing('kontak');
        $kontak = $prospek->kontak;

        $masuk = collect();
        $keluar = collect();
        if ($kontak) {
            $idPercakapan = $kontak->percakapan()->pluck('id');
            $pesan = \App\Models\PesanWhatsapp::whereIn('percakapan_id', $idPercakapan)
                ->where('status', '!=', 'draf')->orderByDesc('id')->limit(self::MAKS_PESAN * 2)->get();
            $masuk = $pesan->where('arah', 'masuk')->take(self::MAKS_PESAN)->values();
            $keluar = $pesan->where('arah', 'keluar')->take(self::MAKS_PESAN)->values();
        }

        $komponen = [
            'kelengkapan' => $this->kelengkapan($kontak),
            'urgensi' => $this->urgensi($masuk),
            'minat' => $this->minat($masuk),
            'responsivitas' => $this->responsivitas($masuk, $keluar),
            'riwayat' => $this->riwayat($prospek),
        ];

        $bobot = array_map('floatval', (array) config('prospek.bobot'));
        $total = array_sum($bobot) ?: 1.0;

        $rincian = [];
        $skor = 0.0;
        foreach ($komponen as $kunci => [$nilai, $keterangan]) {
            $b = $bobot[$kunci] ?? 0.0;
            $poin = $nilai * $b / $total * 100;
            $skor += $poin;
            $rincian[] = [
                'kunci' => $kunci, 'label' => self::LABEL[$kunci], 'bobot' => $b,
                'nilai' => round($nilai, 2), 'poin' => round($poin, 1), 'keterangan' => $keterangan,
            ];
        }

        return ['skor' => (int) max(0, min(100, round($skor))), 'rincian' => $rincian];
    }

    /** Menghitung ulang dan menyimpan skor beserta rinciannya. */
    public function simpan(Prospek $prospek): Prospek
    {
        $hasil = $this->hitung($prospek);
        $prospek->update(['skor' => $hasil['skor'], 'rincian_skor' => $hasil['rincian']]);

        return $prospek;
    }

    /** @return array{0: float, 1: string} */
    private function kelengkapan(?\App\Models\Kontak $kontak): array
    {
        $kolom = [
            'nama' => $kontak && $kontak->nama && $kontak->nama !== $kontak->nomor_telepon,
            'jenjang' => (bool) $kontak?->jenjang,
            'kelas' => (bool) $kontak?->kelas,
            'mata pelajaran' => (bool) $kontak?->mapel,
            'sumber' => (bool) $kontak?->sumber,
        ];
        $kurang = array_keys(array_filter($kolom, fn ($ada) => !$ada));
        $terisi = count($kolom) - count($kurang);

        return [$terisi / count($kolom), "{$terisi} dari " . count($kolom) . ' data terisi' . ($kurang ? ' (belum: ' . implode(', ', $kurang) . ')' : '')];
    }

    /** @return array{0: float, 1: string} */
    private function urgensi(Collection $masuk): array
    {
        $kata = $masuk->flatMap(fn ($m) => $this->ekstraksi->urgensi($m->isi))->unique()->values();

        return match (true) {
            $kata->count() >= 2 => [1.0, 'Penanda mendesak: ' . $kata->take(4)->implode(', ')],
            $kata->count() === 1 => [0.5, 'Penanda mendesak: ' . $kata->first()],
            default => [0.0, 'Tidak ada penanda mendesak'],
        };
    }

    /** @return array{0: float, 1: string} */
    private function minat(Collection $masuk): array
    {
        $terbaik = 0.0;
        $maksud = null;
        foreach ($masuk as $m) {
            $kode = $this->ekstraksi->ekstrak($m->isi)['maksud'];
            $nilai = self::NILAI_MAKSUD[$kode] ?? 0.0;
            if ($nilai > $terbaik) {
                $terbaik = $nilai;
                $maksud = $kode;
            }
        }

        return [$terbaik, $maksud ? 'Menanyakan: ' . str_replace('tanya_', '', $maksud) : 'Belum menanyakan program, biaya, atau pendaftaran'];
    }

    /** @return array{0: float, 1: string} */
    private function responsivitas(Collection $masuk, Collection $keluar): array
    {
        $jumlah = min($masuk->count(), 4) / 4;

        // Balasan atas pesan kita: pelanggan menulis lagi dalam 24 jam setelahnya.
        $dibalas = $keluar->filter(fn ($k) => $masuk->contains(
            fn ($m) => $m->percakapan_id === $k->percakapan_id
                && $m->created_at > $k->created_at
                && $m->created_at <= $k->created_at->copy()->addDay()
        ))->count();
        $rasio = $keluar->count() ? $dibalas / $keluar->count() : 0.0;

        return [
            0.5 * $jumlah + 0.5 * $rasio,
            "{$masuk->count()} pesan pelanggan; " . ($keluar->count() ? "{$dibalas} dari {$keluar->count()} pesan kita dibalas dalam 24 jam" : 'belum ada pesan kita yang bisa dibalas'),
        ];
    }

    /** @return array{0: float, 1: string} */
    private function riwayat(Prospek $prospek): array
    {
        $aktivitas = $prospek->aktivitas()->count();
        $nilai = 0.7 * (self::NILAI_TAHAP[$prospek->tahap] ?? 0.0) + 0.3 * min($aktivitas, 3) / 3;

        return [$nilai, 'Tahap ' . $prospek->tahap . ", {$aktivitas} aktivitas tindak lanjut"];
    }
}
