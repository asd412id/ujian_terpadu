<?php

namespace App\Console\Commands;

use App\Models\SesiPeserta;
use App\Services\PenilaianService;
use App\Models\LogAktivitasUjian;
use Illuminate\Console\Command;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'ujian:auto-submit';
    protected $description = 'Auto-submit ujian peserta yang sudah melewati batas waktu tapi masih berstatus mengerjakan';

    public function handle(PenilaianService $penilaianService): int
    {
        $count = 0;

        // Use DB-level filtering + chunkById to avoid loading all into memory
        SesiPeserta::where('status', 'mengerjakan')
            ->whereNotNull('mulai_at')
            ->whereHas('sesi.paket', fn ($q) => $q->where('durasi_menit', '>', 0))
            ->with(['sesi.paket'])
            ->chunkById(50, function ($chunk) use ($penilaianService, &$count) {
                foreach ($chunk as $sp) {
                    $durasiDetik = ($sp->sesi->paket->durasi_menit ?? 0) * 60;
                    if ($durasiDetik <= 0) continue;

                    $elapsed = (int) $sp->mulai_at->diffInSeconds(now(), false);
                    if ($elapsed <= $durasiDetik) continue;

                    $submitAt = $sp->mulai_at->copy()->addSeconds($durasiDetik);

                    $sp->update([
                        'status'              => 'submit',
                        'submit_at'           => $submitAt,
                        'durasi_aktual_detik' => $durasiDetik,
                    ]);

                    $hasil = $penilaianService->hitungNilai($sp);
                    $sp->update($hasil);

                    LogAktivitasUjian::create([
                        'sesi_peserta_id' => $sp->id,
                        'tipe_event'      => 'submit_ujian',
                        'detail'          => ['reason' => 'auto_submit_server_timeout', 'durasi' => $durasiDetik],
                        'created_at'      => $submitAt,
                    ]);

                    $count++;
                }
            });

        if ($count === 0) {
            $this->info('Tidak ada ujian expired yang perlu di-submit.');
        } else {
            $this->info("Berhasil auto-submit {$count} ujian yang expired.");
        }

        return self::SUCCESS;
    }
}
