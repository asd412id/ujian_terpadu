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
     * Get active sesi ujian (status = berlangsung) with relations.
     */
    public function getSesiUjianAktif(): Collection
    {
        return SesiUjian::with(['paket.sekolah', 'pengawas', 'sesiPeserta'])
            ->where('status', 'berlangsung')
            ->latest()
            ->get();
    }

    /**
     * Get active sesi ujian for API (minimal relations).
     */
    public function getSesiUjianAktifForApi(): Collection
    {
        return SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung')
            ->get();
    }

    /**
     * Get active sesi ujian by sekolah.
     */
    public function getSesiAktifBySekolah(string $sekolahId): Collection
    {
        return SesiUjian::with(['paket', 'pengawas', 'sesiPeserta.peserta'])
            ->where('status', 'berlangsung')
            ->whereHas('paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->get();
    }

    /**
     * Get peserta list by sesi ID with relations.
     */
    public function getPesertaBySesi(string $sesiId): Collection
    {
        return SesiPeserta::with(['peserta', 'jawaban'])
            ->where('sesi_id', $sesiId)
            ->get();
    }

    /**
     * Get sesi with full detail for monitoring.
     */
    public function getSesiWithDetail(string $sesiId): ?SesiUjian
    {
        return SesiUjian::with(['paket.sekolah', 'sesiPeserta.peserta', 'sesiPeserta.jawaban'])
            ->find($sesiId);
    }

    /**
     * Get sesi with peserta ordered by updated_at (for API).
     */
    public function getSesiWithPesertaForApi(string $sesiId): ?SesiUjian
    {
        return SesiUjian::with([
            'paket',
            'sesiPeserta' => fn ($q) => $q->with('peserta')->orderBy('updated_at', 'desc'),
        ])->find($sesiId);
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
     * Count peserta online (hadir/mengerjakan) in active sesi.
     */
    public function countPesertaOnline(): int
    {
        return SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->count();
    }

    /**
     * Count peserta who submitted today.
     */
    public function countPesertaSelesaiHariIni(): int
    {
        return SesiPeserta::where('status', 'selesai')
            ->whereDate('updated_at', today())
            ->count();
    }

    /**
     * Get sekolah list with peserta count and active paket info for monitoring dashboard.
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
     * Get all active sekolah with peserta count (for API).
     */
    public function getSekolahWithPesertaCount(): Collection
    {
        return Sekolah::withCount(['peserta'])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Count active sesi for a specific sekolah.
     */
    public function countSesiAktifBySekolah(string $sekolahId): int
    {
        return SesiUjian::where('status', 'berlangsung')
            ->whereHas('paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->count();
    }

    /**
     * Count peserta online for a specific sekolah.
     */
    public function countPesertaOnlineBySekolah(string $sekolahId): int
    {
        return SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung')
                ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $sekolahId)))
            ->count();
    }

    /**
     * Count peserta finished today for a specific sekolah.
     */
    public function countPesertaSelesaiBySekolah(string $sekolahId): int
    {
        return SesiPeserta::where('status', 'selesai')
            ->whereHas('sesi', fn ($q) => $q->whereDate('created_at', today())
                ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $sekolahId)))
            ->count();
    }

    /**
     * Count cheating events today for a specific sekolah.
     */
    public function countCheatingBySekolah(string $sekolahId): int
    {
        return LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
            ->whereDate('created_at', today())
            ->whereHas('sesiPeserta.sesi.paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->count();
    }

    /**
     * Get sesi for pengawas monitoring with peserta and logs.
     */
    public function getSesiForPengawas(string $sesiId): ?SesiUjian
    {
        return SesiUjian::with(['paket', 'sesiPeserta.peserta', 'sesiPeserta.logAktivitas'])
            ->find($sesiId);
    }

    /**
     * Get ruang/sesi assigned to a pengawas.
     */
    public function getRuangByPengawas(string $pengawasId): Collection
    {
        return SesiUjian::with(['paket', 'sesiPeserta'])
            ->where('pengawas_id', $pengawasId)
            ->where('status', 'berlangsung')
            ->get();
    }

    /**
     * Get peserta in a specific ruang/sesi.
     */
    public function getPesertaByRuang(string $sesiId): Collection
    {
        return SesiPeserta::with(['peserta', 'logAktivitas'])
            ->where('sesi_id', $sesiId)
            ->get();
    }

    /**
     * Get statistik ujian summary.
     */
    public function getStatistikUjian(): array
    {
        $sesiAktif = SesiUjian::where('status', 'berlangsung')->get();

        return [
            'total_sesi'     => $sesiAktif->count(),
            'peserta_online' => $this->countPesertaOnline(),
            'peserta_ragu'   => 0,
            'sudah_submit'   => $this->countPesertaSelesaiHariIni(),
        ];
    }

    /**
     * Count jawaban for a sesi peserta.
     */
    public function countJawabanBySesiPeserta(string $sesiPesertaId): int
    {
        return \App\Models\JawabanPeserta::where('sesi_peserta_id', $sesiPesertaId)->count();
    }
}
