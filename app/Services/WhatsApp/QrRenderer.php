<?php

namespace App\Services\WhatsApp;

/**
 * Mengubah isi QR menjadi SVG. Memakai bacon/bacon-qr-code bila terpasang
 * (composer require bacon/bacon-qr-code); jika belum, menampilkan kotak
 * pengganti agar alur penautan tetap bisa diuji.
 */
class QrRenderer
{
    public static function svg(string $isi): string
    {
        if (class_exists(\BaconQrCode\Writer::class)) {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(280, 2),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );

            return (new \BaconQrCode\Writer($renderer))->writeString($isi);
        }

        $teks = e(mb_strimwidth($isi, 0, 28, '…'));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
  <rect width="200" height="200" fill="#f1f5f9" stroke="#94a3b8" stroke-dasharray="6 4"/>
  <text x="100" y="86" text-anchor="middle" font-size="12" fill="#475569">Pratinjau QR (mode uji)</text>
  <text x="100" y="108" text-anchor="middle" font-size="8" fill="#64748b">{$teks}</text>
  <text x="100" y="130" text-anchor="middle" font-size="8" fill="#94a3b8">pasang bacon/bacon-qr-code untuk QR asli</text>
</svg>
SVG;
    }
}
