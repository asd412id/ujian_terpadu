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

        // Verify token exists in database
        $exists = SesiPeserta::where('token_ujian', $token)->exists();

        if (! $exists) {
            return response()->json(['error' => 'Sesi ujian tidak ditemukan.'], 401);
        }

        return $next($request);
    }
}
