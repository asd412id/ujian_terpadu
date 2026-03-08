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
            ->get();

        $summary = [
            'total_sesi'     => $sesiList->count(),
            'peserta_online' => SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
                ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))->count(),
            'peserta_ragu'   => 0,
            'sudah_submit'   => SesiPeserta::where('status', 'selesai')
                ->whereDate('updated_at', today())->count(),
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
     * Get peserta status for a specific sesi ujian.
     */
    public function getPesertaStatus(string $sesiId): array
    {
        $sesi = SesiUjian::with(['paket.sekolah', 'sesiPeserta.peserta', 'sesiPeserta.jawaban'])
            ->findOrFail($sesiId);

        $alerts = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit', 'koneksi_putus'])
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesi->id))
            ->with('sesiPeserta.peserta')
            ->latest()
            ->take(20)
            ->get();

        $pesertaList = $sesi->sesiPeserta;

        $stats = [
            'total'       => $pesertaList->count(),
            'online'      => $pesertaList->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'      => $pesertaList->whereIn('status', ['submit', 'dinilai'])->count(),
            'kosong'      => $pesertaList->where('status', 'belum_login')->count(),
            'belum_mulai' => $pesertaList->where('status', 'belum_login')->count(),
        ];

        return compact('sesi', 'alerts', 'pesertaList', 'stats');
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
            'belum_masuk'  => $sesi->sesiPeserta->where('status', 'belum_login')->count(),
        ];

        return compact('sesi', 'statsPeserta');
    }

    /**
     * Get peserta list by ruang/sesi for pengawas.
     */
    public function getPesertaByRuang(string $sesiId): array
    {
        $sesi = SesiUjian::with(['paket', 'sesiPeserta.peserta', 'sesiPeserta.logAktivitas'])
            ->findOrFail($sesiId);

        $statsPeserta = [
            'total'        => $sesi->sesiPeserta->count(),
            'aktif'        => $sesi->sesiPeserta->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'       => $sesi->sesiPeserta->where('status', 'submit')->count(),
            'belum_masuk'  => $sesi->sesiPeserta->where('status', 'belum_login')->count(),
        ];

        return compact('sesi', 'statsPeserta');
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

                $pesertaOnline = SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
                    ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung')
                        ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $s->id)))
                    ->count();

                $pesertaSelesai = SesiPeserta::where('status', 'selesai')
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
        $sesi = SesiUjian::with(['paket', 'sesiPeserta' => fn ($q) => $q->with('peserta')->orderBy('updated_at', 'desc')])
            ->findOrFail($sesiId);

        $data = $sesi->sesiPeserta->map(fn ($sp) => [
            'nama'          => $sp->peserta->nama ?? '–',
            'no_peserta'    => $sp->peserta->no_peserta ?? '–',
            'status'        => $sp->status,
            'soal_dijawab'  => $sp->jawaban()->count(),
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
                'total'   => $sesi->sesiPeserta->count(),
                'hadir'   => $sesi->sesiPeserta->whereIn('status', ['hadir', 'mengerjakan'])->count(),
                'selesai' => $sesi->sesiPeserta->where('status', 'selesai')->count(),
                'belum'   => $sesi->sesiPeserta->where('status', 'belum_hadir')->count(),
            ],
        ];
    }
}
