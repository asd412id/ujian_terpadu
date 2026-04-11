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
 *
 * Uses a two-tier cache strategy:
 * 1. Cache the sesi_peserta ID by token (cheap lookup, 30s TTL)
 * 2. On cache hit, load model by primary key (faster than WHERE token_ujian=?)
 *
 * Caches a plain array (not Eloquent model) to be safe under Octane's
 * long-lived workers.
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
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            // Cache hit — load by primary key (indexed, fast) instead of WHERE token_ujian
            $sesiPeserta = SesiPeserta::with('sesi.paket')->find($cached['id']);

            // If model disappeared (deleted/reset), invalidate cache
            if (! $sesiPeserta || $sesiPeserta->token_ujian !== $token) {
                Cache::forget($cacheKey);
                $sesiPeserta = null;
            }
        } else {
            // Cache miss — lookup by token (slower, uses WHERE clause)
            $sesiPeserta = SesiPeserta::with('sesi.paket')
                ->where('token_ujian', $token)
                ->first();
        }

        if (! $sesiPeserta) {
            Cache::forget($cacheKey);
            return response()->json(['error' => 'Sesi ujian tidak ditemukan.'], 401);
        }

        // Cache only scalar fields — safe for Octane long-lived workers
        Cache::put($cacheKey, [
            'id'        => $sesiPeserta->id,
            'token'     => $sesiPeserta->token_ujian,
            'status'    => $sesiPeserta->status,
            'sesi_id'   => $sesiPeserta->sesi_id,
            'peserta_id'=> $sesiPeserta->peserta_id,
        ], 30);

        $request->attributes->set('sesiPeserta', $sesiPeserta);
        $request->attributes->set('ujianToken', $token);

        return $next($request);
    }
}
