<?php

namespace App\Services\Ai;

use App\Jobs\KirimPesanKeluar;
use App\Models\AntreanPesan;
use App\Models\LogAudit;
use App\Models\PengaturanAI;
use App\Models\PesanWhatsapp;
use Illuminate\Support\Facades\DB;

/**
 * Menangani satu pesan pelanggan dengan mesin jawaban (F04/F05), sesuai mode AI nomor:
 *  - mati     : diabaikan, agen yang menangani;
 *  - draf     : keputusan disimpan sebagai saran (pesan "draf"), agen yang mengirim;
 *  - otomatis : jawaban/eskalasi dikirim sendiri. Bila daftar nomor uji terisi, hanya
 *               pelanggan di daftar itu yang dibalas otomatis; lainnya mendapat saran.
 */
class PenangananAi
{
    public const HASIL_DIABAIKAN = 'diabaikan';
    public const HASIL_DRAF = 'draf';
    public const HASIL_KIRIM = 'kirim';
    public const HASIL_ESKALASI = 'eskalasi';
    public const HASIL_BATAS = 'batas';

    public function __construct(private readonly MesinJawaban $mesin)
    {
    }

    public function proses(PesanWhatsapp $pesan): string
    {
        $pesan->loadMissing('percakapan.nomorWhatsapp', 'percakapan.kontak');
        $percakapan = $pesan->percakapan;
        $nomor = $percakapan->nomorWhatsapp;

        if ($pesan->arah !== 'masuk' || $nomor->mode_ai === 'mati') {
            return self::HASIL_DIABAIKAN;
        }

        // BR08: setelah eskalasi atau diambil alih agen, AI berhenti sampai "Kembalikan ke AI".
        if ($percakapan->penangan !== 'AI' || $percakapan->status === 'Selesai') {
            return self::HASIL_DIABAIKAN;
        }

        // Hanya pesan pelanggan terbaru yang dijawab; pesan sebelumnya sudah tercakup.
        $terbaru = $percakapan->pesan()->where('arah', 'masuk')->max('id');
        if ((int) $terbaru !== (int) $pesan->id) {
            return self::HASIL_DIABAIKAN;
        }

        // Idempoten: job yang diulang tidak membuat keputusan ganda.
        $sudah = PesanWhatsapp::where('percakapan_id', $percakapan->id)
            ->where('jenis_pengirim', 'ai')
            ->whereRaw("json_extract(meta_ai, '$.untuk_pesan_id') = ?", [$pesan->id])
            ->exists();
        if ($sudah) {
            return self::HASIL_DIABAIKAN;
        }

        $pengaturan = PengaturanAI::find(1);
        $otomatis = $nomor->mode_ai === 'otomatis' && $this->diizinkanOtomatis($pengaturan, $percakapan->kontak->nomor_telepon);

        if ($otomatis && $this->melewatiBatas($percakapan->id)) {
            $this->serahkanKeAgen($percakapan, $pesan);

            return self::HASIL_BATAS;
        }

        $keputusan = $this->mesin->putuskan($pesan->isi, $pengaturan);
        $jejak = ['untuk_pesan_id' => $pesan->id, 'mode_nomor' => $otomatis ? 'otomatis' : 'draf'] + $keputusan->keJejak();
        $teks = $keputusan->eskalasi() ? MesinJawaban::PESAN_ESKALASI : (string) $keputusan->jawaban;

        if (!$otomatis) {
            PesanWhatsapp::create([
                'percakapan_id' => $percakapan->id,
                'arah' => 'keluar',
                'jenis_pengirim' => 'ai',
                'isi' => $teks,
                'status' => 'draf',
                'meta_ai' => $jejak,
            ]);

            return self::HASIL_DRAF;
        }

        $antrean = DB::transaction(function () use ($percakapan, $nomor, $keputusan, $teks, $jejak, $pengaturan) {
            $percakapan->catatBalasanPertama();

            $perubahan = ['pesan_terakhir_pada' => now()];
            if ($keputusan->eskalasi()) {
                // belum_dibaca tetap: agen masih perlu menanggapi.
                $perubahan += ['status' => 'Ditangani Agen', 'agen_id' => null];
            } else {
                $perubahan += ['status' => 'Ditangani AI', 'belum_dibaca' => 0];
            }
            $percakapan->update($perubahan);

            $pesanKeluar = PesanWhatsapp::create([
                'percakapan_id' => $percakapan->id,
                'arah' => 'keluar',
                'jenis_pengirim' => 'ai',
                'isi' => $teks,
                'status' => 'menunggu',
                'meta_ai' => $jejak,
            ]);

            LogAudit::catat(
                $keputusan->eskalasi() ? 'Eskalasi AI' : 'Balasan AI',
                "Percakapan #{$percakapan->id}: " . ($keputusan->eskalasi() ? ($keputusan->teksAlasan() ?? 'eskalasi') : 'jawaban dikirim otomatis'),
                'percakapan', null, $percakapan->id,
                ['keputusan' => $keputusan->keputusan, 'alasan' => $keputusan->alasan]
            );

            return AntreanPesan::create([
                'nomor_whatsapp_id' => $nomor->id,
                'pesan_whatsapp_id' => $pesanKeluar->id,
                'pengirim' => $nomor->label,
                'penerima' => $percakapan->kontak->nomor_telepon,
                'isi_pesan' => $teks,
                'skor_risiko' => 0,
                'keputusan' => 'Kirim',
                'percobaan' => 0,
                'maks_percobaan' => 3,
                'jadwal_kirim' => now(),
            ]);
        });

        $min = max(1, (int) ($pengaturan?->jeda_min ?? 3));
        $maks = max($min, (int) ($pengaturan?->jeda_max ?? 8));
        KirimPesanKeluar::dispatch($antrean->id)->delay(now()->addSeconds(random_int($min, $maks)));

        return $keputusan->eskalasi() ? self::HASIL_ESKALASI : self::HASIL_KIRIM;
    }

    /** Daftar nomor uji kosong = tanpa pembatasan. */
    private function diizinkanOtomatis(?PengaturanAI $pengaturan, string $telepon): bool
    {
        $daftar = $pengaturan?->daftarNomorUji() ?? [];

        return $daftar === [] || in_array($telepon, $daftar, true);
    }

    private function melewatiBatas(int $percakapanId): bool
    {
        $jumlah = PesanWhatsapp::where('percakapan_id', $percakapanId)
            ->where('jenis_pengirim', 'ai')
            ->where('status', '!=', 'draf')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $jumlah >= (int) config('ai.maks_balasan_per_jam');
    }

    /** Pengaman: tidak membalas pelanggan, tetapi alasannya tampil di Kotak Masuk sebagai saran. */
    private function serahkanKeAgen($percakapan, PesanWhatsapp $pesan): void
    {
        $percakapan->update(['status' => 'Ditangani Agen', 'agen_id' => null]);

        PesanWhatsapp::create([
            'percakapan_id' => $percakapan->id,
            'arah' => 'keluar',
            'jenis_pengirim' => 'ai',
            'isi' => MesinJawaban::PESAN_ESKALASI,
            'status' => 'draf',
            'meta_ai' => ['untuk_pesan_id' => $pesan->id, 'keputusan' => KeputusanAi::ESKALASI, 'alasan' => 'batas_balasan', 'mode' => 'aturan', 'maksud' => null, 'keyakinan' => 0.0, 'ambang' => 0.0, 'sumber' => []],
        ]);

        LogAudit::catat('Eskalasi AI', "Percakapan #{$percakapan->id}: batas balasan otomatis per jam tercapai.", 'percakapan', null, $percakapan->id, ['alasan' => 'batas_balasan']);
    }
}
