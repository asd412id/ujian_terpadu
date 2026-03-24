<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Repositories\NarasiSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mews\Purifier\Facades\Purifier;

class NarasiSoalService
{
    public function __construct(
        protected NarasiSoalRepository $repository
    ) {}

    public function getAllPaginated(
        ?string $kategoriId = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->repository->getFiltered($kategoriId, $search, $perPage);
    }

    public function getActive(?string $kategoriId = null): Collection
    {
        return $this->repository->getActive($kategoriId);
    }

    public function getById(string $id): ?NarasiSoal
    {
        return $this->repository->findById($id);
    }

    public function getWithRelations(string $id): ?NarasiSoal
    {
        return $this->repository->findWithRelations($id);
    }

    public function createNarasi(array $data): NarasiSoal
    {
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        if (isset($data['konten'])) {
            $data['konten'] = Purifier::clean($data['konten'], 'tiptap');
        }

        return $this->repository->create($data);
    }

    public function updateNarasi(NarasiSoal $narasi, array $data): bool
    {
        if (isset($data['konten'])) {
            $data['konten'] = Purifier::clean($data['konten'], 'tiptap');
        }

        return $this->repository->update($narasi, $data);
    }

    public function deleteNarasi(NarasiSoal $narasi): ?bool
    {
        return $this->repository->delete($narasi);
    }
}