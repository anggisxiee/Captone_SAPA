<?php

namespace App\Jobs;

use App\Models\PesanWhatsapp;
use App\Services\Ai\PenangananAi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Memproses satu pesan pelanggan dengan AI di luar siklus permintaan webhook. */
class ProsesBalasanAi implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $pesanId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->pesanId;
    }

    public function handle(PenangananAi $penanganan): void
    {
        $pesan = PesanWhatsapp::find($this->pesanId);

        if ($pesan) {
            $penanganan->proses($pesan);
        }
    }
}
