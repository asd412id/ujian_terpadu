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
     * Get pending essay grading (jawaban essay belum dinilai manual).
     */
    public function getPendingGrading(
        ?string $paketId = null,
        ?string $sekolahId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->model
            ->with(['soal.kategori', 'sesiPeserta.peserta.sekolah', 'sesiPeserta.sesi.paket'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNull('skor_manual')
            ->whereNotNull('jawaban_teks');

        if ($paketId) {
            $query->whereHas('sesiPeserta.sesi', fn ($q) => $q->where('paket_id', $paketId));
        }

        if ($sekolahId) {
            $query->whereHas('sesiPeserta.peserta', fn ($q) => $q->where('sekolah_id', $sekolahId));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get all essay jawaban (regardless of grading status).
     */
    public function getEssayJawaban(?string $paketId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['soal.kategori', 'sesiPeserta.peserta'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNotNull('jawaban_teks');

        if ($paketId) {
            $query->whereHas('sesiPeserta.sesi', fn ($q) => $q->where('paket_id', $paketId));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find a specific jawaban by ID.
     */
    public function findJawabanById(string $id): ?JawabanPeserta
    {
        return $this->model->find($id);
    }

    /**
     * Update nilai (skor_manual) for a jawaban.
     */
    public function updateNilai(JawabanPeserta $jawaban, array $data): bool
    {
        return $jawaban->update($data);
    }

    /**
     * Get grading statistics (total ungraded essay count).
     */
    public function getGradingStats(): array
    {
        $totalBelumDinilai = $this->model
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNull('skor_manual')
            ->whereNotNull('jawaban_teks')
            ->count();

        $totalSudahDinilai = $this->model
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNotNull('skor_manual')
            ->count();

        return [
            'belum_dinilai' => $totalBelumDinilai,
            'sudah_dinilai' => $totalSudahDinilai,
            'total_essay'   => $totalBelumDinilai + $totalSudahDinilai,
        ];
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
}
