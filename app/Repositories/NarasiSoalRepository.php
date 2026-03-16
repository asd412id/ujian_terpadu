<?php

namespace App\Repositories;

use App\Models\NarasiSoal;
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
        return $this->model
            ->with(['kategori', 'pembuat'])
            ->withCount('soalList')
            ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('judul', 'like', "%{$search}%")
                   ->orWhere('konten', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
