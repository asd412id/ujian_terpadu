<?php

namespace App\Jobs;

use App\Repositories\JawabanRepository;
use App\Services\RedisExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Periodically flushes buffered exam answers from Redis to MariaDB.
 *
 * Runs every 5 seconds via schedule. Processes dirty sessions in chunks
 * to avoid memory spikes and long-running transactions.
 *
 * No retry needed: the dirty set is persistent in Redis, so any sessions
 * skipped due to timeout or error are automatically retried on the next
 * scheduled run (every 5 seconds).
 */
class FlushJawabanToDbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 55;

    private const CHUNK_SIZE = 50;

    public function __construct()
    {
        $this->onQueue('exam-flush');
    }

    public function handle(RedisExamService $redisExam, JawabanRepository $repository): void
    {
        $dirtyIds = $redisExam->getDirtySessionIds();

        if (empty($dirtyIds)) {
            return;
        }

        $flushed = 0;
        $failed  = 0;
        $skipped = 0;

        // Process in chunks to limit memory and DB pressure
        foreach (array_chunk($dirtyIds, self::CHUNK_SIZE) as $chunk) {
            // Batch-fetch statuses for the entire chunk (1 query instead of N)
            $statuses = DB::table('sesi_peserta')
                ->whereIn('id', $chunk)
                ->pluck('status', 'id')
                ->all();

            foreach ($chunk as $spId) {
                try {
                    // Skip sessions that are no longer active:
                    // - submit/dinilai: forceFlush already handled them
                    // - terdaftar: admin reset cleared DB answers, must not re-flush stale Redis data
                    $status = $statuses[$spId] ?? null;
                    if ($status === null || in_array($status, ['submit', 'dinilai', 'terdaftar'], true)) {
                        $redisExam->cleanupSession($spId);
                        $skipped++;
                        continue;
                    }

                    $data = $redisExam->getSessionDataForFlush($spId);

                    if (empty($data['upsert_rows'])) {
                        $redisExam->markFlushed($spId);
                        continue;
                    }

                    // Use shared flush method to prevent logic divergence with forceFlush()
                    $redisExam->flushSessionToDb($spId, $data, $repository);

                    $redisExam->markFlushed($spId);
                    $flushed++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('[FlushJawaban] Failed to flush session', [
                        'sesi_peserta_id' => $spId,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't remove from dirty set — will retry on next run
                }
            }
        }

        // Periodic cleanup: remove stale entries from dirty set where session
        // no longer exists or was scored more than 1 hour ago
        $this->cleanupStaleDirtyEntries($redisExam);

        if ($flushed > 0 || $failed > 0 || $skipped > 0) {
            Log::info('[FlushJawaban] Completed', [
                'total_dirty' => count($dirtyIds),
                'flushed'     => $flushed,
                'skipped'     => $skipped,
                'failed'      => $failed,
            ]);
        }
    }

    /**
     * Remove stale spIds from the dirty set that should no longer be there.
     *
     * Targets sessions that: (a) no longer exist in DB, or (b) were scored
     * more than 1 hour ago. Runs after each flush cycle to keep the dirty set
     * from growing unboundedly.
     */
    private function cleanupStaleDirtyEntries(RedisExamService $redisExam): void
    {
        try {
            $dirtyIds = $redisExam->getDirtySessionIds();
            if (empty($dirtyIds)) {
                return;
            }

            // Check in batches of 100
            $staleCount = 0;
            foreach (array_chunk($dirtyIds, 100) as $chunk) {
                $existing = DB::table('sesi_peserta')
                    ->whereIn('id', $chunk)
                    ->pluck('status', 'id')
                    ->all();

                foreach ($chunk as $spId) {
                    $status = $existing[$spId] ?? null;
                    // Remove if session doesn't exist or was scored
                    if ($status === null || $status === 'dinilai') {
                        $redisExam->markFlushed($spId);
                        $staleCount++;
                    }
                }
            }

            if ($staleCount > 0) {
                Log::info('[FlushJawaban] Cleaned up stale dirty entries', ['count' => $staleCount]);
            }
        } catch (\Exception $e) {
            Log::warning('[FlushJawaban] Stale cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
