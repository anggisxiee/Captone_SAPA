<?php

namespace App\Services\WhatsApp;

use App\Exceptions\GagalKirimPesanException;
use App\Exceptions\GatewayException;
use App\Exceptions\SudahTersambungException;
use App\Models\NomorWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Berbicara dengan layanan wa-gateway (Node.js + Baileys) lewat HTTP lokal.
 * Pesan masuk dan perubahan sesi tidak lewat sini; gateway mengirimnya ke
 * webhook (routes/api.php). Token yang sama dipakai di dua arah.
 */
class BaileysWhatsAppGateway implements WhatsAppGateway
{
    public function mintaQr(NomorWhatsapp $nomor): string
    {
        try {
            $res = $this->klien()->post('/sesi', ['nomor' => $nomor->nomor]);
        } catch (ConnectionException) {
            throw new GatewayException('Gateway WhatsApp tidak dapat dihubungi. Pastikan layanan wa-gateway berjalan.');
        }

        if ($res->failed()) {
            throw new GatewayException($res->json('pesan') ?? "Gateway menolak permintaan ({$res->status()}).");
        }

        if ($res->json('status') === 'tersambung') {
            throw new SudahTersambungException();
        }

        $qr = $res->json('qr');
        if (!is_string($qr) || $qr === '') {
            throw new GatewayException('Gateway tidak mengembalikan kode QR.');
        }

        return $qr;
    }

    /**
     * Gateway yang mati tidak menghalangi pemutusan di SAPA: sesi tetap
     * ditandai terputus agar tidak ada pesan yang dikirim lagi.
     */
    public function putuskan(NomorWhatsapp $nomor): void
    {
        try {
            $res = $this->klien()->delete("/sesi/{$nomor->nomor}");

            if ($res->failed() && $res->status() !== 404) {
                Log::warning('Gateway menolak pemutusan sesi', ['nomor' => $nomor->nomor, 'status' => $res->status()]);
            }
        } catch (ConnectionException $e) {
            Log::warning('Gateway tidak dapat dihubungi saat memutus sesi', ['nomor' => $nomor->nomor]);
        }
    }

    public function kirim(NomorWhatsapp $nomor, string $telepon, string $isi): string
    {
        try {
            $res = $this->klien()->post("/sesi/{$nomor->nomor}/pesan", [
                'telepon' => $telepon,
                'isi' => $isi,
            ]);
        } catch (ConnectionException) {
            throw new GagalKirimPesanException('Gateway WhatsApp tidak dapat dihubungi.');
        }

        if ($res->failed()) {
            throw new GagalKirimPesanException(
                $res->json('pesan') ?? "Gateway menolak pengiriman ({$res->status()})."
            );
        }

        return (string) ($res->json('id_pesan') ?? '');
    }

    private function klien(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('whatsapp.baileys.url'), '/'))
            ->withHeaders(['X-Gateway-Token' => (string) config('whatsapp.webhook_token')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('whatsapp.baileys.timeout'));
    }
}
