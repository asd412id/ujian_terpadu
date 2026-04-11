<?php

namespace App\Jobs;

use App\Models\SesiPeserta;
use App\Services\PenilaianService;
use App\Services\RedisExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HitungNilaiJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /** Unique lock held for max 30 seconds — prevents duplicate scoring */
    public int $uniqueFor = 30;

    public function __construct(
        protected string $sesiPesertaId,
        protected string $reason = 'submit',
    ) {
        $this->onQueue('default');
    }

    /** Scope uniqueness to the specific sesi_peserta being scored */
    public function uniqueId(): string
    {
        return $this->sesiPesertaId;
    }

    public function handle(PenilaianService $penilaianService, RedisExamService $redisExam): void
    {
        $sesiPeserta = SesiPeserta::find($this->sesiPesertaId);

        if (!$sesiPeserta) {
            Log::warning('[HitungNilai] SesiPeserta not found', ['id' => $this->sesiPesertaId]);
            return;
        }

        if (!in_array($sesiPeserta->status, ['submit', 'dinilai'])) {
            Log::warning('[HitungNilai] Skipping — status is ' . $sesiPeserta->status, ['id' => $this->sesiPesertaId]);
            return;
        }

        $hasil = $penilaianService->hitungNilai($sesiPeserta);
        $sesiPeserta->update($hasil);

        // Bust cached status so polling gets fresh nilai immediately
        if ($sesiPeserta->token_ujian) {
            Cache::forget("ujian_status:{$sesiPeserta->token_ujian}");
        }

        // Cleanup Redis exam buffer (answers already flushed to DB before scoring)
        $redisExam->cleanupSession($this->sesiPesertaId);

        Log::info('[HitungNilai] Scored successfully', [
            'sesi_peserta_id' => $this->sesiPesertaId,
            'reason'          => $this->reason,
            'nilai_akhir'     => $hasil['nilai_akhir'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[HitungNilai] Job failed permanently', [
            'sesi_peserta_id' => $this->sesiPesertaId,
            'reason'          => $this->reason,
            'error'           => $exception->getMessage(),
        ]);
    }
}
