<?php

namespace App\Console\Commands;

use App\Services\Ai\KeputusanAi;
use App\Services\Ai\MesinJawaban;
use Illuminate\Console\Command;

class AiJawab extends Command
{
    protected $signature = 'ai:jawab {pesan : Pesan pelanggan yang diuji}';

    protected $description = 'Menjalankan mesin jawaban AI pada satu pesan (tanpa mengirim apa pun) dan menampilkan jejak keputusannya';

    public function handle(MesinJawaban $mesin): int
    {
        $k = $mesin->putuskan((string) $this->argument('pesan'));
        $d = $k->detail;

        $this->line('');
        $this->line($k->eskalasi()
            ? "<fg=red;options=bold>ESKALASI</> ke agen: {$k->teksAlasan()}"
            : '<fg=green;options=bold>KIRIM</> jawaban otomatis');

        $this->table(['Jejak', 'Nilai'], array_filter([
            ['Mode', $k->mode === 'ai' ? 'AI (' . ($d['model'] ?? '-') . ')' : 'aturan bawaan'],
            ['Maksud', $k->maksud ?? '-'],
            ['Kata kunci eskalasi', $d['kata_kunci'] ?? '-'],
            ['Nada marah', isset($d['nada']) ? ($d['nada']['marah'] ? 'ya (' . implode(', ', $d['nada']['pemicu']) . ')' : 'tidak') : '(tidak diperiksa)'],
            ['Keyakinan / ambang', sprintf('%.2f / %.2f', $k->keyakinan, $k->ambang)],
            ['Sumber', $k->sumber ? implode('; ', array_map(fn ($s) => "{$s['kode']} {$s['judul']} ({$s['skor']})", $k->sumber)) : '-'],
            ['Token', isset($d['token_masuk']) ? "{$d['token_masuk']} masuk, {$d['token_keluar']} keluar" : '-'],
            ['Galat penyedia', $d['galat'] ?? null],
        ], fn ($baris) => $baris[1] !== null));

        $this->line($k->eskalasi() ? '<comment>Pesan ke pelanggan:</comment> ' . MesinJawaban::PESAN_ESKALASI : '<comment>Balasan:</comment>');
        $this->line($k->eskalasi() ? '' : (string) $k->jawaban);
        $this->line('');

        return self::SUCCESS;
    }
}
