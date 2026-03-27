<?php

namespace App\Repositories;

use App\Models\KategoriSoal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class KategoriSoalRepository
{
    public function __construct(
        protected KategoriSoal $model
    ) {}

    /**
     * Get all kategori with soal count, paginated.
     */
    public function getAll(int $perPage = 30): LengthAwarePaginator
    {
        return $this->model
            ->withCount('soal')
            ->orderBy('urutan')
            ->paginate($perPage);
    }

    /**
     * Get active kategori (for dropdowns/filters).
     */
    public function getActive(): Collection
    {
        return $this->model
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get();
    }

    /**
     * Find kategori by ID.
     */
    public function findById(string $id): ?KategoriSoal
    {
        return $this->model->find($id);
    }

    /**
     * Create a new kategori.
     */
    public function create(array $data): KategoriSoal
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing kategori.
     */
    public function update(KategoriSoal $kategori, array $data): bool
    {
        return $kategori->update($data);
    }

    /**
     * Hard-delete a kategori (fails if has soal).
     */
    public function delete(KategoriSoal $kategori): bool
    {
        return $kategori->delete();
    }

    /**
     * Delete all kategori that have zero soal.
     */
    public function deleteAllEmpty(): int
    {
        return $this->model
            ->whereDoesntHave('soal')
            ->delete();
    }
}
