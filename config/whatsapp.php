<?php

return [

    /*
    | Driver gateway WhatsApp.
    |   fake : simulasi tanpa WhatsApp sungguhan (pengembangan dan tes).
    | Driver sungguhan ditambahkan di App\Providers\AppServiceProvider
    | setelah tim memutuskan layanan yang dipakai.
    */
    'driver' => env('WHATSAPP_DRIVER', 'fake'),

    // Token rahasia bersama untuk webhook (header X-Gateway-Token).
    // Kosong = semua webhook ditolak.
    'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),

    // Masa berlaku kode QR penautan (detik).
    'qr_ttl' => 60,

    // Jeda sebelum pesan yang gagal dicoba kirim ulang (detik).
    'jeda_ulang' => 30,
];
