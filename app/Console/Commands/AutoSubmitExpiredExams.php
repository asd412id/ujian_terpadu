<?php

namespace App\Console\Commands;

use App\Jobs\LogAktivitasUjianJob;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Repositories\JawabanRepository;
use App\Services\RedisExamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'ujian:auto-submit';
    protected $description = 'Auto-submit ujian peserta yang sudah melewati batas waktu atau sesi sudah berakhir';

    public function handle(): int
    {
        $now = now();
        $count = 0;
        $redisExam = app(RedisExamService::class);
        $jawabanRepo = app(JawabanRepository::class);

        // --- Phase 1: Auto-end sesi that passed waktu_selesai ---
        // Transition berlangsung → selesai when waktu_selesai has passed
        SesiUjian::where('status', 'berlangsung')
            ->whereNotNull('waktu_selesai')
            ->where('waktu_selesai', '<=', $now)
            ->chunkById(50, function ($sesiChunk) {
                foreach ($sesiChunk as $sesi) {
                    $sesi->update(['status' => 'selesai']);
                    $this->info("Sesi '{$sesi->nama_sesi}' (ID: {$sesi->id}) otomatis diakhiri.");
                }
            });

        // --- Phase 2: Force-submit all active peserta in ended sesi ---
        $sesiSelesaiCount = 0;
        SesiPeserta::whereIn('status', ['login', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'selesai'))
            ->with(['sesi.paket'])
            ->chunkById(50, function ($chunk) use ($now, &$sesiSelesaiCount, $redisExam, $jawabanRepo) {
                foreach ($chunk as $sp) {
                    // Flush any buffered answers from Redis to DB before submitting
                    if (!$redisExam->forceFlush($sp->id, $jawabanRepo)) {
                        Log::warning('[AutoSubmit] Redis flush failed, proceeding with DB data', [
                            'sesi_peserta_id' => $sp->id,
                            'phase' => 'sesi_ended',
                        ]);
                    }

                    $submitAt = $sp->sesi->waktu_selesai ?? $now;
                    $durasiDetik = $sp->mulai_at
                        ? (int) $sp->mulai_at->diffInSeconds($submitAt, false)
                        : 0;

                    $sp->update([
                        'status'              => 'submit',
                        'submit_at'           => $submitAt,
                        'durasi_aktual_detik' => max(0, $durasiDetik),
                    ]);

                    \App\Jobs\HitungNilaiJob::dispatch($sp->id, 'auto_submit_sesi_ended');

                    LogAktivitasUjianJob::dispatch(
                        $sp->id,
                        'submit_ujian',
                        [
                            'reason'  => 'auto_submit_sesi_ended',
                            'durasi'  => max(0, $durasiDetik),
                            'trigger' => 'sesi_waktu_selesai',
                        ],
                        null,
                        $submitAt instanceof \Carbon\Carbon ? $submitAt->toIso8601String() : null,
                    );

                    $sesiSelesaiCount++;
                }
            });
        $count += $sesiSelesaiCount;

        // --- Phase 3: Auto-submit individual expired exams (durasi habis) ---
        $durasiCount = 0;
        SesiPeserta::where('status', 'mengerjakan')
            ->whereNotNull('mulai_at')
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->whereHas('sesi.paket', fn ($q) => $q->where('durasi_menit', '>', 0))
            ->with(['sesi.paket'])
            ->chunkById(50, function ($chunk) use ($now, &$durasiCount, $redisExam, $jawabanRepo) {
                foreach ($chunk as $sp) {
                    $durasiDetik = ($sp->sesi->paket->durasi_menit ?? 0) * 60;
                    if ($durasiDetik <= 0) continue;

                    $elapsed = (int) $sp->mulai_at->diffInSeconds($now, false);
                    if ($elapsed <= $durasiDetik) continue;

                    // Flush any buffered answers from Redis to DB before submitting
                    if (!$redisExam->forceFlush($sp->id, $jawabanRepo)) {
                        Log::warning('[AutoSubmit] Redis flush failed, proceeding with DB data', [
                            'sesi_peserta_id' => $sp->id,
                            'phase' => 'durasi_expired',
                        ]);
                    }

                    $submitAt = $sp->mulai_at->copy()->addSeconds($durasiDetik);

                    // Also cap by sesi waktu_selesai
                    $waktuSelesai = $sp->sesi->waktu_selesai;
                    if ($waktuSelesai && $waktuSelesai->lt($submitAt)) {
                        $submitAt = $waktuSelesai;
                    }

                    $durasiAktual = (int) $sp->mulai_at->diffInSeconds($submitAt, false);

                    $sp->update([
                        'status'              => 'submit',
                        'submit_at'           => $submitAt,
                        'durasi_aktual_detik' => max(0, $durasiAktual),
                    ]);

                    \App\Jobs\HitungNilaiJob::dispatch($sp->id, 'auto_submit_server_timeout');

                    LogAktivitasUjianJob::dispatch(
                        $sp->id,
                        'submit_ujian',
                        [
                            'reason' => 'auto_submit_server_timeout',
                            'durasi' => max(0, $durasiAktual),
                        ],
                        null,
                        $submitAt->toIso8601String(),
                    );

                    $durasiCount++;
                }
            });
        $count += $durasiCount;

        if ($count === 0) {
            $this->info('Tidak ada ujian expired yang perlu di-submit.');
        } else {
            $this->info("Berhasil auto-submit: {$sesiSelesaiCount} (sesi ended) + {$durasiCount} (durasi expired) = {$count} total.");
        }

        return self::SUCCESS;
    }
}
