<?php

namespace App\Services;

use App\Repositories\MonitoringRepository;

class MonitoringService
{
    public function __construct(
        protected MonitoringRepository $repository
    ) {}

    /**
     * Get dashboard monitoring data with summary.
     */
    public function getDashboardMonitoring(): array
    {
        $sekolahList = $this->repository->getSekolahWithMonitoringStats();
        $sesiList = $this->repository->getDashboardSesiList();
        $summaryRaw = $this->repository->getDashboardSummary();

        $summary = [
            'total_sesi'     => $sesiList->count(),
            'peserta_online' => $summaryRaw['peserta_online'],
            'peserta_ragu'   => 0,
            'sudah_submit'   => $summaryRaw['sudah_submit'],
        ];

        return compact('sekolahList', 'sesiList', 'summary');
    }

    /**
     * Get active sessions with optional filters.
     */
    public function getSesiAktif(array $filters = []): mixed
    {
        return $this->repository->getSesiAktifFiltered($filters);
    }

    /**
     * Get peserta status for a specific sesi ujian (paginated).
     */
    public function getPesertaStatus(string $sesiId, array $filters = []): array
    {
        $sesi = $this->repository->findSesiWithPaket($sesiId);
        $alerts = $this->repository->getAlertsBySesi($sesi->id);
        $stats = $this->repository->getSesiPesertaStats($sesi->id);
        $pesertaList = $this->repository->getSesiPesertaPaginated($sesi->id, $filters);
        $sekolahList = $this->repository->getSekolahListBySesi($sesi->id);
        $pesertaLive = $this->repository->getSesiPesertaLiveData($sesi->id);

        return compact('sesi', 'alerts', 'pesertaList', 'stats', 'sekolahList', 'pesertaLive');
    }

    /**
     * Get monitoring statistics.
     */
    public function getStatistik(): array
    {
        return $this->repository->getStatistik();
    }

    /**
     * Get ruang monitoring for a specific pengawas.
     */
    public function getRuangMonitoring(string $pengawasId): array
    {
        $sesi = $this->repository->getRuangForPengawas($pengawasId);

        if (!$sesi) {
            return ['sesi' => null, 'statsPeserta' => []];
        }

        $statsPeserta = [
            'total'        => $sesi->total_peserta,
            'aktif'        => $sesi->peserta_aktif,
            'submit'       => $sesi->peserta_submit,
            'belum_masuk'  => $sesi->peserta_belum,
        ];

        return compact('sesi', 'statsPeserta');
    }

    /**
     * Get peserta list by ruang/sesi for pengawas (paginated).
     */
    public function getPesertaByRuang(string $sesiId, array $filters = []): array
    {
        $sesi = $this->repository->findSesiWithPaketById($sesiId);
        $statsPeserta = $this->repository->getSesiPesertaStatsSimple($sesi->id);
        $pesertaPaginated = $this->repository->getPesertaByRuangPaginated($sesi->id, $filters);
        $pesertaLive = $this->repository->getSesiPesertaLiveData($sesi->id);

        return compact('sesi', 'statsPeserta', 'pesertaPaginated', 'pesertaLive');
    }

    /**
     * Get active sekolah list (for view).
     */
    public function getSekolahList(): mixed
    {
        return $this->repository->getActiveSekolahList();
    }

    /**
     * Get all sekolah monitoring data with real-time stats (for API).
     */
    public function getSekolahMonitoringData(): array
    {
        $batch = $this->repository->getSekolahMonitoringBatch();

        $sekolahData = $batch['sekolahList']->map(function ($s) use ($batch) {
            $sesiAktif = $batch['sesiAktifMap'][$s->id] ?? 0;
            $pesertaOnline = $batch['pesertaOnlineMap'][$s->id] ?? 0;
            $pesertaSelesai = $batch['pesertaSelesaiMap'][$s->id] ?? 0;
            $cheatingCount = $batch['cheatingMap'][$s->id] ?? 0;
            $status = $sesiAktif > 0 ? 'aktif' : ($pesertaSelesai > 0 ? 'selesai' : 'belum');

            return [
                'id'              => $s->id,
                'nama_sekolah'    => $s->nama,
                'kode_sekolah'    => $s->npsn ?? '–',
                'sesi_aktif'      => $sesiAktif,
                'peserta_online'  => $pesertaOnline,
                'peserta_selesai' => $pesertaSelesai,
                'cheating_count'  => $cheatingCount,
                'status'          => $status,
            ];
        });

        $summary = [
            'total_sekolah'       => $sekolahData->count(),
            'sekolah_aktif'       => $sekolahData->where('status', 'aktif')->count(),
            'total_peserta_aktif' => $sekolahData->sum('peserta_online'),
            'total_selesai'       => $sekolahData->sum('peserta_selesai'),
        ];

        return [
            'sekolah' => $sekolahData->values()->all(),
            'summary' => $summary,
        ];
    }

    /**
     * Get sesi detail stats + per-peserta live data (for polling API).
     */
    public function getSesiStats(string $sesiId): array
    {
        $stats = $this->repository->getSesiPesertaStats($sesiId);
        $pesertaLive = $this->repository->getSesiPesertaLiveData($sesiId);

        return [
            'stats'        => $stats,
            'peserta_live' => $pesertaLive,
        ];
    }
}
