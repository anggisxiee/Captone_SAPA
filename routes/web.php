
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\DemoAuth;
use App\Http\Middleware\RoleAccess;

use App\Http\Controllers\ArtikelPengetahuanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TimAksesController;
use App\Http\Controllers\NomorWhatsappController;
use App\Http\Controllers\KotakMasukController;
use App\Http\Controllers\PengaturanAIController;
use App\Http\Controllers\AntreanRisikoController;
use App\Http\Controllers\InsidenRisikoController;
use App\Http\Controllers\RiwayatAntreanController;
use App\Http\Controllers\ProspekController;
use App\Http\Controllers\KontakController;

/*
|--------------------------------------------------------------------------
| Halaman Awal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect(AuthController::halamanAwal(auth()->user()))
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Login dan Logout
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'tampilkanLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Semua Pengguna yang Sudah Login
|--------------------------------------------------------------------------
*/

Route::middleware(DemoAuth::class)->group(function () {

    /*
    | Dashboard dan Halaman Umum
    */

    Route::view('/dasbor', 'dasbor')->name('dasbor');

    /*
    | Prospek
    */

    Route::get('/prospek', [
        ProspekController::class,
        'index'
    ])->name('prospek');

    // Menyimpan prospek baru
    Route::post('/prospek', [
        ProspekController::class,
        'store'
    ])->name('prospek.store');

    // Pindah tahap, catatan aktivitas, dan jadwal tindak lanjut
    Route::patch('/prospek/{prospek}/tindak-lanjut', [
        ProspekController::class,
        'tindakLanjut'
    ])->name('prospek.tindak-lanjut');

    Route::get('/kontak', [KontakController::class, 'index'])->name('kontak');
    Route::get('/kontak/ekspor', [KontakController::class, 'ekspor'])->name('kontak.ekspor');
    Route::put('/kontak/{kontak}', [KontakController::class, 'update'])->name('kontak.update');

    Route::get('/basis-pengetahuan', [
        ArtikelPengetahuanController::class,
        'index'
    ])->name('basis-pengetahuan');

    /*
    | Kotak Masuk WhatsApp
    */

    Route::get('/kotak-masuk', [
        KotakMasukController::class,
        'index'
    ])->name('kotak-masuk');

    Route::get('/kotak-masuk/data', [
        KotakMasukController::class,
        'data'
    ])->name('kotak-masuk.data');

    Route::post('/kotak-masuk/kirim', [
        KotakMasukController::class,
        'kirimPesan'
    ])->name('kotak-masuk.kirim');

    Route::post('/kotak-masuk/baru', [
        KotakMasukController::class,
        'pesanBaru'
    ])->name('kotak-masuk.baru');

    Route::patch('/kotak-masuk/percakapan/{percakapan}/status', [
        KotakMasukController::class,
        'ubahStatus'
    ])->name('kotak-masuk.status');

    /*
    |--------------------------------------------------------------------------
    | Akses Supervisor dan Administrator
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        RoleAccess::class . ':Supervisor,Administrator'
    )->group(function () {

        /*
        | Basis Pengetahuan (mengelola artikel, F12)
        */

        Route::post('/basis-pengetahuan', [
            ArtikelPengetahuanController::class,
            'store'
        ])->name('basis-pengetahuan.store');

        Route::post('/basis-pengetahuan/uji', [
            ArtikelPengetahuanController::class,
            'uji'
        ])->name('basis-pengetahuan.uji');

        Route::put('/basis-pengetahuan/{artikel}', [
            ArtikelPengetahuanController::class,
            'update'
        ])->name('basis-pengetahuan.update');

        /*
        | Nomor WhatsApp
        */

        Route::get('/nomor-whatsapp', [
            NomorWhatsappController::class,
            'index'
        ])->name('nomor-whatsapp');

        Route::post('/nomor-whatsapp', [
            NomorWhatsappController::class,
            'store'
        ])->name('nomor-whatsapp.store');

        Route::put('/nomor-whatsapp/{nomorWhatsapp}', [
            NomorWhatsappController::class,
            'update'
        ])->name('nomor-whatsapp.update');

        Route::patch('/nomor-whatsapp/{nomorWhatsapp}/status', [
            NomorWhatsappController::class,
            'toggleStatus'
        ])->name('nomor-whatsapp.status');

        Route::delete('/nomor-whatsapp/{nomorWhatsapp}', [
            NomorWhatsappController::class,
            'destroy'
        ])->name('nomor-whatsapp.destroy');

        /*
        | Pengaturan AI
        */

        Route::get('/pengaturan-ai', [
            PengaturanAIController::class,
            'index'
        ])->name('pengaturan-ai');

        Route::put('/pengaturan-ai', [
            PengaturanAIController::class,
            'update'
        ])->name('pengaturan-ai.update');

        Route::post('/pengaturan-ai/reset', [
            PengaturanAIController::class,
            'reset'
        ])->name('pengaturan-ai.reset');

        Route::post('/pengaturan-ai/uji', [
            PengaturanAIController::class,
            'uji'
        ])->name('pengaturan-ai.uji');

        /*
        | Antrean dan Risiko
        */

        Route::get('/antrean-risiko', [
            AntreanRisikoController::class,
            'index'
        ])->name('antrean-risiko');

        Route::patch('/antrean-risiko/{id}/status', [
            AntreanRisikoController::class,
            'ubahStatus'
        ])->name('antrean-risiko.status');

        /*
        | Riwayat Antrean
        */

        Route::get('/riwayat-antrean', [
            RiwayatAntreanController::class,
            'index'
        ])->name('riwayat-antrean');

        /*
        | Pusat Risiko
        */

        Route::post('/pusat-risiko', [
            InsidenRisikoController::class,
            'store'
        ])->name('pusat-risiko.store');

        Route::patch('/pusat-risiko/{id}/status', [
            InsidenRisikoController::class,
            'ubahStatus'
        ])->name('pusat-risiko.status');

        Route::delete('/pusat-risiko/{id}', [
            InsidenRisikoController::class,
            'destroy'
        ])->name('pusat-risiko.destroy');

        /*
        | Log Audit
        */

        Route::view('/log-audit', 'log-audit')
            ->name('log-audit');

    });

    /*
    |--------------------------------------------------------------------------
    | Khusus Administrator
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        RoleAccess::class . ':Administrator'
    )->group(function () {

        /*
        | Penautan sesi nomor WhatsApp (QR)
        */

        Route::delete('/kontak/{kontak}', [KontakController::class, 'destroy'])->name('kontak.destroy');

        Route::post('/nomor-whatsapp/{nomorWhatsapp}/tautkan', [
            NomorWhatsappController::class,
            'tautkan'
        ])->name('nomor-whatsapp.tautkan');

        Route::get('/nomor-whatsapp/{nomorWhatsapp}/sesi', [
            NomorWhatsappController::class,
            'statusSesi'
        ])->name('nomor-whatsapp.sesi');

        Route::post('/nomor-whatsapp/{nomorWhatsapp}/putuskan', [
            NomorWhatsappController::class,
            'putuskan'
        ])->name('nomor-whatsapp.putuskan');

        Route::post('/nomor-whatsapp/{nomorWhatsapp}/simulasi-pindai', [
            NomorWhatsappController::class,
            'simulasiPindai'
        ])->name('nomor-whatsapp.simulasi-pindai');

        /*
        | Tim dan Akses
        */

        Route::get('/tim-akses', [
            TimAksesController::class,
            'index'
        ])->name('tim-akses');

        Route::post('/tim-akses', [
            TimAksesController::class,
            'store'
        ])->name('tim-akses.store');

        Route::get('/tim-akses/{user}/edit', [
            TimAksesController::class,
            'edit'
        ])->name('tim-akses.edit');

        Route::put('/tim-akses/{user}', [
            TimAksesController::class,
            'update'
        ])->name('tim-akses.update');

        Route::patch('/tim-akses/{user}/status', [
            TimAksesController::class,
            'toggleStatus'
        ])->name('tim-akses.status');

        Route::delete('/tim-akses/{user}', [
            TimAksesController::class,
            'destroy'
        ])->name('tim-akses.destroy');

    });

});
