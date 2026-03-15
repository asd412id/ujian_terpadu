<?php

namespace App\Http\Middleware;

use App\Models\SesiPeserta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify that API requests contain a valid ujian token (sesi_token or URL token).
 * Prevents unauthenticated access to /api/ujian/* endpoints.
 */
class VerifyUjianToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Extract token from request body or URL parameter
        $token = $request->input('sesi_token')
              ?? $request->input('token')
              ?? $request->route('token');

        if (! $token || strlen($token) !== 64 || ! ctype_alnum($token)) {
            return response()->json(['error' => 'Token ujian tidak valid.'], 401);
        }

        // Load full sesi_peserta and share via request attributes (avoids duplicate query in controller)
        $sesiPeserta = SesiPeserta::where('token_ujian', $token)->first();

        if (! $sesiPeserta) {
            return response()->json(['error' => 'Sesi ujian tidak ditemukan.'], 401);
        }

        $request->attributes->set('sesiPeserta', $sesiPeserta);

        return $next($request);
    }
}
