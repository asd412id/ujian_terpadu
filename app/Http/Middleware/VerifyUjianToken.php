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
        $sesiPeserta = Cache::get($cacheKey);

        if ($sesiPeserta instanceof SesiPeserta) {
            // Cached model — ensure relations are still loaded
            if (! $sesiPeserta->relationLoaded('sesi')) {
                $sesiPeserta->load('sesi.paket');
            }
        } else {
            // Cache miss or stale ID — fetch from DB with eager-loaded relations
            $sesiPeserta = SesiPeserta::with('sesi.paket')
                ->where('token_ujian', $token)
                ->first();
        }

        if (! $sesiPeserta) {
            Cache::forget($cacheKey);
            return response()->json(['error' => 'Sesi ujian tidak ditemukan.'], 401);
        }

        // Cache the full model (with relations) for 30s
        Cache::put($cacheKey, $sesiPeserta, 30);

        $request->attributes->set('sesiPeserta', $sesiPeserta);
        $request->attributes->set('ujianToken', $token);

        return $next($request);
    }
}
