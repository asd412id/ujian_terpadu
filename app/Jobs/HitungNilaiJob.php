<?php

namespace App\Jobs;

use App\Models\SesiPeserta;
use App\Services\PenilaianService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HitungNilaiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        protected string $sesiPesertaId,
        protected string $reason = 'submit',
    ) {
        $this->onQueue('default');
    }

    public function handle(PenilaianService $penilaianService): void
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

        Log::info('[HitungNilai] Scored successfully', [
            'sesi_peserta_id' => $this->sesiPesertaId,
            'reason'          => $this->reason,
            'nilai_akhir'     => $hasil['nilai_akhir'],
        ]);
    }
}
