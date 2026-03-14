<?php

namespace App\Jobs;

use App\Models\SesiPeserta;
use App\Services\PenilaianService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalculateNilaiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900; // 15 minutes max

    public function __construct(
        protected array $filters = [],
        protected ?string $userId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(PenilaianService $penilaianService): void
    {
        $cacheKey = 'recalculate_progress';

        Cache::put($cacheKey, [
            'status'  => 'processing',
            'total'   => 0,
            'updated' => 0,
            'changed' => 0,
        ], 3600);

        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai']);

        if (! empty($this->filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $this->filters['paket_id']));
        }

        if (! empty($this->filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $this->filters['sekolah_id']));
        }

        $total = $query->count();
        $updated = 0;
        $changed = 0;

        Cache::put($cacheKey, [
            'status'  => 'processing',
            'total'   => $total,
            'updated' => 0,
            'changed' => 0,
        ], 3600);

        Log::info('[Recalculate] Started', [
            'total'   => $total,
            'filters' => $this->filters,
            'user_id' => $this->userId,
        ]);

        $query->select('id')
            ->chunkById(100, function ($chunk) use ($penilaianService, &$updated, &$changed, $cacheKey) {
                foreach ($chunk as $sp) {
                    try {
                        $sesiPeserta = SesiPeserta::with(['jawaban.soal.opsiJawaban', 'sesi.paket.paketSoal.soal'])
                            ->find($sp->id);

                        if (! $sesiPeserta) {
                            continue;
                        }

                        $oldNilai = (float) $sesiPeserta->nilai_akhir;
                        $hasil = $penilaianService->hitungNilai($sesiPeserta);
                        $newNilai = (float) $hasil['nilai_akhir'];

                        if (abs($oldNilai - $newNilai) > 0.001) {
                            $sesiPeserta->update($hasil);
                            $changed++;
                        }

                        $updated++;
                    } catch (\Throwable $e) {
                        Log::warning('[Recalculate] Error on SP', [
                            'sesi_peserta_id' => $sp->id,
                            'error' => $e->getMessage(),
                        ]);
                        $updated++;
                    }
                }

                // Update progress every chunk
                Cache::put($cacheKey, [
                    'status'  => 'processing',
                    'total'   => Cache::get($cacheKey)['total'] ?? 0,
                    'updated' => $updated,
                    'changed' => $changed,
                ], 3600);
            });

        Cache::put($cacheKey, [
            'status'  => 'done',
            'total'   => $total,
            'updated' => $updated,
            'changed' => $changed,
        ], 3600);

        Log::info('[Recalculate] Completed', [
            'total'   => $total,
            'updated' => $updated,
            'changed' => $changed,
        ]);
    }
}
