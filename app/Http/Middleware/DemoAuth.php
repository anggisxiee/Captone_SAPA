<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan pengguna sudah login dan akunnya masih aktif.
 * (Nama kelas dipertahankan agar rute lama tidak berubah.)
 */
class DemoAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->status !== 'Aktif') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda dinonaktifkan, hubungi Administrator.');
        }

        view()->share('penggunaLogin', $user);

        return $next($request);
    }
}
