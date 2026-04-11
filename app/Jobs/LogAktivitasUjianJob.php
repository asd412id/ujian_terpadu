<?php

namespace App\Jobs;

use App\Models\LogAktivitasUjian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogAktivitasUjianJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 30;

    public function __construct(
        protected string $sesiPesertaId,
        protected string $tipeEvent,
        protected array $detail = [],
        protected ?string $ipAddress = null,
        protected ?string $createdAt = null,
    ) {
        $this->onQueue('logging');
    }

    public function handle(): void
    {
        LogAktivitasUjian::create([
            'sesi_peserta_id' => $this->sesiPesertaId,
            'tipe_event'      => $this->tipeEvent,
            'detail'          => $this->detail,
            'ip_address'      => $this->ipAddress,
            'created_at'      => $this->createdAt ? \Carbon\Carbon::parse($this->createdAt) : now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[LogAktivitas] Job failed', [
            'sesi_peserta_id' => $this->sesiPesertaId,
            'tipe_event'      => $this->tipeEvent,
            'error'           => $exception->getMessage(),
        ]);
    }
}
