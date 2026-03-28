<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Models\Soal;
use App\Repositories\NarasiSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    public function deleteNarasi(NarasiSoal $narasi, ?string $soalCreatedBy = null): ?bool
    {
        return DB::transaction(function () use ($narasi, $soalCreatedBy) {
            $this->deleteSoalByNarasiIds([$narasi->id], $soalCreatedBy);

            return $this->repository->delete($narasi);
        });
    }

    public function deleteAllNarasi(?string $kategoriId = null, ?string $createdBy = null): int
    {
        return DB::transaction(function () use ($kategoriId, $createdBy) {
            $narasis = $this->repository->getForBulkDelete($kategoriId, $createdBy);

            if ($narasis->isEmpty()) {
                return 0;
            }

            $this->deleteSoalByNarasiIds($narasis->pluck('id')->all(), $createdBy);
            $this->repository->deleteByIds($narasis->pluck('id'));

            return $narasis->count();
        });
    }

    /**
     * Soft-delete all soal associated with the given narasi IDs without deleting assets.
     */
    private function deleteSoalByNarasiIds(array $narasiIds, ?string $createdBy = null): void
    {
        if (empty($narasiIds)) {
            return;
        }

        while (true) {
            $soals = Soal::query()
                ->whereIn('narasi_id', $narasiIds)
                ->when($createdBy, fn ($q) => $q->where('created_by', $createdBy))
                ->limit(100)
                ->get();

            if ($soals->isEmpty()) {
                break;
            }

            foreach ($soals as $soal) {
                $soal->delete();
            }
        }
    }
}
