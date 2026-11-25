<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan webhook berasal dari gateway yang memegang token rahasia.
 * Tanpa token terkonfigurasi, semua permintaan ditolak.
 */
class VerifikasiTokenGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('whatsapp.webhook_token');
        $kiriman = (string) $request->header('X-Gateway-Token');

        if ($token === '' || !hash_equals($token, $kiriman)) {
            return response()->json(['pesan' => 'Token gateway tidak valid.'], 401);
        }

        return $next($request);
    }
}
