<?php

namespace App\Services;

use App\Models\KategoriSoal;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Validation\ValidationException;

class KategoriSoalService
{
    public function __construct(
        protected KategoriSoalRepository $repository
    ) {}

    /**
     * Get all kategori soal with soal count, paginated.
     */
    public function getAllPaginated(int $perPage = 30): mixed
    {
        return $this->repository->getAll($perPage);
    }

    /**
     * Get active kategori (for dropdowns).
     */
    public function getActive(): mixed
    {
        return $this->repository->getActive();
    }

    /**
     * Get a single kategori soal by ID.
     */
    public function getById(string $id): ?KategoriSoal
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new kategori soal.
     */
    public function createKategori(array $data): KategoriSoal
    {
        return $this->repository->create($data);
    }

    /**
     * Update an existing kategori soal.
     */
    public function updateKategori(KategoriSoal $kategori, array $data): KategoriSoal
    {
        $this->repository->update($kategori, $data);
        return $kategori;
    }

    /**
     * Soft-delete (deactivate) a kategori soal.
     */
    public function deleteKategori(KategoriSoal $kategori): bool
    {
        return $this->repository->delete($kategori);
    }
}
