<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAKS_PERCOBAAN = 5;
    private const DETIK_TAHAN = 300; // 5 menit

    public function tampilkanLogin()
    {
        if (Auth::check()) {
            return redirect($this->halamanAwal(Auth::user()));
        }

        return view('login');
    }

    public function login(Request $request)
    {
        $kredensial = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $kunci = Str::lower($kredensial['email']) . '|' . $request->ip();

        // Login ditahan setelah lima kali gagal berturut-turut.
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);

            return back()->withErrors([
                'email' => "Terlalu banyak percobaan gagal. Coba lagi dalam {$menit} menit.",
            ])->withInput($request->only('email'));
        }

        $user = User::where('email', $kredensial['email'])->first();

        if (!$user || !password_verify($kredensial['password'], $user->password)) {
            RateLimiter::hit($kunci, self::DETIK_TAHAN);

            LogAudit::catat(
                'Login gagal',
                'Percobaan login gagal untuk ' . $kredensial['email'],
                'Autentikasi',
                $user?->id
            );

            return back()->withErrors([
                'email' => 'Email atau password salah.',
            ])->withInput($request->only('email'));
        }

        if ($user->status !== 'Aktif') {
            return back()->withErrors([
                'email' => 'Akun Anda dinonaktifkan, hubungi Administrator.',
            ])->withInput($request->only('email'));
        }

        RateLimiter::clear($kunci);

        Auth::login($user);
        $request->session()->regenerate();

        LogAudit::catat('Login', 'Pengguna berhasil masuk.', 'Autentikasi', $user->id);

        return redirect()->intended($this->halamanAwal($user));
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            LogAudit::catat('Logout', 'Pengguna keluar.', 'Autentikasi');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Halaman awal sesuai role:
     * Agen/CS ke Kotak Masuk, Supervisor dan Administrator ke Dasbor.
     */
    public static function halamanAwal(User $user): string
    {
        return $user->role === 'Agen/CS'
            ? route('kotak-masuk')
            : route('dasbor');
    }
}
