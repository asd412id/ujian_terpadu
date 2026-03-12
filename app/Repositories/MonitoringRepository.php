<?php

namespace App\Repositories;

use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Collection;

class MonitoringRepository
{
    /**
     * Get dashboard sesi list with peserta counts.
     */
    public function getDashboardSesiList(): Collection
    {
        return SesiUjian::with(['paket.sekolah', 'pengawas'])
            ->withCount([
                'sesiPeserta as total_peserta',
                'sesiPeserta as peserta_online' => fn ($q) => $q->whereIn('status', ['login', 'mengerjakan']),
                'sesiPeserta as sudah_submit' => fn ($q) => $q->whereIn('status', ['submit', 'dinilai']),
            ])
            ->where('status', 'berlangsung')
            ->latest()
            ->get();
    }

    /**
     * Get aggregated summary of active sesi peserta.
     */
    public function getDashboardSummary(): array
    {
        $raw = SesiPeserta::query()
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->selectRaw("
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as peserta_online,
                COUNT(CASE WHEN status IN ('submit','dinilai') THEN 1 END) as sudah_submit
            ")
            ->first();

        return [
            'peserta_online' => $raw->peserta_online ?? 0,
            'sudah_submit'   => $raw->sudah_submit ?? 0,
        ];
    }

    /**
     * Get active sesi ujian with optional sekolah filter.
     */
    public function getSesiAktifFiltered(array $filters = []): Collection
    {
        $query = SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung');

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('paket', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        return $query->latest()->get();
    }

    /**
     * Get sesi with paket and sekolah for detail view.
     */
    public function findSesiWithPaket(string $sesiId): SesiUjian
    {
        return SesiUjian::with(['paket.sekolah'])->findOrFail($sesiId);
    }

    /**
     * Get peserta stats aggregate for a sesi.
     */
    public function getSesiPesertaStats(string $sesiId): array
    {
        $raw = SesiPeserta::where('sesi_id', $sesiId)
            ->whereHas('peserta')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as `online`,
                COUNT(CASE WHEN status IN ('submit','dinilai') THEN 1 END) as submit,
                COUNT(CASE WHEN status IN ('terdaftar','belum_login') THEN 1 END) as belum_mulai
            ")
            ->first();

        return [
            'total'       => $raw->total ?? 0,
            'online'      => $raw->online ?? 0,
            'submit'      => $raw->submit ?? 0,
            'kosong'      => $raw->belum_mulai ?? 0,
            'belum_mulai' => $raw->belum_mulai ?? 0,
        ];
    }

    /**
     * Get paginated peserta list for a sesi with filters.
     */
    public function getSesiPesertaPaginated(string $sesiId, array $filters = [])
    {
        $query = SesiPeserta::with(['peserta.sekolah'])
            ->whereHas('peserta')
            ->where('sesi_id', $sesiId);

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
        return $query->orderByRaw("FIELD(status, 'mengerjakan', 'login', 'submit', 'dinilai', 'terdaftar', 'belum_login')")
            ->paginate($perPage);
    }

    /**
     * Get sekolah list that have peserta in a specific sesi.
     */
    public function getSekolahListBySesi(string $sesiId): Collection
    {
        $sekolahIds = SesiPeserta::where('sesi_id', $sesiId)
            ->join('peserta', 'sesi_peserta.peserta_id', '=', 'peserta.id')
            ->distinct()
            ->pluck('peserta.sekolah_id');

        return Sekolah::whereIn('id', $sekolahIds)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get active sekolah list (for filter dropdown).
     */
    public function getActiveSekolahList(): Collection
    {
        return Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get sekolah monitoring data with batch queries.
     */
    public function getSekolahMonitoringBatch(): array
    {
        $sekolahList = Sekolah::withCount(['peserta'])
            ->where('is_active', true)
            ->get();

        $sekolahIds = $sekolahList->pluck('id');

        $sesiAktifMap = SesiUjian::where('status', 'berlangsung')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        $pesertaOnlineMap = SesiPeserta::whereIn('sesi_peserta.status', ['login', 'mengerjakan'])
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->where('sesi_ujian.status', 'berlangsung')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        $pesertaSelesaiMap = SesiPeserta::where('sesi_peserta.status', 'submit')
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereDate('sesi_ujian.created_at', today())
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        $cheatingMap = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
            ->whereDate('log_aktivitas_ujian.created_at', today())
            ->join('sesi_peserta', 'log_aktivitas_ujian.sesi_peserta_id', '=', 'sesi_peserta.id')
            ->join('sesi_ujian', 'sesi_peserta.sesi_id', '=', 'sesi_ujian.id')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereIn('paket_ujian.sekolah_id', $sekolahIds)
            ->selectRaw('paket_ujian.sekolah_id, COUNT(*) as cnt')
            ->groupBy('paket_ujian.sekolah_id')
            ->pluck('cnt', 'sekolah_id');

        return compact('sekolahList', 'sesiAktifMap', 'pesertaOnlineMap', 'pesertaSelesaiMap', 'cheatingMap');
    }

    /**
     * Get sesi for pengawas with counts.
     */
    public function getRuangForPengawas(string $pengawasId): ?SesiUjian
    {
        return SesiUjian::with(['paket'])
            ->withCount([
                'sesiPeserta as total_peserta',
                'sesiPeserta as peserta_aktif' => fn ($q) => $q->whereIn('status', ['login', 'mengerjakan']),
                'sesiPeserta as peserta_submit' => fn ($q) => $q->where('status', 'submit'),
                'sesiPeserta as peserta_belum' => fn ($q) => $q->whereIn('status', ['terdaftar', 'belum_login']),
            ])
            ->where('pengawas_id', $pengawasId)
            ->whereIn('status', ['persiapan', 'berlangsung'])
            ->first();
    }

    /**
     * Get peserta stats for a sesi (simple aggregate without whereHas peserta).
     */
    public function getSesiPesertaStatsSimple(string $sesiId): array
    {
        $raw = SesiPeserta::where('sesi_id', $sesiId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('login','mengerjakan') THEN 1 END) as aktif,
                COUNT(CASE WHEN status = 'submit' THEN 1 END) as submit,
                COUNT(CASE WHEN status IN ('terdaftar','belum_login') THEN 1 END) as belum_masuk
            ")
            ->first();

        return [
            'total'        => (int) ($raw->total ?? 0),
            'aktif'        => (int) ($raw->aktif ?? 0),
            'submit'       => (int) ($raw->submit ?? 0),
            'belum_masuk'  => (int) ($raw->belum_masuk ?? 0),
        ];
    }

    /**
     * Get paginated peserta for pengawas ruang view.
     */
    public function getPesertaByRuangPaginated(string $sesiId, array $filters = [])
    {
        $query = SesiPeserta::with(['peserta'])
            ->where('sesi_id', $sesiId);

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
        return $query->orderByRaw("FIELD(status, 'mengerjakan', 'login', 'submit', 'dinilai', 'terdaftar', 'belum_login')")
            ->paginate($perPage);
    }

    /**
     * Find sesi with paket by ID.
     */
    public function findSesiWithPaketById(string $sesiId): SesiUjian
    {
        return SesiUjian::with(['paket'])->findOrFail($sesiId);
    }

    /**
     * Get cheating/alert logs for a sesi.
     */
    public function getAlertsBySesi(string $sesiId, int $limit = 20): Collection
    {
        return LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit', 'koneksi_putus'])
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesiId))
            ->with('sesiPeserta.peserta')
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Get sekolah list with monitoring stats (legacy).
     */
    public function getSekolahWithMonitoringStats(): Collection
    {
        return Sekolah::withCount(['peserta'])
            ->with(['paketUjian' => fn ($q) => $q->whereHas('sesi', fn ($s) => $s->where('status', 'berlangsung'))])
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Get statistik ujian summary.
     */
    public function getStatistik(): array
    {
        $totalSesi = SesiUjian::where('status', 'berlangsung')->count();
        $summary = $this->getDashboardSummary();

        return [
            'total_sesi'     => $totalSesi,
            'peserta_online' => $summary['peserta_online'],
            'peserta_ragu'   => 0,
            'sudah_submit'   => $summary['sudah_submit'],
        ];
    }
}
