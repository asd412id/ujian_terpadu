<?php

namespace App\Repositories;

use App\Models\DinasPendidikan;
use App\Models\ImportJob;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SekolahRepository
{
    public function __construct(
        protected Sekolah $model
    ) {}

    /**
     * Get all sekolah with stats, paginated (with optional filters).
     */
    public function getAll(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('is_active', true)
            ->with('dinas')
            ->withCount(['peserta', 'soal'])
            ->orderBy('nama')
            ->paginate($perPage);
    }

    /**
     * Get filtered + paginated sekolah (for dinas index with search & jenjang filter).
     */
    public function getAllFiltered(
        ?string $search = null,
        ?string $jenjang = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->model
            ->where('is_active', true)
            ->withCount(['peserta', 'soal'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('npsn', 'like', "%{$search}%")
                  ->orWhere('kota', 'like', "%{$search}%");
            }))
            ->when($jenjang, fn ($q) => $q->where('jenjang', $jenjang))
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get filtered sekolah (for dropdowns, filters, etc.).
     */
    public function getFiltered(bool $activeOnly = true): Collection
    {
        $query = $this->model->query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('nama')->get(['id', 'nama', 'npsn', 'jenjang', 'is_active']);
    }

    /**
     * Find sekolah by ID.
     */
    public function findById(string $id): ?Sekolah
    {
        return $this->model->find($id);
    }

    /**
     * Find sekolah with related data for detail view.
     */
    public function findWithStats(string $id): ?Sekolah
    {
        return $this->model
            ->with(['peserta', 'paketUjian.sesi'])
            ->find($id);
    }

    /**
     * Create a new sekolah.
     */
    public function create(array $data): Sekolah
    {
        return $this->model->create($data);
    }

    /**
     * Update a sekolah.
     */
    public function update(Sekolah $sekolah, array $data): bool
    {
        return $sekolah->update($data);
    }

    /**
     * Soft-delete (deactivate) a sekolah.
     */
    public function delete(Sekolah $sekolah): bool
    {
        return $sekolah->update(['is_active' => false]);
    }

    /**
     * Get sekolah with monitoring stats (active sekolah with peserta count).
     */
    public function getWithStats(): Collection
    {
        return $this->model
            ->withCount(['peserta'])
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Count all sekolah records.
     */
    public function countAll(): int
    {
        return $this->model->count();
    }

    /**
     * Delete all sekolah via cursor (triggers model events).
     */
    public function deleteAllWithCursor(): int
    {
        $count = $this->model->count();
        foreach ($this->model->cursor() as $sekolah) {
            $sekolah->delete();
        }
        return $count;
    }

    /**
     * Get all sekolah ordered by name (for dropdowns).
     */
    public function getAllOrdered(array $columns = ['id', 'nama', 'jenjang']): Collection
    {
        return $this->model->orderBy('nama')->get($columns);
    }

    /**
     * Get active sekolah ordered by name (for dropdowns).
     */
    public function getActiveOrdered(array $columns = ['id', 'nama', 'jenjang']): Collection
    {
        return $this->model->where('is_active', true)->orderBy('nama')->get($columns);
    }

    /**
     * Get sekolah filtered by paket's jenjang and sekolah_id.
     */
    public function getForPaket(?string $jenjang, ?string $sekolahId): Collection
    {
        return $this->model
            ->when($jenjang && strtoupper($jenjang) !== 'SEMUA',
                fn($q) => $q->where('jenjang', $jenjang))
            ->when($sekolahId, fn($q) => $q->where('id', $sekolahId))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    /**
     * Get the default DinasPendidikan record.
     */
    public function getDefaultDinas(): ?DinasPendidikan
    {
        return DinasPendidikan::first();
    }

    /**
     * Create an import job record.
     */
    public function createImportJob(array $data): ImportJob
    {
        return ImportJob::create($data);
    }
}
