<?php

namespace App\Console\Commands;

use App\Models\Prospek;
use App\Services\Prospek\SkorPrioritas;
use Illuminate\Console\Command;

class ProspekHitungUlang extends Command
{
    protected $signature = 'prospek:hitung-ulang';

    protected $description = 'Menghitung ulang skor prioritas semua prospek (jalankan setelah mengubah bobot di config/prospek.php)';

    public function handle(SkorPrioritas $skor): int
    {
        $jumlah = 0;
        Prospek::with('kontak')->chunkById(100, function ($kumpulan) use ($skor, &$jumlah) {
            foreach ($kumpulan as $prospek) {
                $skor->simpan($prospek);
                $jumlah++;
            }
        });

        $this->info("{$jumlah} prospek dihitung ulang.");

        return self::SUCCESS;
    }
}
