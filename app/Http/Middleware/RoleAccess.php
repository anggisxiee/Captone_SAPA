<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleAccess
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {

        // Ambil pengguna yang sedang login
        $user = Auth::user();

        // Jika pengguna tidak ditemukan
        if (!$user) {
            return redirect()->route('login');
        }

        // Periksa apakah role pengguna memiliki akses
        if (!in_array($user->role, $roles)) {
            return redirect()->route('dasbor')
                ->with('error', 'Kamu tidak memiliki izin untuk mengakses halaman tersebut.');
        }

        return $next($request);
    }
}
