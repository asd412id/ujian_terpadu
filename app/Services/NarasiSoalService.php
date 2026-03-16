<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Repositories\NarasiSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NarasiSoalService
{
    public function __construct(
        protected NarasiSoalRepository $repository
    ) {}

    /**
     * Get filtered & paginated narasi.
     */
    public function getAllPaginated(
        ?string $kategoriId = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->repository->getFiltered($kategoriId, $search, $perPage);
    }

    /**
     * Get active narasi for dropdown select.
     */
    public function getActive(?string $kategoriId = null): Collection
    {
        return $this->repository->getActive($kategoriId);
    }

    /**
     * Find narasi by ID.
     */
    public function getById(string $id): ?NarasiSoal
    {
        return $this->repository->findById($id);
    }

    /**
     * Find narasi with all relations.
     */
    public function getWithRelations(string $id): ?NarasiSoal
    {
        return $this->repository->findWithRelations($id);
    }

    /**
     * Create a new narasi.
     */
    public function createNarasi(array $data): NarasiSoal
    {
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        return $this->repository->create($data);
    }

    /**
     * Update an existing narasi.
     */
    public function updateNarasi(NarasiSoal $narasi, array $data): bool
    {
        return $this->repository->update($narasi, $data);
    }

    /**
     * Delete a narasi (soft-delete).
     */
    public function deleteNarasi(NarasiSoal $narasi): ?bool
    {
        return $this->repository->delete($narasi);
    }
}
