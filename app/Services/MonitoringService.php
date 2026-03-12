<?php

namespace App\Services;

use App\Models\LogAktivitasUjian;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Repositories\MonitoringRepository;
use Illuminate\Support\Facades\DB;

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
        $sekolahList = Sekolah::withCount(['peserta'])
            ->with(['paketUjian' => fn ($q) => $q->whereHas('sesi', fn ($s) => $s->where('status', 'berlangsung'))])
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        // Use withCount + subqueries instead of loading all sesiPeserta into memory
        $sesiList = SesiUjian::with(['paket.sekolah', 'pengawas'])
            ->withCount([
                'sesiPeserta as total_peserta',
                'sesiPeserta as peserta_online' => fn ($q) => $q->whereIn('status', ['login', 'mengerjakan']),
                'sesiPeserta as sudah_submit' => fn ($q) => $q->whereIn('status', ['submit', 'dinilai']),
            ])
            ->where('status', 'berlangsung')
            ->latest()
            ->get();

        // Single aggregated query for summary
        $summaryRaw = SesiPeserta::query()
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->selectRaw("
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as peserta_online,
                COUNT(CASE WHEN status IN ('submit','dinilai') THEN 1 END) as sudah_submit
            ")
            ->first();

        $summary = [
            'total_sesi'     => $sesiList->count(),
            'peserta_online' => $summaryRaw->peserta_online ?? 0,
            'peserta_ragu'   => 0,
            'sudah_submit'   => $summaryRaw->sudah_submit ?? 0,
        ];

        return compact('sekolahList', 'sesiList', 'summary');
    }

    /**
     * Get active sessions with optional filters.
     */
    public function getSesiAktif(array $filters = []): mixed
    {
        $query = SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung');

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('paket', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        return $query->latest()->get();
    }

    /**
     * Get peserta status for a specific sesi ujian (paginated).
     */
    public function getPesertaStatus(string $sesiId, array $filters = []): array
    {
        $sesi = SesiUjian::with(['paket.sekolah'])
            ->findOrFail($sesiId);

        $alerts = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit', 'koneksi_putus'])
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesi->id))
            ->with('sesiPeserta.peserta')
            ->latest()
            ->take(20)
            ->get();

        // Stats from single aggregate query (1 query instead of 5)
        // Only count records with valid peserta (exclude orphans)
        $statsRaw = SesiPeserta::where('sesi_id', $sesi->id)
            ->whereHas('peserta')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as `online`,
                COUNT(CASE WHEN status IN ('submit','dinilai') THEN 1 END) as submit,
                COUNT(CASE WHEN status IN ('terdaftar','belum_login') THEN 1 END) as belum_mulai
            ")
            ->first();
        $stats = [
            'total'       => $statsRaw->total ?? 0,
            'online'      => $statsRaw->online ?? 0,
            'submit'      => $statsRaw->submit ?? 0,
            'kosong'      => $statsRaw->belum_mulai ?? 0,
            'belum_mulai' => $statsRaw->belum_mulai ?? 0,
        ];

        // Paginated peserta list — exclude orphaned records without peserta
        $query = SesiPeserta::with(['peserta.sekolah'])
            ->whereHas('peserta')
            ->where('sesi_id', $sesi->id);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('peserta', fn ($q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%"));
        }

        if (!empty($filters['status'])) {
            $s = $filters['status'];
            if ($s === 'online') {
                $query->whereIn('status', ['login', 'mengerjakan']);
            } elseif ($s === 'submit') {
                $query->whereIn('status', ['submit', 'dinilai']);
            } elseif ($s === 'belum') {
                $query->whereIn('status', ['terdaftar', 'belum_login']);
            }
        }

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        $perPage = $filters['per_page'] ?? 50;
        $pesertaList = $query->orderByRaw("FIELD(status, 'mengerjakan', 'login', 'submit', 'dinilai', 'terdaftar', 'belum_login')")
            ->paginate($perPage);

        // Get sekolah list that have peserta in this sesi
        $sekolahIds = SesiPeserta::where('sesi_id', $sesi->id)
            ->join('peserta', 'sesi_peserta.peserta_id', '=', 'peserta.id')
            ->distinct()
            ->pluck('peserta.sekolah_id');
        $sekolahList = Sekolah::whereIn('id', $sekolahIds)->orderBy('nama')->get();

        return compact('sesi', 'alerts', 'pesertaList', 'stats', 'sekolahList');
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
        $sesi = SesiUjian::with(['paket', 'sesiPeserta.peserta', 'sesiPeserta.logAktivitas'])
            ->where('pengawas_id', $pengawasId)
            ->whereIn('status', ['persiapan', 'berlangsung'])
            ->first();

        if (!$sesi) {
            return ['sesi' => null, 'statsPeserta' => []];
        }

        $statsPeserta = [
            'total'        => $sesi->sesiPeserta->count(),
            'aktif'        => $sesi->sesiPeserta->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'       => $sesi->sesiPeserta->where('status', 'submit')->count(),
            'belum_masuk'  => $sesi->sesiPeserta->whereIn('status', ['terdaftar', 'belum_login'])->count(),
        ];

        return compact('sesi', 'statsPeserta');
    }

    /**
     * Get peserta list by ruang/sesi for pengawas (paginated).
     */
    public function getPesertaByRuang(string $sesiId, array $filters = []): array
    {
        $sesi = SesiUjian::with(['paket'])
            ->findOrFail($sesiId);

        $allPeserta = SesiPeserta::where('sesi_id', $sesi->id);
        $statsPeserta = [
            'total'        => (clone $allPeserta)->count(),
            'aktif'        => (clone $allPeserta)->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'       => (clone $allPeserta)->where('status', 'submit')->count(),
            'belum_masuk'  => (clone $allPeserta)->whereIn('status', ['terdaftar', 'belum_login'])->count(),
        ];

        $query = SesiPeserta::with(['peserta', 'logAktivitas'])
            ->where('sesi_id', $sesi->id);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('peserta', fn ($q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%"));
        }

        if (!empty($filters['status'])) {
            $s = $filters['status'];
            if ($s === 'mengerjakan') {
                $query->whereIn('status', ['login', 'mengerjakan']);
            } elseif ($s === 'submit') {
                $query->where('status', 'submit');
            } elseif ($s === 'belum') {
                $query->whereIn('status', ['terdaftar', 'belum_login']);
            }
        }

        $perPage = $filters['per_page'] ?? 50;
        $pesertaPaginated = $query->orderByRaw("FIELD(status, 'mengerjakan', 'login', 'submit', 'dinilai', 'terdaftar', 'belum_login')")
            ->paginate($perPage);

        return compact('sesi', 'statsPeserta', 'pesertaPaginated');
    }

    /**
     * Get active sekolah list (for view).
     */
    public function getSekolahList(): mixed
    {
        return Sekolah::where('is_active', true)->orderBy('nama')->get();
    }

    /**
     * Get all sekolah monitoring data with real-time stats (for API).
     * Optimized: batch queries instead of N+1 per sekolah.
     */
    public function getSekolahMonitoringData(): array
    {
        $sekolahList = Sekolah::withCount(['peserta'])
            ->where('is_active', true)
            ->get();

        $sekolahIds = $sekolahList->pluck('id');

        // Batch: sesi aktif per sekolah (1 query)
        $sesiAktifMap = SesiUjian::where('status', 'berlangsung')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        // Batch: peserta online per sekolah (1 query)
        $pesertaOnlineMap = SesiPeserta::whereIn('sesi_peserta.status', ['login', 'mengerjakan'])
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->where('sesi_ujian.status', 'berlangsung')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        // Batch: peserta selesai per sekolah (1 query)
        $pesertaSelesaiMap = SesiPeserta::where('sesi_peserta.status', 'submit')
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereDate('sesi_ujian.created_at', today())
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        // Batch: cheating count per sekolah (1 query)
        $cheatingMap = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
            ->whereDate('log_aktivitas_ujian.created_at', today())
            ->join('sesi_peserta', 'log_aktivitas_ujian.sesi_peserta_id', '=', 'sesi_peserta.id')
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        $sekolahData = $sekolahList->map(function ($s) use ($sesiAktifMap, $pesertaOnlineMap, $pesertaSelesaiMap, $cheatingMap) {
            $sesiAktif = $sesiAktifMap[$s->id] ?? 0;
            $pesertaOnline = $pesertaOnlineMap[$s->id] ?? 0;
            $pesertaSelesai = $pesertaSelesaiMap[$s->id] ?? 0;
            $cheatingCount = $cheatingMap[$s->id] ?? 0;
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
     * Get sesi detail stats only (lightweight, for polling API).
     */
    public function getSesiStats(string $sesiId): array
    {
        $sesi = SesiUjian::findOrFail($sesiId);

        // Stats from single aggregate — no peserta data loaded
        $statsRaw = SesiPeserta::where('sesi_id', $sesi->id)
            ->whereHas('peserta')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as `online`,
                COUNT(CASE WHEN status IN ('submit','dinilai') THEN 1 END) as submit,
                COUNT(CASE WHEN status IN ('terdaftar','belum_login') THEN 1 END) as belum_mulai
            ")
            ->first();

        return [
            'stats' => [
                'total'       => $statsRaw->total ?? 0,
                'online'      => $statsRaw->online ?? 0,
                'submit'      => $statsRaw->submit ?? 0,
                'belum_mulai' => $statsRaw->belum_mulai ?? 0,
            ],
        ];
    }
}
