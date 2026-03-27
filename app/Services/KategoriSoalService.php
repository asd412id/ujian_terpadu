<?php

namespace App\Services;

use App\Models\KategoriSoal;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Support\Facades\DB;
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
     * Hard-delete a kategori soal.
     * Throws ValidationException if kategori still has soal.
     */
    public function deleteKategori(KategoriSoal $kategori): bool
    {
        $soalCount = $kategori->soal()->count();
        if ($soalCount > 0) {
            throw ValidationException::withMessages([
                'kategori' => "Kategori \"{$kategori->nama}\" masih memiliki {$soalCount} soal. Hapus atau pindahkan soal terlebih dahulu.",
            ]);
        }

        return $this->repository->delete($kategori);
    }

    /**
     * Delete all kategori that have zero soal.
     * Returns the count of deleted kategori.
     */
    public function deleteAllEmptyKategoris(): int
    {
        return $this->repository->deleteAllEmpty();
    }
}
