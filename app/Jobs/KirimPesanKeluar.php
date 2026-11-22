<?php

namespace App\Jobs;

use App\Exceptions\GagalKirimPesanException;
use App\Models\AntreanPesan;
use App\Models\InsidenRisiko;
use App\Models\RiwayatAntrean;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mengirim satu pesan dari antrean_pesan melalui gateway WhatsApp.
 *
 * Penilaian anti-ban (jeda acak, batas harian, jam kirim) dikerjakan
 * pada Sprint 3; saat ini setiap pesan berkeputusan "Kirim" langsung
 * dikirim, dan kegagalan dicoba ulang hingga maks_percobaan.
 */
class KirimPesanKeluar implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $antreanPesanId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->antreanPesanId;
    }

    public function handle(WhatsAppGateway $gateway): void
    {
        $antrean = AntreanPesan::with('nomorWhatsapp')->find($this->antreanPesanId);

        // Hanya pesan yang masih menunggu dikirim.
        if (!$antrean || $antrean->keputusan !== 'Kirim') {
            return;
        }

        $nomor = $antrean->nomorWhatsapp;

        if (!$antrean->penerima_telepon) {
            $this->tandaiGagal($antrean, 'Nomor telepon penerima tidak diketahui.');

            return;
        }

        if (!$nomor || !$nomor->dapatMenerimaDanMengirim()) {
            $this->gagalSekali($antrean, 'Nomor WhatsApp pengirim tidak tersambung.');

            return;
        }

        try {
            $gateway->kirim($nomor, $antrean->penerima_telepon, $antrean->isi_pesan);
        } catch (GagalKirimPesanException $e) {
            $this->gagalSekali($antrean, $e->getMessage());

            return;
        }

        $antrean->update([
            'keputusan' => 'Terkirim',
            'percobaan' => $antrean->percobaan + 1,
            'terkirim_pada' => now(),
            'alasan_penahanan' => null,
        ]);

        $nomor->update(['terakhir_aktif_pada' => now()]);

        $this->catatRiwayat($antrean, 'Kirim', 'Terkirim', 'Pesan terkirim melalui gateway.');
    }

    /**
     * Mencatat satu kegagalan; mencoba lagi atau menyerah.
     */
    private function gagalSekali(AntreanPesan $antrean, string $alasan): void
    {
        $percobaan = $antrean->percobaan + 1;

        if ($percobaan >= $antrean->maks_percobaan) {
            $antrean->update(['percobaan' => $percobaan]);
            $this->tandaiGagal($antrean, $alasan);

            return;
        }

        $antrean->update([
            'percobaan' => $percobaan,
            'alasan_penahanan' => $alasan,
        ]);

        self::dispatch($antrean->id)->delay(now()->addSeconds(config('whatsapp.jeda_ulang')));
    }

    private function tandaiGagal(AntreanPesan $antrean, string $alasan): void
    {
        $antrean->update([
            'keputusan' => 'Gagal',
            'alasan_penahanan' => $alasan,
        ]);

        $this->catatRiwayat($antrean, 'Kirim', 'Gagal', $alasan);

        InsidenRisiko::create([
            'nomor_whatsapp_id' => $antrean->nomor_whatsapp_id,
            'jenis_insiden' => 'Pesan gagal terkirim',
            'tingkat_risiko' => 'Sedang',
            'deskripsi' => "Pesan ke {$antrean->penerima} gagal terkirim. {$alasan}",
            'status' => 'Terbuka',
        ]);
    }

    private function catatRiwayat(AntreanPesan $antrean, string $dari, string $ke, string $keterangan): void
    {
        RiwayatAntrean::create([
            'antrean_pesan_id' => $antrean->id,
            'status_sebelumnya' => $dari,
            'status_baru' => $ke,
            'keterangan' => $keterangan,
            'diubah_pada' => now(),
        ]);
    }
}
