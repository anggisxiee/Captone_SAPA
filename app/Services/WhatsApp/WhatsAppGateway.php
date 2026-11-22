<?php

namespace App\Services\WhatsApp;

use App\Exceptions\GagalKirimPesanException;
use App\Models\NomorWhatsapp;

/**
 * Kontrak antara SAPA dan layanan penghubung WhatsApp.
 * Pesan masuk dan perubahan status sesi tidak lewat sini, melainkan
 * diterima gateway melalui webhook (lihat routes/api.php).
 */
interface WhatsAppGateway
{
    /**
     * Meminta gateway membuat sesi dan mengembalikan isi kode QR
     * yang harus dipindai di aplikasi WhatsApp.
     */
    public function mintaQr(NomorWhatsapp $nomor): string;

    /**
     * Mengakhiri sesi WhatsApp nomor tersebut.
     */
    public function putuskan(NomorWhatsapp $nomor): void;

    /**
     * Mengirim pesan teks. Mengembalikan ID pesan dari gateway.
     *
     * @throws GagalKirimPesanException
     */
    public function kirim(NomorWhatsapp $nomor, string $telepon, string $isi): string;
}
