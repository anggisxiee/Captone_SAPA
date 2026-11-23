<?php

namespace App\Console\Commands;

use App\Exceptions\AiException;
use App\Exceptions\KunciApiDitolakException;
use App\Services\Ai\AiFactory;
use App\Services\Ai\KonfigurasiAi;
use Illuminate\Console\Command;

class AiUji extends Command
{
    protected $signature = 'ai:uji {pesan? : Pesan uji (opsional); tanpa ini hanya koneksi yang diuji}';

    protected $description = 'Menguji koneksi dan kunci API penyedia AI (tanpa menampilkan kunci)';

    public function handle(): int
    {
        $k = KonfigurasiAi::muat();

        $this->table(['Pengaturan', 'Nilai'], [
            ['Driver', $k->driver],
            ['Sumber', $k->sumber === 'pengaturan' ? 'halaman Pengaturan AI' : ($k->sumber === 'env' ? '.env' : '-')],
            ['Alamat dasar', $k->baseUrl],
            ['Model', $k->model],
            ['Kunci API', $k->kunciSamar()],
        ]);

        try {
            $klien = AiFactory::buat();
            $uji = $klien->uji();
            $this->info("Koneksi berhasil: model {$uji->model}, {$uji->latensiMs} ms.");

            if ($pesan = $this->argument('pesan')) {
                $hasil = $klien->chat([['role' => 'user', 'content' => $pesan]]);
                $this->line('');
                $this->line($hasil->teks);
                $this->line('');
                $this->comment("Token: {$hasil->tokenMasuk} masuk, {$hasil->tokenKeluar} keluar.");
            }

            if ($saldo = $klien->saldo()) {
                $this->comment(sprintf(
                    'Saldo penyedia: sisa %s dari anggaran %s (terpakai %s).',
                    number_format($saldo['sisa'], 2), number_format($saldo['anggaran'], 2), number_format($saldo['terpakai'], 2)
                ));
            }

            return self::SUCCESS;
        } catch (KunciApiDitolakException $e) {
            $this->error($e->getMessage() . '. Periksa AI_API_KEY di .env atau kunci di halaman Pengaturan AI.');
        } catch (AiException $e) {
            $this->error($e->getMessage());
        }

        return self::FAILURE;
    }
}
