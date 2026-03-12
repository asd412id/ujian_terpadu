<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        protected DashboardRepository $repository
    ) {}

    /**
     * Get Dinas (admin) dashboard data.
     */
    public function getDinasDashboard(): array
    {
        $stats = Cache::remember('dinas.dashboard.stats', 30, function () {
            return $this->repository->getDinasStats();
        });

        $sesiAktif = $this->repository->getActiveSesiList(10);

        return compact('stats', 'sesiAktif');
    }

    /**
     * Get Sekolah dashboard data.
     *
     * @return array|null  Returns null if sekolah is not found
     */
    public function getSekolahDashboard(string $sekolahId): ?array
    {
        $sekolah = $this->repository->findSekolah($sekolahId);

        if (!$sekolah) {
            return null;
        }

        $paketIds = $this->repository->getEligiblePaketIds($sekolahId, $sekolah->jenjang);
        $stats = $this->repository->getSekolahStats($sekolah->id, $paketIds);
        $sesiMendatang = $this->repository->getUpcomingSesi($paketIds, 5);

        return compact('sekolah', 'stats', 'sesiMendatang');
    }

    /**
     * Get Pengawas dashboard data.
     */
    public function getPengawasDashboard(string $pengawasId): array
    {
        $sesiList = $this->repository->getPengawasSesiList($pengawasId);

        $stats = [
            'total_sesi'       => $sesiList->count(),
            'sesi_berlangsung' => $sesiList->where('status', 'berlangsung')->count(),
            'peserta_online'   => $sesiList->sum('peserta_mengerjakan'),
        ];

        return compact('sesiList', 'stats');
    }
}
