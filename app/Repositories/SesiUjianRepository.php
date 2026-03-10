<?php

namespace App\Repositories;

use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Collection;

class SesiUjianRepository
{
    public function __construct(
        protected SesiPeserta $model
    ) {}

    /**
     * Find a sesi peserta by ID.
     */
    public function findById(string $id): ?SesiPeserta
    {
        return $this->model->find($id);
    }

    /**
     * Find sesi peserta with relations.
     */
    public function findWithRelations(string $id, array $relations = ['sesi.paket']): ?SesiPeserta
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * Get all sesi peserta by peserta ID.
     */
    public function getByPeserta(string $pesertaId): Collection
    {
        return $this->model
            ->with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->get();
    }

    /**
     * Get active (mengerjakan/login) sesi peserta for a peserta.
     */
    public function getAktifByPeserta(string $pesertaId): Collection
    {
        return $this->model
            ->with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['terdaftar', 'belum_login', 'login', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->get();
    }

    /**
     * Create a new sesi peserta record.
     */
    public function create(array $data): SesiPeserta
    {
        return $this->model->create($data);
    }

    /**
     * Update a sesi peserta.
     */
    public function update(SesiPeserta $sesiPeserta, array $data): bool
    {
        return $sesiPeserta->update($data);
    }

    /**
     * Mark sesi peserta as submitted (selesaikan).
     */
    public function selesaikan(SesiPeserta $sesiPeserta, array $data): bool
    {
        return $sesiPeserta->update(array_merge([
            'status'    => 'submit',
            'submit_at' => now(),
        ], $data));
    }

    /**
     * Get soal for a paket with relations for ujian display.
     */
    public function getSoalForSesi(string $paketId): Collection
    {
        $paket = SesiUjian::find($paketId)?->paket
            ?? \App\Models\PaketUjian::find($paketId);

        if (!$paket) {
            return new Collection();
        }

        return $paket->soal()
            ->with(['opsiJawaban', 'pasangan', 'kategori'])
            ->get();
    }

    /**
     * Get existing jawaban for a sesi peserta (keyed by soal_id).
     */
    public function getJawabanBySesiPeserta(string $sesiPesertaId): Collection
    {
        return JawabanPeserta::where('sesi_peserta_id', $sesiPesertaId)->get();
    }

    /**
     * Create a log aktivitas ujian.
     */
    public function createLog(array $data): LogAktivitasUjian
    {
        return LogAktivitasUjian::create($data);
    }

    /**
     * Count total soal in a paket (via sesi peserta's paket).
     */
    public function countSoalByPaket(string $paketId): int
    {
        $paket = \App\Models\PaketUjian::find($paketId);
        return $paket ? $paket->soal()->count() : 0;
    }

    /**
     * Count answered soal for a sesi peserta.
     */
    public function countTerjawab(string $sesiPesertaId): int
    {
        return JawabanPeserta::where('sesi_peserta_id', $sesiPesertaId)
            ->where('is_terjawab', true)
            ->count();
    }

    /**
     * Find sesi peserta with selesai details.
     */
    public function findWithSelesaiDetail(string $id): ?SesiPeserta
    {
        return $this->model
            ->with(['sesi.paket', 'jawaban.soal'])
            ->find($id);
    }
}
