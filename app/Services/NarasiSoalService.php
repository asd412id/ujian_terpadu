<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Models\Soal;
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

            $this->deleteSoalByNarasiIds([$narasi->id], $soalCreatedBy);
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

            $this->deleteSoalByNarasiIds($narasis->pluck('id')->all(), $createdBy);
            $this->repository->deleteByIds($narasis->pluck('id'));

            DB::afterCommit(fn () => $this->deleteAssetPaths($paths));

            return $narasis->count();
        });
    }

    /**
     * Delete all soal associated with the given narasi IDs (cascade delete).
     */
    private function deleteSoalByNarasiIds(array $narasiIds, ?string $createdBy = null): void
    {
        if (empty($narasiIds)) {
            return;
        }

        $disk = Storage::disk('public');

        Soal::with(['opsiJawaban', 'pasangan'])
            ->whereIn('narasi_id', $narasiIds)
            ->when($createdBy, fn ($q) => $q->where('created_by', $createdBy))
            ->chunk(100, function ($soals) use ($disk) {
                foreach ($soals as $soal) {
                    if ($soal->gambar_soal) {
                        $disk->delete($soal->gambar_soal);
                    }
                    foreach ($this->extractStoragePaths($soal->pertanyaan) as $path) {
                        $disk->delete($path);
                    }
                    foreach ($this->extractStoragePaths($soal->pembahasan) as $path) {
                        $disk->delete($path);
                    }
                    foreach ($soal->opsiJawaban as $opsi) {
                        if ($opsi->gambar) {
                            $disk->delete($opsi->gambar);
                        }
                        foreach ($this->extractStoragePaths($opsi->teks) as $path) {
                            $disk->delete($path);
                        }
                    }
                    foreach ($soal->pasangan as $pas) {
                        if ($pas->kiri_gambar) {
                            $disk->delete($pas->kiri_gambar);
                        }
                        if ($pas->kanan_gambar) {
                            $disk->delete($pas->kanan_gambar);
                        }
                    }
                    $soal->delete();
                }
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
