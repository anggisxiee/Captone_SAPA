<?php

namespace App\Services\Ai;

final class HasilAi
{
    public function __construct(
        public readonly string $teks,
        public readonly string $model,
        public readonly int $tokenMasuk = 0,
        public readonly int $tokenKeluar = 0,
    ) {
    }
}
