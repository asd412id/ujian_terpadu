<?php

namespace App\Services;

use App\Models\SesiPeserta;
use App\Repositories\JawabanRepository;
use App\Traits\ParsesJawaban;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis write-buffer for exam answers during active sessions.
 *
 * All student answer writes go to Redis first (0 DB queries on hot path),
 * then a background job flushes to MariaDB in bulk every few seconds.
 *
 * Gracefully degrades: when Redis is unavailable (tests, dev without Redis),
 * isAvailable() returns false and callers should fall back to direct DB writes.
 *
 * Key schema (all on Redis DB 2 "exam" connection):
 *   exam:ans:{spId}       Hash  soalId => JSON jawaban row
 *   exam:idem:{spId}      Set   idempotency keys already processed
 *   exam:tandai:{spId}    Set   bookmarked soal IDs
 *   exam:cnt:{spId}       String answered count (atomic counter)
 *   exam:meta:{spId}      Hash  soal_ditandai, last_sync_ts
 *   exam:dirty             Set   sesiPesertaId values pending DB flush
 */
class RedisExamService
{
    use ParsesJawaban;

    private const KEY_ANSWERS     = 'exam:ans:';
    private const KEY_IDEMPOTENCY = 'exam:idem:';
    private const KEY_TANDAI      = 'exam:tandai:';
    private const KEY_COUNTER     = 'exam:cnt:';
    private const KEY_META        = 'exam:meta:';
    private const KEY_DIRTY       = 'exam:dirty';

    // TTL for exam session keys (4 hours — well beyond any exam duration)
    private const SESSION_TTL = 14400;

    // Re-check Redis availability every 30s (Octane workers are long-lived)
    private const AVAILABILITY_CHECK_TTL = 30;

    private ?bool $available = null;
    private float $availableCheckedAt = 0;

    /**
     * Check if the Redis exam connection is reachable.
     * Re-checks every 30s under Octane (long-lived workers) so a Redis
     * recovery or failure is detected promptly.
     * Returns false when phpredis extension is missing (test/CI env).
     */
    public function isAvailable(): bool
    {
        $now = microtime(true);

        // Re-check if TTL expired (handles Octane long-lived workers)
        if ($this->available !== null && ($now - $this->availableCheckedAt) < self::AVAILABILITY_CHECK_TTL) {
            return $this->available;
        }

        // phpredis extension not loaded (common in test/CI environments)
        if (!extension_loaded('redis')) {
            $this->available = false;
            $this->availableCheckedAt = $now;
            return false;
        }

        try {
            $conn = Redis::connection('exam');
            $conn->ping();
            $this->available = true;
        } catch (\Throwable $e) {
            $this->available = false;
        }

        $this->availableCheckedAt = $now;
        return $this->available;
    }

    /**
     * Reset the cached availability flag (useful after reconnect or in tests).
     */
    public function resetAvailability(): void
    {
        $this->available = null;
        $this->availableCheckedAt = 0;
    }

    /**
     * Get the Redis connection for exam data (DB 2).
     */
    private function redis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection('exam');
    }

    /**
     * Save a batch of answers to Redis (zero DB queries).
     *
     * Returns [synced => int, skipped => int] matching the old DB-based response.
     * Throws if Redis is unavailable — caller should catch and fall back to DB.
     */
    public function saveAnswers(string $spId, array $answers, ?array $tandaiList = null, ?int $soalDitandai = null): array
    {
        $conn = $this->redis();
        $synced  = 0;
        $skipped = 0;

        // Collect idempotency keys to check in one PIPELINE call
        $incomingKeys = [];
        foreach ($answers as $ans) {
            $key = $ans['idempotency_key'] ?? null;
            if ($key) {
                $incomingKeys[] = $key;
            }
        }

        // Check existing idempotency keys via pipeline
        $existingFlags = [];
        if (!empty($incomingKeys)) {
            $results = $conn->pipeline(function ($pipe) use ($spId, $incomingKeys) {
                foreach ($incomingKeys as $key) {
                    $pipe->sIsMember(self::KEY_IDEMPOTENCY . $spId, $key);
                }
            });
            foreach ($incomingKeys as $i => $key) {
                $existingFlags[$key] = (bool) ($results[$i] ?? false);
            }
        }

        // Build answer rows and new idempotency keys
        $hashFields = [];
        $newIdemKeys = [];
        $now = now()->toIso8601String();

        foreach ($answers as $ans) {
            $idemKey = $ans['idempotency_key'] ?? null;
            if ($idemKey && ($existingFlags[$idemKey] ?? false)) {
                $skipped++;
                continue;
            }

            $soalId = $ans['soal_id'] ?? null;
            if (!$soalId) {
                $skipped++;
                continue;
            }

            $jawaban = $ans['jawaban'] ?? null;
            $parsed = $this->parseJawaban($jawaban);

            $hashFields[$soalId] = json_encode([
                'jawaban_pg'       => $parsed['jawaban_pg'],
                'jawaban_teks'     => $parsed['jawaban_teks'],
                'jawaban_pasangan' => $parsed['jawaban_pasangan'],
                'is_terjawab'      => $parsed['is_terjawab'],
                'idempotency_key'  => $idemKey,
                'waktu_jawab'      => $now,
            ]);

            if ($idemKey) {
                $newIdemKeys[] = $idemKey;
            }
            $synced++;
        }

        if ($synced === 0 && $tandaiList === null) {
            return ['synced' => 0, 'skipped' => $skipped];
        }

        // Read old is_terjawab states BEFORE writing, so we can compute counter delta
        $oldAnswered = 0;
        $newAnswered = 0;
        if (!empty($hashFields)) {
            $soalIds = array_keys($hashFields);
            $oldValues = $conn->pipeline(function ($pipe) use ($spId, $soalIds) {
                foreach ($soalIds as $soalId) {
                    $pipe->hGet(self::KEY_ANSWERS . $spId, $soalId);
                }
            });
            foreach ($soalIds as $i => $soalId) {
                $oldJson = $oldValues[$i] ?? null;
                if ($oldJson) {
                    $oldRow = json_decode($oldJson, true);
                    if (!empty($oldRow['is_terjawab'])) {
                        $oldAnswered++;
                    }
                }
                $newRow = json_decode($hashFields[$soalId], true);
                if (!empty($newRow['is_terjawab'])) {
                    $newAnswered++;
                }
            }
        }
        $delta = $newAnswered - $oldAnswered;

        // Single pipeline: write answers + idempotency + counter + dirty flag
        $conn->pipeline(function ($pipe) use ($spId, $hashFields, $newIdemKeys, $tandaiList, $soalDitandai, $delta) {
            // Store answer rows
            if (!empty($hashFields)) {
                $pipe->hMSet(self::KEY_ANSWERS . $spId, $hashFields);
                $pipe->expire(self::KEY_ANSWERS . $spId, self::SESSION_TTL);
            }

            // Register idempotency keys
            if (!empty($newIdemKeys)) {
                $pipe->sAdd(self::KEY_IDEMPOTENCY . $spId, ...$newIdemKeys);
                $pipe->expire(self::KEY_IDEMPOTENCY . $spId, self::SESSION_TTL);
            }

            // Update tandai set
            if ($tandaiList !== null) {
                $pipe->del(self::KEY_TANDAI . $spId);
                if (!empty($tandaiList)) {
                    $pipe->sAdd(self::KEY_TANDAI . $spId, ...$tandaiList);
                    $pipe->expire(self::KEY_TANDAI . $spId, self::SESSION_TTL);
                }
            }

            // Atomic counter update: INCRBY delta (handles both new answers and cleared answers)
            if ($delta !== 0) {
                $pipe->incrBy(self::KEY_COUNTER . $spId, $delta);
            }
            $pipe->expire(self::KEY_COUNTER . $spId, self::SESSION_TTL);

            // Meta (soal_ditandai count + last sync timestamp)
            $metaFields = ['last_sync_ts' => (string) now()->timestamp];
            if ($soalDitandai !== null) {
                $metaFields['soal_ditandai'] = (string) $soalDitandai;
            }
            $pipe->hMSet(self::KEY_META . $spId, $metaFields);
            $pipe->expire(self::KEY_META . $spId, self::SESSION_TTL);

            // Mark session as dirty (needs DB flush)
            $pipe->sAdd(self::KEY_DIRTY, $spId);
        });

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    /**
     * Recalculate the answered count from the full answers hash.
     *
     * O(N) where N = total soal in session (~40-200 typical). Called once when
     * the counter key is missing and as a fallback. The counter is then kept
     * in sync by subsequent incremental updates.
     */
    public function recalcAnsweredCount(string $spId): int
    {
        $conn = $this->redis();
        $all = $conn->hGetAll(self::KEY_ANSWERS . $spId);
        $count = 0;

        foreach ($all as $json) {
            $row = json_decode($json, true);
            if (!empty($row['is_terjawab'])) {
                $count++;
            }
        }

        $conn->pipeline(function ($pipe) use ($spId, $count) {
            $pipe->set(self::KEY_COUNTER . $spId, $count);
            $pipe->expire(self::KEY_COUNTER . $spId, self::SESSION_TTL);
        });

        return $count;
    }

    /**
     * Get the answered count for a session from Redis.
     * Returns null if Redis unavailable or key doesn't exist.
     */
    public function getAnsweredCount(string $spId): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $val = $this->redis()->get(self::KEY_COUNTER . $spId);
            if ($val !== null && $val !== false) {
                $count = (int) $val;
                // Counter drifted negative (eviction race) — self-heal
                if ($count < 0) {
                    return $this->recalcAnsweredCount($spId);
                }
                return $count;
            }

            // Counter key missing but answers hash may exist (TTL expired, evicted).
            // Self-heal by recalculating from the source hash.
            if ($this->redis()->exists(self::KEY_ANSWERS . $spId)) {
                return $this->recalcAnsweredCount($spId);
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Batch get answered counts for multiple sessions.
     * Returns [spId => count] only for sessions that exist in Redis.
     */
    public function getAnsweredCountBatch(array $spIds): array
    {
        if (empty($spIds) || !$this->isAvailable()) {
            return [];
        }

        try {
            $keys = array_map(fn ($id) => self::KEY_COUNTER . $id, $spIds);
            $values = $this->redis()->pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $key) {
                    $pipe->get($key);
                }
            });

            $result = [];
            foreach ($spIds as $i => $spId) {
                $val = $values[$i] ?? null;
                if ($val !== null && $val !== false) {
                    $result[$spId] = (int) $val;
                }
            }
            return $result;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get all dirty session IDs that need DB flush.
     */
    public function getDirtySessionIds(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $members = $this->redis()->sMembers(self::KEY_DIRTY);
        return is_array($members) ? $members : [];
    }

    /**
     * Get all answer data for a session (for flushing to DB).
     *
     * Returns array of rows ready for bulkUpsert, plus tandai and meta.
     */
    public function getSessionDataForFlush(string $spId): array
    {
        $conn = $this->redis();

        $results = $conn->pipeline(function ($pipe) use ($spId) {
            $pipe->hGetAll(self::KEY_ANSWERS . $spId);   // [0] answers
            $pipe->sMembers(self::KEY_TANDAI . $spId);   // [1] tandai
            $pipe->hGetAll(self::KEY_META . $spId);       // [2] meta
            $pipe->get(self::KEY_COUNTER . $spId);        // [3] counter
        });

        $answersRaw = $results[0] ?? [];
        $tandaiList = $results[1] ?? [];
        $meta       = $results[2] ?? [];
        $counter    = $results[3] ?? null;

        // Convert answers hash to upsert rows
        $upsertRows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach ($answersRaw as $soalId => $json) {
            $row = json_decode($json, true);
            if (!$row) continue;

            $upsertRows[] = [
                'sesi_peserta_id' => $spId,
                'soal_id'         => $soalId,
                'jawaban_pg'      => isset($row['jawaban_pg']) ? json_encode($row['jawaban_pg']) : null,
                'jawaban_teks'    => $row['jawaban_teks'] ?? null,
                'jawaban_pasangan'=> isset($row['jawaban_pasangan']) ? json_encode($row['jawaban_pasangan']) : null,
                'is_terjawab'     => $row['is_terjawab'] ?? false,
                'idempotency_key' => $row['idempotency_key'] ?? null,
                'waktu_jawab'     => isset($row['waktu_jawab']) ? Carbon::parse($row['waktu_jawab'])->format('Y-m-d H:i:s') : $now,
                'updated_at'      => $now,
            ];
        }

        return [
            'upsert_rows'   => $upsertRows,
            'tandai_list'   => is_array($tandaiList) ? $tandaiList : [],
            'soal_ditandai'  => isset($meta['soal_ditandai']) ? (int) $meta['soal_ditandai'] : null,
            'terjawab_count' => $counter !== null && $counter !== false ? (int) $counter : null,
        ];
    }

    /**
     * Mark a session as flushed (remove from dirty set).
     */
    public function markFlushed(string $spId): void
    {
        $this->redis()->sRem(self::KEY_DIRTY, $spId);
    }

    /**
     * Flush session data to DB inside a transaction.
     *
     * Shared logic used by both forceFlush() and FlushJawabanToDbJob to prevent
     * divergence. Handles bulk upsert, counter sync, and tandai list sync.
     */
    public function flushSessionToDb(string $spId, array $data, JawabanRepository $repository): void
    {
        DB::transaction(function () use ($spId, $data, $repository) {
            $repository->bulkUpsert($data['upsert_rows']);

            $updateData = [];
            if ($data['terjawab_count'] !== null) {
                $updateData['soal_terjawab'] = $data['terjawab_count'];
            }
            if ($data['soal_ditandai'] !== null) {
                $updateData['soal_ditandai'] = $data['soal_ditandai'];
            }
            if (!empty($updateData)) {
                DB::table('sesi_peserta')->where('id', $spId)->update($updateData);
            }

            // Always sync tandai list (empty array clears existing flags)
            $repository->syncTandaiList($spId, $data['tandai_list'] ?? []);
        });
    }

    /**
     * Force-flush a single session's data to DB immediately.
     * Used before submit/scoring to guarantee data completeness.
     * No-op when Redis is unavailable (data already in DB via direct write).
     */
    public function forceFlush(string $spId, JawabanRepository $repository): bool
    {
        if (!$this->isAvailable()) {
            return true; // Nothing buffered — data already in DB
        }

        try {
            $data = $this->getSessionDataForFlush($spId);
        } catch (\Exception $e) {
            Log::warning('[RedisExam] Could not read session data for flush', [
                'sesi_peserta_id' => $spId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if (empty($data['upsert_rows'])) {
            $this->markFlushed($spId);
            return true;
        }

        try {
            $this->flushSessionToDb($spId, $data, $repository);
            $this->markFlushed($spId);
            // Clean up all Redis keys after successful flush — data is safely in DB.
            // Prevents stale answers from being re-loaded on exam reset/retake.
            $this->cleanupSession($spId);
            return true;
        } catch (\Exception $e) {
            Log::error('[RedisExam] forceFlush failed', [
                'sesi_peserta_id' => $spId,
                'error' => $e->getMessage(),
                'rows' => count($data['upsert_rows']),
            ]);
            return false;
        }
    }

    /**
     * Cleanup all Redis keys for a session (after scoring is complete).
     * No-op when Redis is unavailable.
     */
    public function cleanupSession(string $spId): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            $conn = $this->redis();
            $conn->pipeline(function ($pipe) use ($spId) {
                $pipe->del(self::KEY_ANSWERS . $spId);
                $pipe->del(self::KEY_IDEMPOTENCY . $spId);
                $pipe->del(self::KEY_TANDAI . $spId);
                $pipe->del(self::KEY_COUNTER . $spId);
                $pipe->del(self::KEY_META . $spId);
                $pipe->sRem(self::KEY_DIRTY, $spId);
            });
        } catch (\Exception) {
            // Already cleaned or Redis down — acceptable
        }
    }

    /**
     * Cleanup all Redis keys for every sesi_peserta that belongs to one peserta.
     * Optionally excludes one current sesi_peserta id.
     */
    public function cleanupByPeserta(string $pesertaId, ?string $excludeSpId = null): void
    {
        $spIds = SesiPeserta::query()
            ->where('peserta_id', $pesertaId)
            ->when($excludeSpId, fn ($q) => $q->where('id', '!=', $excludeSpId))
            ->pluck('id');

        if ($spIds->isEmpty()) {
            return;
        }

        foreach ($spIds as $spId) {
            $this->cleanupSession((string) $spId);
        }
    }

    /**
     * Check if a session has data buffered in Redis.
     */
    public function hasBufferedData(string $spId): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return (bool) $this->redis()->exists(self::KEY_ANSWERS . $spId);
        } catch (\Exception) {
            return false;
        }
    }
}
