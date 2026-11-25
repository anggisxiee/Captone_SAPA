<?php

return [

    /*
    | Driver gateway WhatsApp.
    |   fake    : simulasi tanpa WhatsApp sungguhan (pengembangan dan tes).
    |   baileys : layanan wa-gateway (Node.js + Baileys) di folder wa-gateway/.
    */
    'driver' => env('WHATSAPP_DRIVER', 'fake'),

    // Token rahasia bersama (header X-Gateway-Token), dipakai di dua arah:
    // gateway -> webhook Laravel, dan Laravel -> gateway.
    // Kosong = semua webhook ditolak.
    'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),

    // Layanan wa-gateway (hanya untuk driver baileys). Biarkan di localhost.
    'baileys' => [
        'url' => env('WHATSAPP_GATEWAY_URL', 'http://127.0.0.1:3001'),
        // Meminta QR menunggu WhatsApp, jadi batasnya lebih longgar dari QR_TIMEOUT_MS gateway.
        'timeout' => (int) env('WHATSAPP_GATEWAY_TIMEOUT', 30),
    ],

    // Masa berlaku kode QR penautan (detik).
    'qr_ttl' => 60,

    // Jeda sebelum pesan yang gagal dicoba kirim ulang (detik).
    'jeda_ulang' => 30,
];
