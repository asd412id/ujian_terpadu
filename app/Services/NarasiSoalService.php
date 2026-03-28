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
        protected NarasiSoalRepository $repository,
        protected SoalService $soalService
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

    public function getTrashedPaginated(
        ?string $kategoriId = null,
        ?string $search = null,
        int $perPage = 20,
        ?string $createdBy = null
    ): LengthAwarePaginator {
        return $this->repository->getTrashedPaginated($kategoriId, $search, $perPage, $createdBy);
    }

    public function getTrashedIds(
        ?string $kategoriId = null,
        ?string $search = null,
        ?string $createdBy = null
    ): array {
        return $this->repository->getTrashedIds($kategoriId, $search, $createdBy);
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

    public function restoreNarasi(NarasiSoal $narasi, ?string $soalCreatedBy = null): bool
    {
        return DB::transaction(function () use ($narasi, $soalCreatedBy) {
            $restored = $this->repository->restore($narasi);
            $this->soalService->restoreTrashedByNarasiIds([$narasi->id], $soalCreatedBy);

            return $restored;
        });
    }

    public function forceDeleteNarasi(NarasiSoal $narasi, ?string $soalCreatedBy = null): bool
    {
        return DB::transaction(function () use ($narasi, $soalCreatedBy) {
            $paths = $this->collectNarasiAssetPaths($narasi);

            $this->soalService->forceDeleteTrashedByNarasiIds([$narasi->id], $soalCreatedBy);
            $deleted = $this->repository->forceDelete($narasi);

            DB::afterCommit(fn () => $this->deleteAssetPaths($paths));

            return $deleted;
        });
    }

    public function emptyTrash(?string $createdBy = null): int
    {
        return $this->bulkForceDeleteNarasi($this->getTrashedIds(null, null, $createdBy), $createdBy);
    }

    public function bulkRestoreNarasi(array $ids, ?string $createdBy = null): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $createdBy) {
            $query = NarasiSoal::onlyTrashed()->whereIn('id', $ids);
            if ($createdBy) {
                $query->where('created_by', $createdBy);
            }

            $count = 0;
            foreach ($query->get() as $narasi) {
                $this->repository->restore($narasi);
                $this->soalService->restoreTrashedByNarasiIds([$narasi->id], $createdBy);
                $count++;
            }

            return $count;
        });
    }

    public function bulkForceDeleteNarasi(array $ids, ?string $createdBy = null): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $createdBy) {
            $query = NarasiSoal::onlyTrashed()->whereIn('id', $ids);
            if ($createdBy) {
                $query->where('created_by', $createdBy);
            }

            $narasis = $query->get();
            $paths = $narasis
                ->flatMap(fn (NarasiSoal $narasi) => $this->collectNarasiAssetPaths($narasi))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $count = 0;
            foreach ($narasis as $narasi) {
                $this->soalService->forceDeleteTrashedByNarasiIds([$narasi->id], $createdBy);
                $this->repository->forceDelete($narasi);
                $count++;
            }

            DB::afterCommit(fn () => $this->deleteAssetPaths($paths));

            return $count;
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

    /**
     * @return string[]
     */
    private function collectNarasiAssetPaths(NarasiSoal $narasi): array
    {
        return collect([
            $narasi->gambar,
            ...$this->extractStoragePaths($narasi->konten),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function deleteAssetPaths(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        \Storage::disk('public')->delete($paths);
    }

    /**
     * @return string[]
     */
    private function extractStoragePaths(?string $html): array
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return [];
        }

        $paths = [];

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                if (preg_match('#/storage/(.+)$#', $src, $pathMatch)) {
                    $path = urldecode($pathMatch[1]);

                    if (
                        str_starts_with($path, 'narasi/')
                        || str_starts_with($path, 'import/')
                        || str_starts_with($path, 'soal/')
                    ) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }
}
