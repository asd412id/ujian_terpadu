<?php

namespace App\Repositories;

use App\Models\Peserta;
use App\Models\ImportJob;
use App\Models\SesiPeserta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class PesertaRepository
{
    public function __construct(
        protected Peserta $model
    ) {}

    /**
     * Get all peserta, paginated.
     */
    public function getAll(int $perPage = 25): LengthAwarePaginator
    {
        return $this->model
            ->orderBy('nama')
            ->paginate($perPage);
    }

    /**
     * Get all peserta across sekolah with optional filters (for dinas admin).
     */
    public function getAllFiltered(
        ?string $sekolahId = null,
        ?string $search = null,
        ?string $kelas = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        return $this->model
            ->with('sekolah')
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            }))
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get filtered peserta for a specific sekolah.
     */
    public function getFiltered(
        string $sekolahId,
        ?string $search = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        return $this->model
            ->where('sekolah_id', $sekolahId)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            }))
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->when($jurusan, fn ($q) => $q->where('jurusan', $jurusan))
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get peserta by sekolah.
     */
    public function getBySekolah(string $sekolahId): Collection
    {
        return $this->model
            ->where('sekolah_id', $sekolahId)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Get distinct kelas list for a sekolah.
     */
    public function getKelasBySekolah(string $sekolahId): SupportCollection
    {
        return $this->model
            ->where('sekolah_id', $sekolahId)
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');
    }

    /**
     * Find peserta by ID.
     */
    public function findById(string $id): ?Peserta
    {
        return $this->model->find($id);
    }

    /**
     * Create a new peserta.
     */
    public function create(array $data): Peserta
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing peserta.
     */
    public function update(Peserta $peserta, array $data): bool
    {
        return $peserta->update($data);
    }

    /**
     * Delete a peserta.
     */
    public function delete(Peserta $peserta): ?bool
    {
        return $peserta->delete();
    }

    /**
     * Create an import job for peserta import.
     */
    public function createImportJob(array $data): ImportJob
    {
        return ImportJob::create($data);
    }

    /**
     * Get peserta by ruang/sesi for monitoring purposes.
     */
    public function getByRuang(string $sesiId): Collection
    {
        return $this->model
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesiId))
            ->get();
    }

    /**
     * Find peserta by NIS and sekolah.
     */
    public function findByNisAndSekolah(string $nis, string $sekolahId): ?Peserta
    {
        return $this->model
            ->where('nis', $nis)
            ->where('sekolah_id', $sekolahId)
            ->first();
    }

    /**
     * Delete all peserta by sekolah (or all), cleaning up sesi_peserta first.
     */
    public function deleteAllBySekolah(?string $sekolahId = null): int
    {
        if ($sekolahId) {
            $pesertaIds = $this->model->where('sekolah_id', $sekolahId)->pluck('id');
            $jumlah = $pesertaIds->count();
            SesiPeserta::whereIn('peserta_id', $pesertaIds)
                ->whereNotIn('status', ['login', 'mengerjakan'])
                ->delete();
            $this->model->where('sekolah_id', $sekolahId)->delete();
            return $jumlah;
        }

        $pesertaIds = $this->model->pluck('id');
        $jumlah = $pesertaIds->count();
        SesiPeserta::whereIn('peserta_id', $pesertaIds)
            ->whereNotIn('status', ['login', 'mengerjakan'])
            ->delete();
        $this->model->newQuery()->delete();
        return $jumlah;
    }

    /**
     * Get peserta by sekolah with filters, paginated (for kartu login).
     */
    public function getBySekolahFiltered(string $sekolahId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('sekolah_id', $sekolahId);

        if (!empty($filters['kelas'])) {
            $query->where('kelas', $filters['kelas']);
        }

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('kelas')
            ->orderBy('nama')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    /**
     * Get active peserta by sekolah, ordered by kelas+nama.
     */
    public function getActiveBySekolah(string $sekolahId): Collection
    {
        return $this->model->where('sekolah_id', $sekolahId)
            ->where('is_active', true)
            ->orderBy('kelas')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Get peserta by IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)->get();
    }

    /**
     * Find peserta by ID or fail.
     */
    public function findOrFail(string $id): Peserta
    {
        return $this->model->findOrFail($id);
    }
}
