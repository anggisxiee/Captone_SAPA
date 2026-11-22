<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Memproses antrean keluar yang masih berkeputusan "Kirim" (misalnya
| karena queue worker sempat mati). Job bersifat unik per pesan,
| sehingga aman dijalankan berulang.
*/
Artisan::command('antrean:proses', function () {
    $ids = \App\Models\AntreanPesan::where('keputusan', 'Kirim')
        ->where(fn ($q) => $q->whereNull('jadwal_kirim')->orWhere('jadwal_kirim', '<=', now()))
        ->pluck('id');

    $ids->each(fn ($id) => \App\Jobs\KirimPesanKeluar::dispatch($id));

    $this->info("{$ids->count()} pesan dijadwalkan untuk dikirim.");
})->purpose('Kirim pesan keluar yang masih menunggu di antrean');

\Illuminate\Support\Facades\Schedule::command('antrean:proses')->everyMinute();
