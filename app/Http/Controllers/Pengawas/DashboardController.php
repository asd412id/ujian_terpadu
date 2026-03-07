<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $sesiList = SesiUjian::with(['paket', 'sesiPeserta'])
            ->where('pengawas_id', $user->id)
            ->whereIn('status', ['persiapan', 'berlangsung'])
            ->get();

        $stats = [
            'total_sesi' => $sesiList->count(),
            'sesi_berlangsung' => $sesiList->where('status', 'berlangsung')->count(),
            'peserta_online' => $sesiList->sum(fn ($sesi) => $sesi->sesiPeserta->where('status', 'mengerjakan')->count()),
        ];

        return view('pengawas.dashboard', compact('sesiList', 'stats'));
    }
}
