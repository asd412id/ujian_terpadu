<?php

namespace App\Services;

use App\Models\LogAktivitasUjian;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
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
        $sekolahList = Sekolah::withCount(['peserta'])
            ->with(['paketUjian' => fn ($q) => $q->whereHas('sesi', fn ($s) => $s->where('status', 'berlangsung'))])
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        $sesiList = SesiUjian::with(['paket.sekolah', 'pengawas', 'sesiPeserta'])
            ->where('status', 'berlangsung')
            ->latest()
            ->get()
            ->each(function ($sesi) {
                $sp = $sesi->sesiPeserta;
                $sesi->total_peserta  = $sp->count();
                $sesi->peserta_online = $sp->whereIn('status', ['login', 'mengerjakan'])->count();
                $sesi->sudah_submit   = $sp->whereIn('status', ['submit', 'dinilai'])->count();
            });

        $summary = [
            'total_sesi'     => $sesiList->count(),
            'peserta_online' => SesiPeserta::whereIn('status', ['login', 'mengerjakan'])
                ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))->count(),
            'peserta_ragu'   => 0,
            'sudah_submit'   => SesiPeserta::where('status', 'submit')
                ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))->count(),
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

        // Stats from aggregate (not paginated)
        $allPeserta = SesiPeserta::where('sesi_id', $sesi->id);
        $stats = [
            'total'       => (clone $allPeserta)->count(),
            'online'      => (clone $allPeserta)->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'      => (clone $allPeserta)->whereIn('status', ['submit', 'dinilai'])->count(),
            'kosong'      => (clone $allPeserta)->whereIn('status', ['terdaftar', 'belum_login'])->count(),
            'belum_mulai' => (clone $allPeserta)->whereIn('status', ['terdaftar', 'belum_login'])->count(),
        ];

        // Paginated peserta list with optional search/filter
        $query = SesiPeserta::with(['peserta.sekolah', 'jawaban'])
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
     */
    public function getSekolahMonitoringData(): array
    {
        $sekolahList = Sekolah::withCount(['peserta'])
            ->where('is_active', true)
            ->get()
            ->map(function ($s) {
                $sesiAktif = SesiUjian::where('status', 'berlangsung')
                    ->whereHas('paket', fn ($q) => $q->where('sekolah_id', $s->id))
                    ->count();

                $pesertaOnline = SesiPeserta::whereIn('status', ['login', 'mengerjakan'])
                    ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung')
                        ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $s->id)))
                    ->count();

                $pesertaSelesai = SesiPeserta::where('status', 'submit')
                    ->whereHas('sesi', fn ($q) => $q->whereDate('created_at', today())
                        ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $s->id)))
                    ->count();

                $cheatingCount = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
                    ->whereDate('created_at', today())
                    ->whereHas('sesiPeserta.sesi.paket', fn ($q) => $q->where('sekolah_id', $s->id))
                    ->count();

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
            'total_sekolah'       => $sekolahList->count(),
            'sekolah_aktif'       => $sekolahList->where('status', 'aktif')->count(),
            'total_peserta_aktif' => $sekolahList->sum('peserta_online'),
            'total_selesai'       => $sekolahList->sum('peserta_selesai'),
        ];

        return [
            'sekolah' => $sekolahList->values()->all(),
            'summary' => $summary,
        ];
    }

    /**
     * Get sesi detail data for API.
     */
    public function getSesiDetail(string $sesiId): array
    {
        $sesi = SesiUjian::with(['paket', 'sesiPeserta' => fn ($q) => $q->with('peserta.sekolah')->orderBy('updated_at', 'desc')])
            ->findOrFail($sesiId);

        $data = $sesi->sesiPeserta->map(fn ($sp) => [
            'nama'          => $sp->peserta->nama ?? '–',
            'no_peserta'    => $sp->peserta->no_peserta ?? '–',
            'sekolah'       => $sp->peserta->sekolah?->nama ?? '–',
            'kelas'         => $sp->peserta->kelas ?? '–',
            'status'        => $sp->status,
            'soal_dijawab'  => $sp->jawaban()->count(),
            'nilai_akhir'   => $sp->nilai_akhir,
            'last_aktif'    => $sp->updated_at?->diffForHumans(),
        ]);

        return [
            'sesi' => [
                'id'             => $sesi->id,
                'nama'           => $sesi->nama_sesi,
                'status'         => $sesi->status,
                'waktu_mulai'    => $sesi->waktu_mulai?->format('H:i'),
                'waktu_selesai'  => $sesi->waktu_selesai?->format('H:i'),
            ],
            'peserta' => $data,
            'stats' => [
                'total'       => $sesi->sesiPeserta->count(),
                'online'      => $sesi->sesiPeserta->whereIn('status', ['login', 'mengerjakan'])->count(),
                'submit'      => $sesi->sesiPeserta->whereIn('status', ['submit', 'dinilai'])->count(),
                'belum_mulai' => $sesi->sesiPeserta->whereIn('status', ['terdaftar', 'belum_login'])->count(),
            ],
        ];
    }
}
