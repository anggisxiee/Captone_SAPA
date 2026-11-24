<?php

namespace App\Console\Commands;

use App\Exceptions\AiTidakTersediaException;
use App\Models\PengaturanAI;
use App\Services\Ai\FakeAiClient;
use App\Services\Ai\KeputusanAi;
use App\Services\Ai\MesinJawaban;
use Illuminate\Console\Command;

/**
 * Mengukur mesin jawaban terhadap set pertanyaan uji (database/kalibrasi/pertanyaan.php):
 * ketepatan jawaban, eskalasi berlebih, dan terutama halusinasi (menjawab pertanyaan yang
 * seharusnya diteruskan ke agen). Hasilnya dasar penyetelan ambang relevansi dan keyakinan.
 */
class AiKalibrasi extends Command
{
    protected $signature = 'ai:kalibrasi
        {--tanpa-ai : Paksa mode aturan bawaan (tanpa memanggil penyedia AI)}
        {--ambang-relevansi= : Timpa ambang relevansi artikel (0-1) untuk percobaan}
        {--ambang-keyakinan= : Timpa ambang keyakinan (0-1) untuk percobaan}
        {--simpan= : Simpan laporan JSON ke berkas ini}';

    protected $description = 'Menguji mesin jawaban dengan set pertanyaan (akurasi, eskalasi berlebih, halusinasi)';

    public function handle(MesinJawaban $mesin): int
    {
        $soal = require database_path('kalibrasi/pertanyaan.php');

        if (($r = $this->option('ambang-relevansi')) !== null) {
            config(['ai.ambang_relevansi' => (float) $r]);
        }
        $pengaturan = PengaturanAI::find(1) ?? new PengaturanAI();
        if (($k = $this->option('ambang-keyakinan')) !== null) {
            $pengaturan->ambang_keyakinan = (float) $k;
        }

        $klien = null;
        if ($this->option('tanpa-ai')) {
            FakeAiClient::reset();
            FakeAiClient::gagal(new AiTidakTersediaException('mode aturan dipaksa'));
            $klien = new FakeAiClient();
        }

        $this->line(sprintf(
            'Ambang relevansi %.2f, ambang keyakinan %.2f, %s, %d pertanyaan',
            config('ai.ambang_relevansi'),
            $pengaturan->ambang_keyakinan ?? 0.62,
            $klien ? 'mode aturan' : 'penyedia ' . config('ai.driver'),
            count($soal)
        ));

        $hasil = [];
        foreach ($soal as $i => $s) {
            $k = $mesin->putuskan($s['pesan'], $pengaturan, $klien);
            $hasil[] = $this->nilai($i + 1, $s, $k);
        }

        $this->laporkan($hasil);

        if ($jalur = $this->option('simpan')) {
            file_put_contents($jalur, json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("Laporan disimpan: {$jalur}");
        }

        // Halusinasi adalah kegagalan keras: tidak boleh ada.
        return collect($hasil)->contains('status', 'halusinasi') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function nilai(int $no, array $s, KeputusanAi $k): array
    {
        $jawaban = (string) $k->jawaban;
        $judul = array_column($k->sumber, 'judul');
        $dijawab = !$k->eskalasi();

        $status = 'benar';
        $catatan = '';

        if ($s['harapan'] === 'eskalasi') {
            if ($dijawab) {
                $status = 'halusinasi';
                $catatan = 'seharusnya eskalasi';
            }
        } elseif (!$dijawab) {
            $status = !empty($s['aman']) ? 'eskalasi_berlebih_aman' : 'eskalasi_berlebih';
            $catatan = $k->teksAlasan() ?? '';
        } elseif (!array_intersect((array) $s['artikel'], $judul)) {
            $status = 'sumber_salah';
            $catatan = 'sumber: ' . ($judul ? implode('; ', $judul) : '-');
        } elseif (isset($s['memuat']) && mb_stripos($jawaban, $s['memuat']) === false) {
            $status = 'kurang_setia';
            $catatan = 'tidak memuat "' . $s['memuat'] . '"';
        }

        // Teks yang dilarang berlaku untuk jawaban yang terkirim, apa pun harapannya.
        if ($dijawab && isset($s['tidak_memuat']) && mb_stripos($jawaban, $s['tidak_memuat']) !== false) {
            $status = 'halusinasi';
            $catatan = 'memuat "' . $s['tidak_memuat'] . '"';
        }

        return [
            'no' => $no, 'kategori' => $s['kategori'], 'pesan' => $s['pesan'], 'harapan' => $s['harapan'],
            'keputusan' => $k->keputusan, 'alasan' => $k->alasan, 'keyakinan' => $k->keyakinan, 'mode' => $k->mode,
            'skor_artikel' => $k->detail['artikel'][0]['skor'] ?? null, 'status' => $status, 'catatan' => $catatan,
        ];
    }

    private function laporkan(array $hasil): void
    {
        $koleksi = collect($hasil);
        $jawab = $koleksi->where('harapan', 'jawab');
        $eskalasi = $koleksi->where('harapan', 'eskalasi');

        $this->newLine();
        $this->table(['Kategori', 'Soal', 'Benar', 'Eskalasi berlebih', 'Sumber/isi salah', 'Halusinasi'],
            $koleksi->groupBy('kategori')->map(fn ($g, $kat) => [
                $kat, $g->count(), $g->where('status', 'benar')->count(),
                $g->whereIn('status', ['eskalasi_berlebih', 'eskalasi_berlebih_aman'])->count(),
                $g->whereIn('status', ['sumber_salah', 'kurang_setia'])->count(),
                $g->where('status', 'halusinasi')->count(),
            ])->values()->all());

        $benar = $koleksi->where('status', 'benar')->count() + $koleksi->where('status', 'eskalasi_berlebih_aman')->count();
        $this->line(sprintf('Akurasi keseluruhan : %d/%d (%.0f%%)', $benar, $koleksi->count(), 100 * $benar / max(1, $koleksi->count())));
        $this->line(sprintf('Jawaban benar       : %d/%d dari pertanyaan yang seharusnya dijawab', $jawab->where('status', 'benar')->count(), $jawab->count()));
        $this->line(sprintf('Eskalasi berlebih   : %d (aman: %d)', $jawab->whereIn('status', ['eskalasi_berlebih', 'eskalasi_berlebih_aman'])->count(), $jawab->where('status', 'eskalasi_berlebih_aman')->count()));
        $halusinasi = $koleksi->where('status', 'halusinasi')->count();
        $this->line(($halusinasi ? '<error>' : '<info>') . sprintf('Halusinasi         : %d/%d pertanyaan yang seharusnya dieskalasi (target 0)', $halusinasi, $eskalasi->count()) . ($halusinasi ? '</error>' : '</info>'));

        $masalah = $koleksi->whereNotIn('status', ['benar']);
        if ($masalah->isNotEmpty()) {
            $this->newLine();
            $this->line('Perlu ditinjau:');
            $this->table(['#', 'Status', 'Keyakinan', 'Skor art.', 'Pesan', 'Catatan'], $masalah->map(fn ($h) => [
                $h['no'], $h['status'], $h['keyakinan'], $h['skor_artikel'] ?? '-', mb_substr($h['pesan'], 0, 55), $h['catatan'],
            ])->values()->all());
        }
    }
}
