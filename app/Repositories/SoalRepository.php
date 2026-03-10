<?php

namespace App\Repositories;

use App\Models\Soal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use App\Models\ImportJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SoalRepository
{
    public function __construct(
        protected Soal $model
    ) {}

    /**
     * Get filtered & paginated soal for Dinas (all soal).
     */
    public function getFilteredSoal(
        ?string $kategoriId = null,
        ?string $tipe = null,
        ?string $kesulitan = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->model
            ->with(['kategori', 'sekolah', 'pembuat'])
            ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            ->when($tipe, fn ($q) => $q->where('tipe_soal', $tipe))
            ->when($kesulitan, fn ($q) => $q->where('tingkat_kesulitan', $kesulitan))
            ->when($search, fn ($q) => $q->where('pertanyaan', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Find a single soal by ID.
     */
    public function findById(string $id): ?Soal
    {
        return $this->model->find($id);
    }

    /**
     * Find soal with its options and pairs loaded.
     */
    public function findWithRelations(string $id, array $relations = ['opsiJawaban', 'pasangan', 'kategori']): ?Soal
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * Create a new soal record.
     */
    public function create(array $data): Soal
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing soal.
     */
    public function update(Soal $soal, array $data): bool
    {
        return $soal->update($data);
    }

    /**
     * Delete a soal.
     */
    public function delete(Soal $soal): ?bool
    {
        return $soal->delete();
    }

    /**
     * Get soal by kategori ID.
     */
    public function getByKategori(string $kategoriId): Collection
    {
        return $this->model->where('kategori_id', $kategoriId)->get();
    }

    /**
     * Get active soal NOT in a specific paket (for bank soal selection).
     */
    public function getByPaketUjian(string $paketId, array $excludeSoalIds = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->with('kategori')
            ->where('is_active', true)
            ->whereNotIn('id', $excludeSoalIds)
            ->paginate($perPage, ['*'], 'soal_page');
    }

    /**
     * Create an import job record for soal import.
     */
    public function createImportJob(array $data): ImportJob
    {
        return ImportJob::create($data);
    }

    /**
     * Get import jobs by user (for Dinas).
     */
    public function getImportJobsByUser(string $userId, int $limit = 10): Collection
    {
        return ImportJob::where('created_by', $userId)
            ->whereIn('tipe', ['soal_excel', 'soal_word'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Save opsi jawaban for a soal (PG / PG Kompleks).
     */
    public function saveOpsiJawaban(Soal $soal, array $opsiData): void
    {
        foreach ($opsiData as $opsi) {
            OpsiJawaban::create(array_merge(['soal_id' => $soal->id], $opsi));
        }
    }

    /**
     * Save pasangan soal (menjodohkan).
     */
    public function savePasangan(Soal $soal, array $pasanganData): void
    {
        foreach ($pasanganData as $pasangan) {
            PasanganSoal::create(array_merge(['soal_id' => $soal->id], $pasangan));
        }
    }

    /**
     * Delete all opsi jawaban for a soal.
     */
    public function deleteOpsiJawaban(Soal $soal): void
    {
        $soal->opsiJawaban()->delete();
    }

    /**
     * Delete all pasangan for a soal.
     */
    public function deletePasangan(Soal $soal): void
    {
        $soal->pasangan()->delete();
    }
}
