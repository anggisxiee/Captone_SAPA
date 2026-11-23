<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\AiFactory;
use App\Services\WhatsApp\BaileysWhatsAppGateway;
use App\Services\WhatsApp\FakeWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tidak singleton: pengaturan AI dapat diubah Administrator kapan saja.
        $this->app->bind(AiClient::class, fn () => AiFactory::buat());

        $this->app->singleton(WhatsAppGateway::class, function () {
            return match (config('whatsapp.driver')) {
                'fake' => new FakeWhatsAppGateway(),
                'baileys' => new BaileysWhatsAppGateway(),
                default => throw new InvalidArgumentException(
                    'Driver WhatsApp tidak dikenal: ' . config('whatsapp.driver')
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Teks waktu relatif ("3 menit yang lalu") dalam bahasa Indonesia.
        // Tidak memakai APP_LOCALE=id karena belum ada berkas terjemahan validasi.
        Carbon::setLocale('id');
    }
}
