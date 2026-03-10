<?php

namespace App\Repositories;

use App\Models\PaketUjian;
use App\Models\PaketSoal;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaketUjianRepository
{
    public function __construct(
        protected PaketUjian $model
    ) {}

    /**
     * Get all paket ujian with counts (Dinas view).
     */
    public function getAll(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['sekolah', 'pembuat'])
            ->withCount(['paketSoal', 'sesi'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get filtered paket ujian for a sekolah (active only, includes shared/null sekolah).
     */
    public function getForSekolah(string $sekolahId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['sesi.sesiPeserta', 'paketSoal'])
            ->where(fn ($q) => $q->where('sekolah_id', $sekolahId)->orWhereNull('sekolah_id'))
            ->where('status', 'aktif')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find paket by ID.
     */
    public function findById(string $id): ?PaketUjian
    {
        return $this->model->find($id);
    }

    /**
     * Find paket with full relations for detail view.
     */
    public function findWithRelations(string $id): ?PaketUjian
    {
        return $this->model
            ->with(['paketSoal.soal.kategori', 'sesi.sesiPeserta', 'sekolah'])
            ->find($id);
    }

    /**
     * Find paket with sesi and peserta (sekolah view).
     */
    public function findWithSesiPeserta(string $id): ?PaketUjian
    {
        return $this->model
            ->with(['sesi.sesiPeserta.peserta', 'paketSoal'])
            ->find($id);
    }

    /**
     * Create a new paket ujian.
     */
    public function create(array $data): PaketUjian
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing paket ujian.
     */
    public function update(PaketUjian $paket, array $data): bool
    {
        return $paket->update($data);
    }

    /**
     * Delete a paket ujian.
     */
    public function delete(PaketUjian $paket): ?bool
    {
        return $paket->delete();
    }

    /**
     * Get paket by sekolah.
     */
    public function getBySekolah(string $sekolahId): Collection
    {
        return $this->model->where('sekolah_id', $sekolahId)->get();
    }

    /**
     * Attach a soal to paket (if not already attached).
     * Returns true if the soal was newly attached, false if already existed.
     */
    public function attachSoal(PaketUjian $paket, string $soalId): bool
    {
        $exists = PaketSoal::where('paket_id', $paket->id)
            ->where('soal_id', $soalId)
            ->exists();

        if ($exists) {
            return false;
        }

        $maxNomor = PaketSoal::where('paket_id', $paket->id)->max('nomor_urut') ?? 0;

        PaketSoal::create([
            'paket_id'   => $paket->id,
            'soal_id'    => $soalId,
            'nomor_urut' => $maxNomor + 1,
        ]);

        $paket->increment('jumlah_soal');

        return true;
    }

    /**
     * Detach a soal from paket.
     */
    public function detachSoal(PaketUjian $paket, string $soalId): bool
    {
        $deleted = PaketSoal::where('paket_id', $paket->id)
            ->where('soal_id', $soalId)
            ->delete();

        if ($deleted) {
            $paket->decrement('jumlah_soal');
            return true;
        }

        return false;
    }

    /**
     * Get the count of soal in a paket.
     */
    public function getSoalCount(PaketUjian $paket): int
    {
        return $paket->paketSoal()->count();
    }

    /**
     * Get soal IDs attached to a paket.
     */
    public function getSoalIdsByPaket(string $paketId): array
    {
        return PaketSoal::where('paket_id', $paketId)->pluck('soal_id')->toArray();
    }

    /**
     * Create a default sesi for a paket.
     */
    public function createSesi(array $data): SesiUjian
    {
        return SesiUjian::create($data);
    }

    /**
     * Register peserta to a sesi.
     */
    public function daftarPesertaToSesi(string $sesiId, array $pesertaIds): int
    {
        $created = 0;
        foreach ($pesertaIds as $pesertaId) {
            SesiPeserta::firstOrCreate(
                ['sesi_id' => $sesiId, 'peserta_id' => $pesertaId],
                ['status' => 'belum_login']
            );
            $created++;
        }
        return $created;
    }
}
