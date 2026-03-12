<?php

namespace App\Repositories;

use App\Models\JawabanPeserta;
use App\Models\PaketUjian;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GradingRepository
{
    public function __construct(
        protected JawabanPeserta $model
    ) {}

    /**
     * Update nilai (skor_manual) for a jawaban.
     */
    public function updateNilai(JawabanPeserta $jawaban, array $data): bool
    {
        return $jawaban->update($data);
    }

    /**
     * Get paket list for filter dropdown.
     */
    public function getPaketList(): Collection
    {
        return PaketUjian::orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get sekolah list for filter dropdown.
     */
    public function getSekolahList(): Collection
    {
        return Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get pending grading with submitted status filter (for service).
     */
    public function getPendingGradingFiltered(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['soal.kategori', 'sesiPeserta.peserta.sekolah', 'sesiPeserta.sesi.paket'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereHas('sesiPeserta', fn ($q) => $q->whereIn('status', ['submit', 'dinilai']))
            ->whereNull('skor_manual')
            ->where(function ($q) {
                $q->whereNotNull('jawaban_teks')
                  ->where('jawaban_teks', '!=', '');
            });

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesiPeserta.sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('sesiPeserta.peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    /**
     * Find jawaban or fail.
     */
    public function findJawabanOrFail(string $id): JawabanPeserta
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Get grading stats with optimized single aggregate query.
     */
    public function getGradingStatsOptimized(): array
    {
        $row = $this->model
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->selectRaw('
                COUNT(CASE WHEN jawaban_teks IS NOT NULL THEN 1 END) as total_essay,
                COUNT(CASE WHEN skor_manual IS NOT NULL THEN 1 END) as sudah_dinilai,
                COUNT(CASE WHEN skor_manual IS NULL AND jawaban_teks IS NOT NULL AND jawaban_teks != \'\' THEN 1 END) as belum_dinilai
            ')
            ->first();

        $totalEssay = (int) ($row->total_essay ?? 0);
        $sudahDinilai = (int) ($row->sudah_dinilai ?? 0);

        return [
            'total_essay'    => $totalEssay,
            'sudah_dinilai'  => $sudahDinilai,
            'belum_dinilai'  => (int) ($row->belum_dinilai ?? 0),
            'progress_pct'   => $totalEssay > 0 ? round(($sudahDinilai / $totalEssay) * 100, 1) : 0,
        ];
    }

    /**
     * Get essay jawaban by sesi ID.
     */
    public function getEssayBySesi(string $sesiId): Collection
    {
        return $this->model
            ->with(['soal.kategori', 'sesiPeserta.peserta'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesiId))
            ->whereNotNull('jawaban_teks')
            ->get();
    }
}
