<?php

namespace App\Services;

use App\Models\JawabanPeserta;
use App\Models\KategoriSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get Dinas (admin) dashboard data.
     */
    public function getDinasDashboard(): array
    {
        $stats = Cache::remember('dinas.dashboard.stats', 30, function () {
            $sekolahAktifIds = SesiUjian::where('status', 'berlangsung')
                ->with('paket')
                ->get()
                ->pluck('paket.sekolah_id')
                ->filter()
                ->unique();

            return [
                'total_sekolah'       => Sekolah::where('is_active', true)->count(),
                'sekolah_aktif'       => $sekolahAktifIds->count(),
                'total_peserta'       => Peserta::count(),
                'total_paket'         => PaketUjian::count(),
                'paket_aktif'         => PaketUjian::where('status', 'aktif')->count(),
                'sesi_berlangsung'    => SesiUjian::where('status', 'berlangsung')->count(),
                'peserta_online'      => SesiPeserta::whereIn('status', ['login', 'mengerjakan'])->count(),
                'essay_belum_dinilai' => JawabanPeserta::where('is_terjawab', true)
                    ->whereNull('skor_manual')
                    ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
                    ->count(),
                'total_soal'          => Soal::count(),
                'total_kategori'      => KategoriSoal::where('is_active', true)->count(),
            ];
        });

        $sesiAktif = SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung')
            ->latest('waktu_mulai')
            ->take(10)
            ->get();

        return compact('stats', 'sesiAktif');
    }

    /**
     * Get Sekolah dashboard data.
     *
     * @return array|null  Returns null if sekolah is not found (user has no sekolah assigned)
     */
    public function getSekolahDashboard(string $sekolahId): ?array
    {
        $sekolah = Sekolah::find($sekolahId);

        if (!$sekolah) {
            return null;
        }

        $paketIds = PaketUjian::where('status', 'aktif')
            ->where(function ($q) use ($sekolahId, $sekolah) {
                $q->where('sekolah_id', $sekolahId)
                  ->orWhere(function ($q2) use ($sekolah) {
                      $q2->whereNull('sekolah_id');
                      if ($sekolah->jenjang) {
                          $q2->where(fn ($q3) => $q3->where('jenjang', $sekolah->jenjang)->orWhere('jenjang', 'SEMUA'));
                      }
                  });
            })
            ->pluck('id');

        $stats = [
            'total_peserta' => Peserta::where('sekolah_id', $sekolah->id)->count(),
            'total_paket'   => $paketIds->count(),
            'sesi_aktif'    => SesiUjian::whereIn('paket_id', $paketIds)->where('status', 'berlangsung')->count(),
        ];

        $sesiMendatang = SesiUjian::whereIn('paket_id', $paketIds)
            ->whereIn('status', ['persiapan', 'menunggu', 'berlangsung'])
            ->with('paket')
            ->orderBy('waktu_mulai')
            ->limit(5)
            ->get();

        return compact('sekolah', 'stats', 'sesiMendatang');
    }

    /**
     * Get Pengawas dashboard data.
     */
    public function getPengawasDashboard(string $pengawasId): array
    {
        $sesiList = SesiUjian::with(['paket', 'sesiPeserta'])
            ->where('pengawas_id', $pengawasId)
            ->whereIn('status', ['persiapan', 'berlangsung'])
            ->get();

        $stats = [
            'total_sesi'        => $sesiList->count(),
            'sesi_berlangsung'  => $sesiList->where('status', 'berlangsung')->count(),
            'peserta_online'    => $sesiList->sum(fn ($sesi) => $sesi->sesiPeserta->where('status', 'mengerjakan')->count()),
        ];

        return compact('sesiList', 'stats');
    }
}
