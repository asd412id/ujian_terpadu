<?php

namespace App\Repositories;

use App\Models\Peserta;
use App\Models\ImportJob;
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
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%"))
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
}
