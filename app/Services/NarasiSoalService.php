<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Repositories\NarasiSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            $paths = $this->collectNarasiAssetPaths($narasi);

            $this->repository->detachSoalFromNarasiIds([$narasi->id], $soalCreatedBy);
            $deleted = $this->repository->delete($narasi);

            DB::afterCommit(fn () => $this->deleteAssetPaths($paths));

            return $deleted;
        });
    }

    public function deleteAllNarasi(?string $kategoriId = null, ?string $createdBy = null): int
    {
        return DB::transaction(function () use ($kategoriId, $createdBy) {
            $narasis = $this->repository->getForBulkDelete($kategoriId, $createdBy);

            if ($narasis->isEmpty()) {
                return 0;
            }

            $paths = $narasis
                ->flatMap(fn (NarasiSoal $narasi) => $this->collectNarasiAssetPaths($narasi))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->repository->detachSoalFromNarasiIds($narasis->pluck('id'), $createdBy);
            $this->repository->deleteByIds($narasis->pluck('id'));

            DB::afterCommit(fn () => $this->deleteAssetPaths($paths));

            return $narasis->count();
        });
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

        Storage::disk('public')->delete($paths);
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
                        || str_starts_with($path, 'soal/gambar/')
                    ) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }
}
