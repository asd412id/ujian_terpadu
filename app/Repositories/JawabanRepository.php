<?php

namespace App\Repositories;

use App\Models\JawabanPeserta;
use App\Models\SesiPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Collection;

class JawabanRepository
{
    public function __construct(
        protected JawabanPeserta $model
    ) {}

    /**
     * Find jawaban by sesi peserta and soal.
     */
    public function findBySesiAndSoal(string $sesiPesertaId, string $soalId): ?JawabanPeserta
    {
        return $this->model
            ->where('sesi_peserta_id', $sesiPesertaId)
            ->where('soal_id', $soalId)
            ->first();
    }

    /**
     * Find jawaban by idempotency key.
     */
    public function findByIdempotencyKey(string $key): ?JawabanPeserta
    {
        return $this->model->where('idempotency_key', $key)->first();
    }

    /**
     * Create or update a jawaban (upsert by sesi_peserta_id + soal_id).
     */
    public function createOrUpdate(string $sesiPesertaId, string $soalId, array $data): JawabanPeserta
    {
        return $this->model->updateOrCreate(
            ['sesi_peserta_id' => $sesiPesertaId, 'soal_id' => $soalId],
            $data
        );
    }

    /**
     * Create a new jawaban.
     */
    public function create(array $data): JawabanPeserta
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing jawaban.
     */
    public function update(JawabanPeserta $jawaban, array $data): bool
    {
        return $jawaban->update($data);
    }

    /**
     * Get all jawaban for a sesi peserta (session).
     */
    public function getBySession(string $sesiPesertaId): Collection
    {
        return $this->model
            ->where('sesi_peserta_id', $sesiPesertaId)
            ->get();
    }

    /**
     * Get jawaban by session keyed by soal_id.
     */
    public function getBySessionKeyedBySoal(string $sesiPesertaId): Collection
    {
        return $this->model
            ->where('sesi_peserta_id', $sesiPesertaId)
            ->get()
            ->keyBy('soal_id');
    }

    /**
     * Count answered (is_terjawab = true) soal for a session.
     */
    public function countAnswered(string $sesiPesertaId): int
    {
        return $this->model
            ->where('sesi_peserta_id', $sesiPesertaId)
            ->where('is_terjawab', true)
            ->count();
    }

    /**
     * Find sesi peserta by token ujian (for offline sync).
     */
    public function findSesiPesertaByToken(string $token): ?SesiPeserta
    {
        return SesiPeserta::where('token_ujian', $token)
            ->whereIn('status', ['mengerjakan', 'login'])
            ->first();
    }

    /**
     * Find sesi peserta by token (any status, for status check).
     */
    public function findSesiPesertaByTokenAny(string $token): ?SesiPeserta
    {
        return SesiPeserta::where('token_ujian', $token)->first();
    }

    /**
     * Update soal_terjawab count on sesi peserta.
     */
    public function updateTerjawabCount(string $sesiPesertaId): int
    {
        $count = $this->countAnswered($sesiPesertaId);
        SesiPeserta::where('id', $sesiPesertaId)->update(['soal_terjawab' => $count]);
        return $count;
    }

    /**
     * Create a log aktivitas record.
     */
    public function createLog(array $data): LogAktivitasUjian
    {
        return LogAktivitasUjian::create($data);
    }
}
