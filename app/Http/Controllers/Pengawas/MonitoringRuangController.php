<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use Illuminate\Support\Facades\Auth;

class MonitoringRuangController extends Controller
{
    public function index(SesiUjian $sesi)
    {
        $sesi->load(['paket', 'sesiPeserta.peserta', 'sesiPeserta.logAktivitas']);

        $statsPeserta = [
            'total'        => $sesi->sesiPeserta->count(),
            'aktif'        => $sesi->sesiPeserta->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'       => $sesi->sesiPeserta->where('status', 'submit')->count(),
            'belum_masuk'  => $sesi->sesiPeserta->where('status', 'belum_login')->count(),
        ];

        return view('pengawas.monitoring-ruang', compact('sesi', 'statsPeserta'));
    }
}
