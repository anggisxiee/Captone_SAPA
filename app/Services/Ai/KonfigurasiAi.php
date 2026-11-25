<?php

namespace App\Services\Ai;

use App\Models\PengaturanAI;
use Throwable;

/**
 * Konfigurasi AI yang berlaku. Aturan: bila Administrator sudah menyimpan kunci API
 * di halaman Pengaturan AI, SELURUH nilai dari halaman itu dipakai (penyedia, model,
 * alamat dasar). Jika tidak, nilai dari .env dipakai seluruhnya. Dengan begitu kunci
 * dari satu penyedia tidak pernah terkirim ke alamat penyedia lain.
 */
final class KonfigurasiAi
{
    public function __construct(
        public readonly string $driver,
        public readonly string $baseUrl,
        public readonly string $model,
        public readonly ?string $apiKey,
        public readonly int $timeout,
        public readonly string $sumber,   // pengaturan | env | fake
    ) {
    }

    public static function muat(): self
    {
        $driver = (string) config('ai.driver');
        $timeout = (int) config('ai.timeout');

        if ($driver === 'fake') {
            return new self('fake', (string) config('ai.base_url'), (string) config('ai.model'), null, $timeout, 'fake');
        }

        $pengaturan = null;
        try {
            $pengaturan = PengaturanAI::find(1);
        } catch (Throwable) {
            // tabel belum ada (mis. saat migrasi); pakai .env
        }

        if ($pengaturan && !empty($pengaturan->api_key)) {
            return new self(
                $driver,
                $pengaturan->base_url ?: (string) config('ai.base_url'),
                $pengaturan->model ?: (string) config('ai.model'),
                $pengaturan->api_key,
                $timeout,
                'pengaturan',
            );
        }

        return new self(
            $driver,
            (string) config('ai.base_url'),
            (string) config('ai.model'),
            config('ai.api_key') ?: null,
            $timeout,
            'env',
        );
    }

    /** Kunci disamarkan untuk ditampilkan: sk-…abcd */
    public function kunciSamar(): string
    {
        if (!$this->apiKey) {
            return '(belum diisi)';
        }

        return substr($this->apiKey, 0, 3) . '…' . substr($this->apiKey, -4);
    }
}
