<?php

namespace App\Services;

use App\Jobs\ImportSekolahJob;
use App\Models\ImportJob;
use App\Models\Sekolah;
use App\Repositories\SekolahRepository;

class SekolahService
{
    public function __construct(
        protected SekolahRepository $repository
    ) {}

    /**
     * Get all sekolah with stats, paginated (Dinas view) with optional filters.
     */
    public function getAllPaginated(int $perPage = 20, array $filters = []): mixed
    {
        if (empty($filters['q']) && empty($filters['jenjang'])) {
            return $this->repository->getAll($perPage);
        }

        return $this->repository->getAllFiltered(
            $filters['q'] ?? null,
            $filters['jenjang'] ?? null,
            $perPage
        );
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
        $dinas = $this->repository->getDefaultDinas();
        if ($dinas) {
            $data['dinas_id'] = $dinas->id;
        }

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

    /**
     * Create an import job and dispatch the import queue.
     */
    public function createImportJob(array $data): ImportJob
    {
        $importJob = $this->repository->createImportJob($data);
        ImportSekolahJob::dispatch($importJob);
        return $importJob;
    }

    /**
     * Delete all sekolah via Eloquent cursor (triggers model events).
     */
    public function deleteAllSekolah(): int
    {
        return $this->repository->deleteAllWithCursor();
    }
}
