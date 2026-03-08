<?php

namespace App\Repositories;

use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SekolahRepository
{
    public function __construct(
        protected Sekolah $model
    ) {}

    /**
     * Get all sekolah with stats, paginated.
     */
    public function getAll(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with('dinas')
            ->withCount(['peserta', 'soal'])
            ->orderBy('nama')
            ->paginate($perPage);
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

        return $query->orderBy('nama')->get();
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
}
