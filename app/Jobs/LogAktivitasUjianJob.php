<?php

namespace App\Jobs;

use App\Models\LogAktivitasUjian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAktivitasUjianJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        protected string $sesiPesertaId,
        protected string $tipeEvent,
        protected array $detail = [],
        protected ?string $ipAddress = null,
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
            'created_at'      => now(),
        ]);
    }
}
