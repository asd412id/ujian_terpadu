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
        // Find all sesi_peserta still in 'mengerjakan' status where time has expired
        $expired = SesiPeserta::where('status', 'mengerjakan')
            ->whereNotNull('mulai_at')
            ->whereHas('sesi.paket')
            ->with(['sesi.paket'])
            ->get()
            ->filter(function (SesiPeserta $sp) {
                $durasiDetik = ($sp->sesi->paket->durasi_menit ?? 0) * 60;
                if ($durasiDetik <= 0) return false;
                $elapsed = (int) $sp->mulai_at->diffInSeconds(now(), false);
                return $elapsed > $durasiDetik;
            });

        if ($expired->isEmpty()) {
            $this->info('Tidak ada ujian expired yang perlu di-submit.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $sp) {
            $durasiDetik = ($sp->sesi->paket->durasi_menit ?? 0) * 60;
            $submitAt = $sp->mulai_at->copy()->addSeconds($durasiDetik);

            $sp->update([
                'status'              => 'submit',
                'submit_at'           => $submitAt,
                'durasi_aktual_detik' => $durasiDetik,
            ]);

            // Calculate score
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

        $this->info("Berhasil auto-submit {$count} ujian yang expired.");
        return self::SUCCESS;
    }
}
