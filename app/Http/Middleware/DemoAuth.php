<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class DemoAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Periksa apakah pengguna sudah login
        if (!session('demo_auth') || !session('user_id')) {
            return redirect()->route('login');
        }

        // 2. Ambil data pengguna dari database
        $user = User::find(session('user_id'));

        // 3. Periksa apakah pengguna masih tersedia dan aktif
        if (!$user || $user->status !== 'Aktif') {

            // Hapus sesi login
            $request->session()->forget([
                'demo_auth',
                'user_id',
            ]);

            return redirect()->route('login')
                ->with('error', 'Akun kamu sudah tidak aktif atau tidak ditemukan.');
        }

        // 4. Bagikan data pengguna ke seluruh halaman
        view()->share('penggunaLogin', $user);

        // 5. Lanjutkan ke halaman yang diminta
        return $next($request);
    }
}
