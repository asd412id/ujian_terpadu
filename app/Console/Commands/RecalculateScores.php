<?php

namespace App\Console\Commands;

use App\Models\SesiPeserta;
use App\Services\PenilaianService;
use Illuminate\Console\Command;

class RecalculateScores extends Command
{
    protected $signature = 'ujian:recalculate-scores
                            {--sesi= : Recalculate hanya untuk sesi_ujian_id tertentu}
                            {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Recalculate nilai semua sesi_peserta yang sudah submit/dinilai menggunakan formula terbaru';

    public function handle(PenilaianService $penilaianService): int
    {
        $dryRun = $this->option('dry-run');
        $sesiId = $this->option('sesi');

        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai'])
            ->with(['sesi.paket.paketSoal.soal', 'jawaban.soal.opsiJawaban']);

        if ($sesiId) {
            $query->where('sesi_id', $sesiId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('Tidak ada sesi peserta yang perlu di-recalculate.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Recalculating {$total} sesi peserta...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $changed = 0;
        $errors  = 0;

        $query->chunkById(50, function ($chunk) use ($penilaianService, $dryRun, &$updated, &$changed, &$errors, $bar) {
            foreach ($chunk as $sp) {
                try {
                    $oldNilai = (float) $sp->nilai_akhir;
                    $oldBenar = (int) $sp->jumlah_benar;
                    $oldSalah = (int) $sp->jumlah_salah;

                    $hasil = $penilaianService->hitungNilai($sp);

                    $newNilai = (float) $hasil['nilai_akhir'];
                    $newBenar = (int) $hasil['jumlah_benar'];
                    $newSalah = (int) $hasil['jumlah_salah'];

                    if ($oldNilai !== $newNilai || $oldBenar !== $newBenar || $oldSalah !== $newSalah) {
                        $changed++;

                        if ($this->output->isVerbose()) {
                            $pesertaNama = $sp->peserta?->nama ?? $sp->peserta_id;
                            $this->newLine();
                            $this->comment("  {$pesertaNama}: nilai {$oldNilai} → {$newNilai}, benar {$oldBenar} → {$newBenar}, salah {$oldSalah} → {$newSalah}");
                        }

                        if (! $dryRun) {
                            $sp->update($hasil);
                        }
                    }

                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  Error sesi_peserta {$sp->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Selesai: {$updated} diproses, {$changed} nilai berubah, {$errors} error.");

        if ($dryRun && $changed > 0) {
            $this->warn('Jalankan tanpa --dry-run untuk menyimpan perubahan.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
