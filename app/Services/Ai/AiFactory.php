<?php

namespace App\Services\Ai;

use App\Exceptions\AiTidakTersediaException;
use InvalidArgumentException;

class AiFactory
{
    /**
     * @throws AiTidakTersediaException bila kunci API belum diatur (pemanggil beralih ke mode aturan bawaan)
     */
    public static function buat(): AiClient
    {
        $k = KonfigurasiAi::muat();

        return match ($k->driver) {
            'fake' => new FakeAiClient(),
            'openai' => $k->apiKey
                ? new OpenAiCompatibleClient($k->baseUrl, $k->apiKey, $k->model, $k->timeout)
                : throw new AiTidakTersediaException('Kunci API AI belum diatur.'),
            default => throw new InvalidArgumentException('Driver AI tidak dikenal: ' . $k->driver),
        };
    }
}
