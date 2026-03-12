<?php

namespace App\Repositories;

use App\Models\JawabanPeserta;
use App\Models\SesiPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Find jawaban by ID or fail.
     */
    public function getJawabanBySesiPeserta(string $sesiPesertaId): Collection
    {
        return $this->model
            ->where('sesi_peserta_id', $sesiPesertaId)
            ->get()
            ->keyBy('soal_id');
    }

    /**
     * Batch update skor_auto for multiple jawaban.
     */
    public function batchUpdateSkorAuto(array $updates): void
    {
        foreach ($updates as $upd) {
            $this->model->where('id', $upd['id'])->update(['skor_auto' => $upd['skor_auto']]);
        }
    }

    /**
     * Find active sesi peserta by ID (status must be login/mengerjakan).
     */
    public function findActiveSesiPeserta(string $id): SesiPeserta
    {
        return SesiPeserta::whereIn('status', ['mengerjakan', 'login'])
            ->findOrFail($id);
    }

    /**
     * Find sesi peserta by token with sesi.paket, for given statuses.
     */
    public function findSesiPesertaByTokenWithPaket(string $token, array $statuses): SesiPeserta
    {
        return SesiPeserta::with('sesi.paket')
            ->where('token_ujian', $token)
            ->whereIn('status', $statuses)
            ->firstOrFail();
    }

    /**
     * Find sesi peserta by token with sesi.paket (any status).
     */
    public function findSesiPesertaByTokenWithPaketAny(string $token): SesiPeserta
    {
        return SesiPeserta::with('sesi.paket')
            ->where('token_ujian', $token)
            ->firstOrFail();
    }

    /**
     * Get existing idempotency keys from a list.
     */
    public function getExistingIdempotencyKeys(array $keys): array
    {
        return $this->model->whereIn('idempotency_key', $keys)
            ->pluck('idempotency_key')
            ->flip()
            ->all();
    }

    /**
     * Bulk upsert jawaban rows.
     */
    public function bulkUpsert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $now = now();
        $prepared = collect($rows)->map(fn ($row) => array_merge($row, [
            'id'         => $row['id'] ?? Str::uuid()->toString(),
            'created_at' => $row['created_at'] ?? $now,
        ]))->all();

        DB::table('jawaban_peserta')->upsert(
            $prepared,
            ['sesi_peserta_id', 'soal_id'],
            ['jawaban_pg', 'jawaban_teks', 'jawaban_pasangan', 'is_terjawab', 'idempotency_key', 'waktu_jawab', 'updated_at']
        );
    }

    /**
     * Sync tandai (bookmarked) soal for a sesi peserta.
     */
    public function syncTandaiList(string $sesiPesertaId, array $tandaiSoalIds): void
    {
        $this->model->where('sesi_peserta_id', $sesiPesertaId)
            ->whereNotIn('soal_id', $tandaiSoalIds)
            ->where('is_ditandai', true)
            ->update(['is_ditandai' => false]);

        $this->model->where('sesi_peserta_id', $sesiPesertaId)
            ->whereIn('soal_id', $tandaiSoalIds)
            ->where('is_ditandai', false)
            ->update(['is_ditandai' => true]);
    }

    /**
     * Clear all tandai flags for a sesi peserta.
     */
    public function clearAllTandai(string $sesiPesertaId): void
    {
        $this->model->where('sesi_peserta_id', $sesiPesertaId)
            ->where('is_ditandai', true)
            ->update(['is_ditandai' => false]);
    }

    /**
     * Find jawaban by ID or fail.
     */
    public function findOrFail(string $id): JawabanPeserta
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Get sesi peserta by ID (unused method getBySesiPeserta alias).
     */
    public function getBySesiPeserta(string $sesiPesertaId): Collection
    {
        return $this->getBySession($sesiPesertaId);
    }
}
