<?php

namespace App\Repositories;

use App\Models\PaketUjian;
use App\Models\SesiPeserta;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanRepository
{
    /**
     * Get hasil ujian filtered by sekolah and optionally by paket.
     */
    public function getHasilUjian(
        string $sekolahId,
        ?string $paketId = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        $query = SesiPeserta::with(['peserta', 'sesi.paket', 'jawaban'])
            ->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('status', ['submit', 'dinilai']);

        if ($paketId) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId));
        }

        return $query->paginate($perPage);
    }

    /**
     * Get hasil ujian by sekolah (all submitted/graded).
     */
    public function getHasilBySekolah(string $sekolahId): Collection
    {
        return SesiPeserta::with(['peserta', 'sesi.paket'])
            ->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->get();
    }

    /**
     * Get hasil ujian by paket.
     */
    public function getHasilByPaket(string $paketId): Collection
    {
        return SesiPeserta::with(['peserta', 'sesi'])
            ->whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->get();
    }

    /**
     * Get statistik (rekap) for a specific paket ujian.
     */
    public function getStatistik(string $paketId): array
    {
        $sesiPeserta = SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->get();

        return [
            'total_peserta' => $sesiPeserta->count(),
            'rata_rata'     => $sesiPeserta->avg('nilai_akhir') ?? 0,
            'nilai_max'     => $sesiPeserta->max('nilai_akhir') ?? 0,
            'nilai_min'     => $sesiPeserta->min('nilai_akhir') ?? 0,
            'lulus'         => $sesiPeserta->where('nilai_akhir', '>=', 70)->count(),
            'tidak_lulus'   => $sesiPeserta->where('nilai_akhir', '<', 70)->count(),
        ];
    }

    /**
     * Get rekap nilai for reporting (all sekolah).
     */
    public function getRekapNilai(?string $sekolahId = null, ?string $paketId = null): Collection
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket'])
            ->whereIn('status', ['submit', 'dinilai']);

        if ($sekolahId) {
            $query->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $sekolahId));
        }

        if ($paketId) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId));
        }

        return $query->get();
    }

    /**
     * Get detail nilai for a specific peserta in a sesi.
     */
    public function getDetailNilaiPeserta(string $sesiPesertaId): ?SesiPeserta
    {
        return SesiPeserta::with(['peserta', 'sesi.paket', 'jawaban.soal'])
            ->find($sesiPesertaId);
    }

    /**
     * Get list of all active sekolah for filter dropdowns.
     */
    public function getSekolahList(): Collection
    {
        return Sekolah::where('is_active', true)->orderBy('nama')->get();
    }

    /**
     * Get list of all paket ujian for filter dropdowns.
     */
    public function getPaketList(): Collection
    {
        return PaketUjian::orderBy('nama')->get();
    }
}
