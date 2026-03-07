<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\JawabanPeserta;
use App\Models\KategoriSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = Cache::remember('dinas.dashboard.stats', 30, function () {
            $sekolahAktifIds = SesiUjian::where('status', 'berlangsung')
                ->with('paket')
                ->get()
                ->pluck('paket.sekolah_id')
                ->filter()
                ->unique();

            return [
                'total_sekolah'      => Sekolah::where('is_active', true)->count(),
                'sekolah_aktif'      => $sekolahAktifIds->count(),
                'total_peserta'      => Peserta::count(),
                'total_paket'        => PaketUjian::count(),
                'paket_aktif'        => PaketUjian::where('status', 'aktif')->count(),
                'sesi_berlangsung'   => SesiUjian::where('status', 'berlangsung')->count(),
                'peserta_online'     => SesiPeserta::whereIn('status', ['login', 'mengerjakan'])->count(),
                'essay_belum_dinilai'=> JawabanPeserta::where('is_terjawab', true)
                                            ->whereNull('skor_manual')
                                            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
                                            ->count(),
                'total_soal'         => Soal::count(),
                'total_kategori'     => KategoriSoal::count(),
            ];
        });

        $sesiAktif = SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung')
            ->latest('waktu_mulai')
            ->take(10)
            ->get();

        return view('dinas.dashboard', compact('stats', 'sesiAktif'));
    }
}
