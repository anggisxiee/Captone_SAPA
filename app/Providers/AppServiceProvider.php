<?php

namespace App\Providers;

use App\Services\WhatsApp\FakeWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppGateway::class, function () {
            return match (config('whatsapp.driver')) {
                'fake' => new FakeWhatsAppGateway(),
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
        //
    }
}
