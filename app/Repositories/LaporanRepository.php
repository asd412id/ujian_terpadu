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
        $query = SesiPeserta::with(['peserta', 'sesi.paket'])
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
     * Get statistik (rekap) for a specific paket ujian (single aggregate query).
     */
    public function getStatistik(string $paketId): array
    {
        $row = SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->selectRaw('
                COUNT(*) as total_peserta,
                ROUND(AVG(nilai_akhir), 1) as rata_rata,
                MAX(nilai_akhir) as nilai_max,
                MIN(nilai_akhir) as nilai_min,
                SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
                SUM(CASE WHEN nilai_akhir < 70 THEN 1 ELSE 0 END) as tidak_lulus
            ')->first();

        return [
            'total_peserta' => (int) ($row->total_peserta ?? 0),
            'rata_rata'     => (float) ($row->rata_rata ?? 0),
            'nilai_max'     => (float) ($row->nilai_max ?? 0),
            'nilai_min'     => (float) ($row->nilai_min ?? 0),
            'lulus'         => (int) ($row->lulus ?? 0),
            'tidak_lulus'   => (int) ($row->tidak_lulus ?? 0),
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
        return Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get list of all paket ujian for filter dropdowns.
     */
    public function getPaketList(): Collection
    {
        return PaketUjian::orderBy('nama')->get(['id', 'nama']);
    }
}
