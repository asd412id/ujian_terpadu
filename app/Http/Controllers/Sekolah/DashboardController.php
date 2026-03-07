<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use App\Models\SesiUjian;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user    = Auth::user();
        $sekolah = $user->sekolah;

        // Redirect admin_dinas/super_admin to dinas dashboard if no school assigned
        if (! $sekolah) {
            return redirect()->route('dinas.dashboard');
        }

        $paketIds = $sekolah->paketUjian()->pluck('id');

        $stats = [
            'total_peserta' => Peserta::where('sekolah_id', $sekolah->id)->count(),
            'total_paket'   => $paketIds->count(),
            'sesi_aktif'    => SesiUjian::whereIn('paket_id', $paketIds)->where('status', 'berlangsung')->count(),
            'total_soal'    => $sekolah->soal()->count(),
        ];

        $sesiMendatang = SesiUjian::whereIn('paket_id', $paketIds)
            ->whereIn('status', ['menunggu', 'berlangsung'])
            ->with('paket')
            ->orderBy('waktu_mulai')
            ->limit(5)
            ->get();

        return view('sekolah.dashboard', compact('sekolah', 'stats', 'sesiMendatang'));
    }
}
