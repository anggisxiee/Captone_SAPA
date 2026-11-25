<?php

namespace App\Services\WhatsApp;

use App\Models\InsidenRisiko;
use App\Models\LogAudit;
use App\Models\NomorWhatsapp;

/**
 * Mengelola siklus sesi WhatsApp sebuah nomor. Dipakai oleh halaman
 * Nomor WhatsApp dan oleh webhook dari gateway, sehingga audit dan
 * pencatatan risiko ada di satu tempat.
 */
class SesiNomor
{
    public function __construct(private WhatsAppGateway $gateway)
    {
    }

    /**
     * Memulai (atau membuat ulang) penautan dan mengembalikan QR baru.
     */
    public function mulaiTautan(NomorWhatsapp $nomor): NomorWhatsapp
    {
        $nomor->update([
            'status_sesi' => 'Menunggu QR',
            'qr_token' => $this->gateway->mintaQr($nomor),
            'qr_kedaluwarsa_pada' => now()->addSeconds(config('whatsapp.qr_ttl')),
        ]);

        LogAudit::catat('Mulai tautan nomor', "QR dibuat untuk {$nomor->label}", 'nomor_whatsapp', null, $nomor->id);

        return $nomor;
    }

    /**
     * WhatsApp memutar kode QR sekitar tiap 20 detik; gateway meneruskan
     * kode terbarunya. Hanya diterima selama nomor memang menunggu QR dan
     * masa berlakunya belum habis.
     */
    public function perbaruiQr(NomorWhatsapp $nomor, string $qr): bool
    {
        if ($nomor->status_sesi !== 'Menunggu QR' || $nomor->qrKedaluwarsa()) {
            return false;
        }

        $nomor->update(['qr_token' => $qr]);

        return true;
    }

    public function tandaiTersambung(NomorWhatsapp $nomor): void
    {
        $sudahTersambung = $nomor->status_sesi === 'Tersambung';

        $nomor->update([
            'status_sesi' => 'Tersambung',
            'qr_token' => null,
            'qr_kedaluwarsa_pada' => null,
            'terakhir_aktif_pada' => now(),
        ]);

        // Gateway melaporkan "tersambung" lagi setiap sambungan terbuka ulang (sekitar tiap 15 menit);
        // hanya perpindahan dari status lain yang layak masuk log audit.
        if (!$sudahTersambung) {
            LogAudit::catat('Nomor tersambung', "{$nomor->label} berhasil tertaut", 'nomor_whatsapp', null, $nomor->id);
        }
    }

    public function tandaiTerputus(NomorWhatsapp $nomor): void
    {
        $nomor->update([
            'status_sesi' => 'Terputus',
            'qr_token' => null,
            'qr_kedaluwarsa_pada' => null,
        ]);

        LogAudit::catat('Nomor terputus', "Sesi {$nomor->label} berakhir", 'nomor_whatsapp', null, $nomor->id);
    }

    public function tandaiGagal(NomorWhatsapp $nomor, ?string $alasan = null): void
    {
        $nomor->update([
            'status_sesi' => 'Gagal tertaut',
            'qr_token' => null,
            'qr_kedaluwarsa_pada' => null,
        ]);

        InsidenRisiko::create([
            'nomor_whatsapp_id' => $nomor->id,
            'jenis_insiden' => 'Gagal tertaut',
            'tingkat_risiko' => 'Sedang',
            'deskripsi' => "Penautan {$nomor->label} gagal." . ($alasan ? " {$alasan}" : ''),
            'status' => 'Terbuka',
        ]);

        LogAudit::catat('Nomor gagal tertaut', "Penautan {$nomor->label} gagal", 'nomor_whatsapp', null, $nomor->id);
    }

    /**
     * Memutus sesi atas permintaan Administrator.
     */
    public function putuskan(NomorWhatsapp $nomor): void
    {
        $this->gateway->putuskan($nomor);
        $this->tandaiTerputus($nomor);
    }
}
