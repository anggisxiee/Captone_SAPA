<?php

namespace App\Services\WhatsApp;

use App\Exceptions\GagalKirimPesanException;
use App\Models\NomorWhatsapp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gateway simulasi: tidak terhubung ke WhatsApp. Pesan hanya dicatat
 * di log. Untuk menguji jalur gagal kirim, sertakan teks "[gagal]"
 * pada isi pesan.
 */
class FakeWhatsAppGateway implements WhatsAppGateway
{
    public function mintaQr(NomorWhatsapp $nomor): string
    {
        return 'SAPA-FAKE:' . $nomor->nomor . ':' . Str::random(24);
    }

    public function putuskan(NomorWhatsapp $nomor): void
    {
        Log::info('[WA fake] sesi diputus', ['nomor' => $nomor->nomor]);
    }

    public function kirim(NomorWhatsapp $nomor, string $telepon, string $isi): string
    {
        if (str_contains($isi, '[gagal]')) {
            throw new GagalKirimPesanException('Simulasi gagal kirim dari gateway uji.');
        }

        Log::info('[WA fake] pesan terkirim', [
            'dari' => $nomor->nomor,
            'ke' => $telepon,
            'isi' => $isi,
        ]);

        return 'fake-' . Str::uuid();
    }
}
