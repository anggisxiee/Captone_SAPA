
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

use App\Models\User;

use App\Http\Middleware\DemoAuth;
use App\Http\Middleware\RoleAccess;

use App\Http\Controllers\TimAksesController;
use App\Http\Controllers\NomorWhatsappController;
use App\Http\Controllers\KotakMasukController;
use App\Http\Controllers\PengaturanAIController;
use App\Http\Controllers\AntreanRisikoController;
use App\Http\Controllers\InsidenRisikoController;
use App\Http\Controllers\RiwayatAntreanController;
use App\Http\Controllers\ProspekController;

/*
|--------------------------------------------------------------------------
| Halaman Awal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dasbor');
});

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', function (Request $request) {

    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->withInput();
    }

    if ($user->status !== 'Aktif') {
        return back()->withErrors([
            'email' => 'Akun Anda tidak aktif.',
        ])->withInput();
    }

    session([
        'demo_auth' => true,
        'user_id' => $user->id,
    ]);

    return redirect()->route('dasbor');

})->name('login.post');

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', function () {

    session()->forget([
        'demo_auth',
        'user_id',
    ]);

    return redirect()->route('login');

})->name('logout');

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

    Route::view('/kontak', 'kontak')->name('kontak');

    Route::view('/basis-pengetahuan', 'basis-pengetahuan')
        ->name('basis-pengetahuan');

    /*
    | Kotak Masuk WhatsApp
    */

    Route::get('/kotak-masuk', [
        KotakMasukController::class,
        'index'
    ])->name('kotak-masuk');

    Route::post('/kotak-masuk/kirim', [
        KotakMasukController::class,
        'kirimPesan'
    ])->name('kotak-masuk.kirim');

    /*
    |--------------------------------------------------------------------------
    | Akses Supervisor dan Administrator
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        RoleAccess::class . ':Supervisor,Administrator'
    )->group(function () {

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
