<?php

use App\Http\Controllers\Api\WebhookWhatsappController;
use App\Http\Middleware\VerifikasiTokenGateway;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook dari WA Gateway
|--------------------------------------------------------------------------
| Dilindungi token rahasia bersama (header X-Gateway-Token).
*/

Route::prefix('webhook/whatsapp')
    ->middleware([VerifikasiTokenGateway::class, 'throttle:120,1'])
    ->group(function () {
        Route::post('/pesan', [WebhookWhatsappController::class, 'pesanMasuk'])
            ->name('webhook.whatsapp.pesan');

        Route::post('/sesi', [WebhookWhatsappController::class, 'sesi'])
            ->name('webhook.whatsapp.sesi');
    });
