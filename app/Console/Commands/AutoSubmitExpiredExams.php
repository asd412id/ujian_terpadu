<?php

namespace App\Console\Commands;

use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\LogAktivitasUjian;
use Illuminate\Console\Command;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'ujian:auto-submit';
    protected $description = 'Auto-submit ujian peserta yang sudah melewati batas waktu atau sesi sudah berakhir';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        // --- Phase 1: Auto-end sesi that passed waktu_selesai ---
        // Transition berlangsung → selesai when waktu_selesai has passed
        $endedSesi = SesiUjian::where('status', 'berlangsung')
            ->whereNotNull('waktu_selesai')
            ->where('waktu_selesai', '<=', $now)
            ->get();

        foreach ($endedSesi as $sesi) {
            $sesi->update(['status' => 'selesai']);
            $this->info("Sesi '{$sesi->nama_sesi}' (ID: {$sesi->id}) otomatis diakhiri.");
        }

        // --- Phase 2: Force-submit all active peserta in ended sesi ---
        $sesiSelesaiCount = 0;
        SesiPeserta::whereIn('status', ['login', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'selesai'))
            ->with(['sesi.paket'])
            ->chunkById(50, function ($chunk) use ($now, &$sesiSelesaiCount) {
                foreach ($chunk as $sp) {
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

                    LogAktivitasUjian::create([
                        'sesi_peserta_id' => $sp->id,
                        'tipe_event'      => 'submit_ujian',
                        'detail'          => [
                            'reason'  => 'auto_submit_sesi_ended',
                            'durasi'  => max(0, $durasiDetik),
                            'trigger' => 'sesi_waktu_selesai',
                        ],
                        'created_at'      => $submitAt,
                    ]);

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
            ->chunkById(50, function ($chunk) use ($now, &$durasiCount) {
                foreach ($chunk as $sp) {
                    $durasiDetik = ($sp->sesi->paket->durasi_menit ?? 0) * 60;
                    if ($durasiDetik <= 0) continue;

                    $elapsed = (int) $sp->mulai_at->diffInSeconds($now, false);
                    if ($elapsed <= $durasiDetik) continue;

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

                    LogAktivitasUjian::create([
                        'sesi_peserta_id' => $sp->id,
                        'tipe_event'      => 'submit_ujian',
                        'detail'          => [
                            'reason' => 'auto_submit_server_timeout',
                            'durasi' => max(0, $durasiAktual),
                        ],
                        'created_at'      => $submitAt,
                    ]);

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
