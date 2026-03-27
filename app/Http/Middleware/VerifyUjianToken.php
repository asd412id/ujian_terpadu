<?php

namespace App\Http\Middleware;

use App\Models\SesiPeserta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify that API requests contain a valid ujian token (sesi_token or URL token).
 * Prevents unauthenticated access to /api/ujian/* endpoints.
 * Uses Redis cache (30s TTL) to avoid DB query on every request.
 */
class VerifyUjianToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('sesi_token')
              ?? $request->input('token')
              ?? $request->route('token');

        if (! $token || strlen($token) !== 64 || ! ctype_alnum($token)) {
            return response()->json(['error' => 'Token ujian tidak valid.'], 401);
        }

        $cacheKey = "ujian_token:{$token}";
        $sesiPesertaId = Cache::get($cacheKey);

        if ($sesiPesertaId) {
            $sesiPeserta = SesiPeserta::find($sesiPesertaId);
        } else {
            $sesiPeserta = SesiPeserta::where('token_ujian', $token)->first();
        }

        if (! $sesiPeserta) {
            Cache::forget($cacheKey);
            return response()->json(['error' => 'Sesi ujian tidak ditemukan.'], 401);
        }

        Cache::put($cacheKey, $sesiPeserta->id, 30);

        $request->attributes->set('sesiPeserta', $sesiPeserta);
        $request->attributes->set('ujianToken', $token);

        return $next($request);
    }
}
