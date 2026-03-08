<?php

namespace App\Services;

use App\Models\DinasPendidikan;
use App\Models\Sekolah;
use App\Repositories\SekolahRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SekolahService
{
    public function __construct(
        protected SekolahRepository $repository
    ) {}

    /**
     * Get all sekolah with stats, paginated (Dinas view).
     */
    public function getAllPaginated(int $perPage = 20): mixed
    {
        return $this->repository->getAll($perPage);
    }

    /**
     * Get a single sekolah by ID with stats for detail view.
     */
    public function getById(string $id): ?Sekolah
    {
        return $this->repository->findWithStats($id);
    }

    /**
     * Create a new sekolah.
     */
    public function createSekolah(array $data): Sekolah
    {
        $dinas = DinasPendidikan::first();
        $data['dinas_id'] = $dinas->id;

        return $this->repository->create($data);
    }

    /**
     * Update an existing sekolah.
     */
    public function updateSekolah(Sekolah $sekolah, array $data): Sekolah
    {
        $this->repository->update($sekolah, $data);
        return $sekolah;
    }

    /**
     * Soft-delete (deactivate) a sekolah.
     */
    public function deleteSekolah(Sekolah $sekolah): bool
    {
        return $this->repository->delete($sekolah);
    }

    /**
     * Get active sekolah for filters/dropdowns.
     */
    public function getActiveSekolahs(): mixed
    {
        return $this->repository->getFiltered(true);
    }

    /**
     * Get all sekolah with statistics (peserta count, paket count, etc.).
     */
    public function getWithStats(): mixed
    {
        return $this->repository->getWithStats();
    }
}
