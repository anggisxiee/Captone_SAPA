<?php

namespace App\Services\Ai;

final class HasilUji
{
    public function __construct(
        public readonly string $model,
        public readonly int $latensiMs,
    ) {
    }
}
