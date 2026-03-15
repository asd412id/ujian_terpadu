<?php

namespace App\Http\Middleware;

use App\Models\SesiPeserta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoActiveExam
{
    public function handle(Request $request, Closure $next): Response
    {
        $peserta = Auth::guard('peserta')->user();

        if ($peserta) {
            $active = SesiPeserta::where('peserta_id', $peserta->id)
                ->whereIn('status', ['mengerjakan', 'login'])
                ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
                ->first();

            if ($active) {
                return redirect()->route('ujian.mengerjakan', $active)
                    ->with('warning', 'Anda masih memiliki ujian yang sedang berlangsung. Selesaikan terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
