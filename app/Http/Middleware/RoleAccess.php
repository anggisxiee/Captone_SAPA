<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class RoleAccess
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {

        // Ambil pengguna yang sedang login
        $user = User::find(session('user_id'));

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
