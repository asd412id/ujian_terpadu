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
        int $perPage = 20,
        ?string $createdBy = null,
        ?bool $isVerified = null
    ): LengthAwarePaginator {
        return $this->model
            ->with(['kategori', 'sekolah', 'pembuat'])
            ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            ->when($tipe, fn ($q) => $q->where('tipe_soal', $tipe))
            ->when($kesulitan, fn ($q) => $q->where('tingkat_kesulitan', $kesulitan))
            ->when($search, fn ($q) => $q->where('pertanyaan', 'like', "%{$search}%"))
            ->when($createdBy, fn ($q) => $q->where('created_by', $createdBy))
            ->when($isVerified !== null, fn ($q) => $q->where('is_verified', $isVerified))
            ->orderByRaw('COALESCE(nomor_urut_import, 999999) ASC')
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

    /**
     * Get valid soal IDs from a list (for validation).
     */
    public function getValidIds(array $ids): array
    {
        return $this->model->whereIn('id', $ids)->pluck('id')->flip()->all();
    }

    /**
     * Get filtered active bank soal for the paket soal picker.
     */
    public function getBankSoalFiltered(array $filters): mixed
    {
        $query = $this->model->with('kategori')
            ->where('is_active', true);

        if (!empty($filters['search'])) {
            $query->where('pertanyaan', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['jenis'])) {
            $query->where('tipe_soal', $filters['jenis']);
        }
        if (!empty($filters['kategori'])) {
            $query->where('kategori_id', $filters['kategori']);
        }

        $query->orderBy('kategori_id')->orderByRaw('COALESCE(nomor_urut_import, 999999) ASC')->orderBy('created_at');

        if (!empty($filters['all'])) {
            return ['type' => 'all', 'data' => $query->get()];
        }

        return ['type' => 'paginated', 'data' => $query->paginate(50)];
    }

    /**
     * Chunk soal with opsiJawaban and pasangan relations.
     */
    public function chunkWithRelations(int $size, callable $callback): void
    {
        $this->model->with(['opsiJawaban', 'pasangan'])->chunk($size, $callback);
    }

    /**
     * Chunk soal by kategori with relations.
     */
    public function chunkByKategoriWithRelations(string $kategoriId, int $size, callable $callback): void
    {
        $this->model->where('kategori_id', $kategoriId)
            ->with(['opsiJawaban', 'pasangan'])
            ->chunk($size, $callback);
    }

    /**
     * Delete all soal records (soft-delete).
     */
    public function deleteAll(): void
    {
        $this->model->newQuery()->delete();
    }

    /**
     * Delete soal by kategori (soft-delete).
     */
    public function deleteByKategori(string $kategoriId): void
    {
        $this->model->where('kategori_id', $kategoriId)->delete();
    }

    /**
     * Create an import job for soal and dispatch appropriate job.
     */
    public function createImportJob(array $data): \App\Models\ImportJob
    {
        $job = \App\Models\ImportJob::create($data);

        if ($data['tipe'] === 'soal_word') {
            dispatch(new \App\Jobs\ImportSoalWordJob($job));
        }

        return $job;
    }

    /**
     * Get import jobs by user (for soal imports).
     */
    public function getImportJobsByUser(string $userId, int $limit = 10): mixed
    {
        return \App\Models\ImportJob::where('created_by', $userId)
            ->whereIn('tipe', ['soal_excel', 'soal_word'])
            ->latest()
            ->take($limit)
            ->get();
    }
}
