<?php

namespace App\Repositories;

use App\Models\NarasiSoal;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NarasiSoalRepository
{
    public function __construct(
        protected NarasiSoal $model
    ) {}

    /**
     * Get filtered & paginated narasi for Dinas.
     */
    public function getFiltered(
        ?string $kategoriId = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->filteredQuery($kategoriId, $search)
            ->with(['kategori', 'pembuat'])
            ->withCount('soalList')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function filteredQuery(
        ?string $kategoriId = null,
        ?string $search = null,
        ?string $createdBy = null,
    ): Builder {
        return $this->model->newQuery()
            ->when($createdBy, fn (Builder $query) => $query->where('created_by', $createdBy))
            ->when($kategoriId, fn (Builder $query) => $query->where('kategori_id', $kategoriId))
            ->search($search);
    }

    /**
     * Base query for trashed narasi.
     */
    public function queryTrashed(
        ?string $kategoriId = null,
        ?string $search = null,
        ?string $createdBy = null
    ): Builder {
        return $this->model->onlyTrashed()
            ->when($createdBy, fn (Builder $query) => $query->where('created_by', $createdBy))
            ->when($kategoriId, fn (Builder $query) => $query->where('kategori_id', $kategoriId))
            ->search($search);
    }

    /**
     * Get trashed narasi with pagination.
     */
    public function getTrashedPaginated(
        ?string $kategoriId = null,
        ?string $search = null,
        int $perPage = 20,
        ?string $createdBy = null
    ): LengthAwarePaginator {
        return $this->queryTrashed($kategoriId, $search, $createdBy)
            ->with(['kategori', 'pembuat'])
            ->withCount(['soalList' => fn ($query) => $query->withTrashed()])
            ->latest('deleted_at')
            ->paginate($perPage, ['*'], 'trash_page')
            ->withQueryString();
    }

    /**
     * Get all trashed narasi IDs for current filter.
     */
    public function getTrashedIds(
        ?string $kategoriId = null,
        ?string $search = null,
        ?string $createdBy = null
    ): array {
        return $this->queryTrashed($kategoriId, $search, $createdBy)
            ->orderBy('deleted_at', 'desc')
            ->pluck('id')
            ->all();
    }

    /**
     * Get active narasi for dropdowns/selects (optionally filtered by kategori).
     */
    public function getActive(?string $kategoriId = null): Collection
    {
        return $this->model
            ->where('is_active', true)
            ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            ->orderBy('judul')
            ->get(['id', 'judul', 'kategori_id']);
    }

    /**
     * Find narasi by ID.
     */
    public function findById(string $id): ?NarasiSoal
    {
        return $this->model->find($id);
    }

    /**
     * Find narasi with relations loaded.
     */
    public function findWithRelations(string $id, array $relations = ['kategori', 'pembuat', 'soalList']): ?NarasiSoal
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * Create a new narasi record.
     */
    public function create(array $data): NarasiSoal
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing narasi.
     */
    public function update(NarasiSoal $narasi, array $data): bool
    {
        return $narasi->update($data);
    }

    /**
     * Soft-delete a narasi.
     */
    public function delete(NarasiSoal $narasi): ?bool
    {
        return $narasi->delete();
    }

    public function getForBulkDelete(?string $kategoriId = null, ?string $createdBy = null): Collection
    {
        return $this->filteredQuery($kategoriId, null, $createdBy)
            ->with('soalList')
            ->get();
    }

    public function detachSoalFromNarasiIds(iterable $narasiIds, ?string $createdBy = null): int
    {
        $narasiIds = collect($narasiIds)->filter()->values();

        if ($narasiIds->isEmpty()) {
            return 0;
        }

        return Soal::withTrashed()
            ->whereIn('narasi_id', $narasiIds)
            ->when($createdBy, fn (Builder $query) => $query->where('created_by', $createdBy))
            ->update([
                'narasi_id' => null,
                'urutan_dalam_narasi' => 0,
                'updated_at' => now(),
            ]);
    }

    public function deleteByIds(iterable $ids): int
    {
        $ids = collect($ids)->filter()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return $this->model->newQuery()->whereIn('id', $ids)->delete();
    }

    public function restore(NarasiSoal $narasi): bool
    {
        return $narasi->restore();
    }

    public function forceDelete(NarasiSoal $narasi): bool
    {
        return $narasi->forceDelete();
    }

    /**
     * Get narasi by kategori ID.
     */
    public function getByKategori(string $kategoriId): Collection
    {
        return $this->model
            ->where('kategori_id', $kategoriId)
            ->where('is_active', true)
            ->orderBy('judul')
            ->get();
    }
}
