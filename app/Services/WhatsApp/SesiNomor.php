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

        LogAudit::catat('Mulai tautan nomor', "QR dibuat untuk {$nomor->nama}", 'Nomor WhatsApp');

        return $nomor;
    }

    public function tandaiTersambung(NomorWhatsapp $nomor): void
    {
        $nomor->update([
            'status_sesi' => 'Tersambung',
            'qr_token' => null,
            'qr_kedaluwarsa_pada' => null,
            'terakhir_aktif_pada' => now(),
        ]);

        LogAudit::catat('Nomor tersambung', "{$nomor->nama} berhasil tertaut", 'Nomor WhatsApp');
    }

    public function tandaiTerputus(NomorWhatsapp $nomor): void
    {
        $nomor->update([
            'status_sesi' => 'Terputus',
            'qr_token' => null,
            'qr_kedaluwarsa_pada' => null,
        ]);

        LogAudit::catat('Nomor terputus', "Sesi {$nomor->nama} berakhir", 'Nomor WhatsApp');
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
            'deskripsi' => "Penautan {$nomor->nama} gagal." . ($alasan ? " {$alasan}" : ''),
            'status' => 'Terbuka',
        ]);

        LogAudit::catat('Nomor gagal tertaut', "Penautan {$nomor->nama} gagal", 'Nomor WhatsApp');
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
