<?php

namespace App\Services;

use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Repositories\SesiUjianRepository;
use App\Repositories\SekolahRepository;
use Illuminate\Support\Facades\DB;

class SesiUjianService
{
    public function __construct(
        protected PenilaianService $penilaianService,
        protected SesiUjianRepository $repository,
        protected SekolahRepository $sekolahRepository
    ) {}

    public function createSesi(PaketUjian $paket, array $data): SesiUjian
    {
        $data['paket_id'] = $paket->id;
        $data['status']   = $data['status'] ?? 'persiapan';

        $sesi = $this->repository->createSesi($data);

        $this->autoSyncPeserta($sesi);

        return $sesi;
    }

    public function updateSesi(SesiUjian $sesi, array $data): SesiUjian
    {
        $oldStatus = $sesi->status;
        $newStatus = $data['status'] ?? $oldStatus;

        // Block: cannot revert to 'persiapan' if there are active peserta
        if ($newStatus === 'persiapan' && $oldStatus !== 'persiapan') {
            $activeCount = $sesi->sesiPeserta()
                ->whereIn('status', ['login', 'mengerjakan', 'submit', 'dinilai'])
                ->count();
            if ($activeCount > 0) {
                throw new \RuntimeException(
                    "Tidak dapat mengembalikan sesi ke persiapan. {$activeCount} peserta sudah mengikuti ujian."
                );
            }
        }

        // Auto force-submit all active peserta when sesi moves to 'selesai'
        if ($newStatus === 'selesai' && $oldStatus === 'berlangsung') {
            $this->forceSubmitActivePeserta($sesi);
        }

        $sesi->update($data);
        return $sesi->fresh();
    }

    /**
     * Force submit all peserta currently in 'mengerjakan' or 'login' status.
     * Called when admin changes sesi status to 'selesai'.
     */
    public function forceSubmitActivePeserta(SesiUjian $sesi): int
    {
        $count = 0;

        $sesi->sesiPeserta()
            ->whereIn('status', ['login', 'mengerjakan'])
            ->chunkById(100, function ($chunk) use (&$count, $sesi) {
                foreach ($chunk as $sp) {
                    $submitAt = now();
                    $durasiDetik = $sp->mulai_at
                        ? (int) $sp->mulai_at->diffInSeconds($submitAt, false)
                        : 0;

                    $sp->update([
                        'status'              => 'submit',
                        'submit_at'           => $submitAt,
                        'durasi_aktual_detik' => $durasiDetik,
                    ]);

                    \App\Jobs\HitungNilaiJob::dispatch($sp->id, 'admin_force_submit');

                    $this->repository->logAktivitas([
                        'sesi_peserta_id' => $sp->id,
                        'tipe_event'      => 'submit_ujian',
                        'detail'          => [
                            'reason'  => 'admin_force_submit',
                            'durasi'  => $durasiDetik,
                            'trigger' => 'sesi_status_selesai',
                        ],
                        'created_at'      => $submitAt,
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Reset sesi peserta agar bisa mengulang ujian.
     * Menghapus jawaban, log aktivitas, dan mereset status ke 'terdaftar'.
     */
    public function resetSesiPeserta(\App\Models\SesiPeserta $sp): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($sp) {
            // 1. Hapus jawaban peserta
            $sp->jawaban()->delete();

            // 2. Hapus log aktivitas
            $sp->logAktivitas()->delete();

            // 3. Reset sesi peserta fields
            $sp->update([
                'status'              => 'terdaftar',
                'token_ujian'         => null,
                'urutan_soal'         => null,
                'urutan_opsi'         => null,
                'ip_address'          => null,
                'browser_info'        => null,
                'device_type'         => null,
                'mulai_at'            => null,
                'submit_at'           => null,
                'durasi_aktual_detik' => null,
                'soal_terjawab'       => 0,
                'soal_ditandai'       => 0,
                'nilai_akhir'         => null,
                'nilai_benar'         => null,
                'jumlah_benar'        => null,
                'jumlah_salah'        => null,
                'jumlah_kosong'       => null,
            ]);

            // 4. Clear cache soal jika ada
            $paketId = $sp->sesi?->paket_id;
            if ($paketId) {
                \Illuminate\Support\Facades\Cache::forget("paket_soal_{$paketId}_sp_{$sp->id}");
            }

            // 5. Log aktivitas reset
            $this->repository->logAktivitas([
                'sesi_peserta_id' => $sp->id,
                'tipe_event'      => 'reset_ujian',
                'detail'          => [
                    'reason'     => 'admin_reset',
                    'reset_by'   => auth()->id(),
                    'reset_at'   => now()->toISOString(),
                ],
                'created_at'      => now(),
            ]);
        });
    }

    /**
     * Count active peserta (login + mengerjakan) for a sesi.
     */
    public function countActivePeserta(SesiUjian $sesi): int
    {
        return $sesi->sesiPeserta()
            ->whereIn('status', ['login', 'mengerjakan'])
            ->count();
    }

    public function deleteSesi(SesiUjian $sesi): bool
    {
        if ($sesi->status === 'berlangsung') {
            throw new \RuntimeException('Tidak dapat menghapus sesi yang sedang berlangsung.');
        }

        if ($sesi->sesiPeserta()->whereIn('status', ['login', 'mengerjakan'])->exists()) {
            throw new \RuntimeException('Tidak dapat menghapus sesi yang sedang diikuti peserta.');
        }

        if ($sesi->sesiPeserta()->whereIn('status', ['submit', 'dinilai'])->exists()) {
            throw new \RuntimeException('Tidak dapat menghapus sesi yang sudah memiliki peserta submit atau dinilai.');
        }

        return (bool) $sesi->delete();
    }

    public function cancelPendingSesiByPaket(PaketUjian $paket): int
    {
        return $paket->sesi()
            ->where('status', 'persiapan')
            ->update(['status' => 'selesai']);
    }

    /**
     * Get peserta for a sesi with their sesi_peserta status.
     */
    public function getPesertaSesi(SesiUjian $sesi, ?string $search = null, int $perPage = 50)
    {
        return $sesi->peserta()
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            }))
            ->with('sekolah')
            ->orderBy('nama')
            ->paginate($perPage, ['*'], 'enrolled_page');
    }

    /**
     * Get all eligible peserta based on paket filter (jenjang + sekolah).
     * Returns peserta NOT yet in sesi.
     */
    public function getAvailablePeserta(SesiUjian $sesi, ?string $search = null, ?string $sekolahId = null, int $perPage = 50)
    {
        $paket = $sesi->paket;
        return $this->repository->getAvailablePeserta(
            $sesi, $paket->jenjang, $paket->sekolah_id, $sekolahId, $search, $perPage
        );
    }

    /**
     * Count enrolled peserta for stats (not affected by pagination).
     */
    public function countEnrolled(SesiUjian $sesi): int
    {
        return $sesi->sesiPeserta()->count();
    }

    /**
     * Count available peserta for stats (not affected by pagination).
     */
    public function countAvailable(SesiUjian $sesi): int
    {
        $paket = $sesi->paket;
        return $this->repository->countAvailablePeserta($sesi, $paket->jenjang, $paket->sekolah_id);
    }

    /**
     * Auto-sync peserta to sesi based on paket filter.
     * Only runs if sesi is NOT manually overridden.
     */
    public function autoSyncPeserta(SesiUjian $sesi): int
    {
        if ($sesi->is_peserta_override) {
            return 0;
        }

        return $this->syncNewPeserta($sesi);
    }

    /**
     * Sync new peserta that match paket filter but aren't enrolled yet.
     * Works regardless of override mode — used by both auto-sync and manual sync button.
     */
    public function syncNewPeserta(SesiUjian $sesi): int
    {
        $paket = $sesi->paket;

        $pesertaIds = $this->repository->getEligiblePesertaIds($paket->jenjang, $paket->sekolah_id);
        $existingIds = $sesi->sesiPeserta()->pluck('peserta_id');
        $newIds = $pesertaIds->diff($existingIds);

        return $this->repository->insertSesiPeserta($sesi->id, $newIds);
    }

    /**
     * Sync all non-override sesi for a paket (called when paket filter changes).
     */
    public function syncAllSesiForPaket(PaketUjian $paket): void
    {
        $paket->sesi()
            ->where('is_peserta_override', false)
            ->where('status', 'persiapan')
            ->each(function ($sesi) {
                DB::transaction(function () use ($sesi) {
                    $sesi->sesiPeserta()->where('status', 'terdaftar')->delete();
                    $this->autoSyncPeserta($sesi);
                });
            });
    }

    /**
     * Manually add peserta to sesi (marks sesi as override).
     */
    public function addPesertaToSesi(SesiUjian $sesi, array $pesertaIds): int
    {
        $sesi->update(['is_peserta_override' => true]);

        $existingIds = $sesi->sesiPeserta()->pluck('peserta_id');
        $newIds = collect($pesertaIds)->diff($existingIds);

        return $this->repository->insertSesiPeserta($sesi->id, $newIds);
    }

    /**
     * Remove peserta from sesi (only if status = terdaftar).
     */
    public function removePesertaFromSesi(SesiUjian $sesi, array $pesertaIds): int
    {
        $sesi->update(['is_peserta_override' => true]);

        return $sesi->sesiPeserta()
            ->whereIn('peserta_id', $pesertaIds)
            ->where('status', 'terdaftar')
            ->delete();
    }

    /**
     * Reset sesi to auto-sync mode (remove override, re-sync from paket filter).
     */
    public function resetToAutoSync(SesiUjian $sesi): int
    {
        $sesi->sesiPeserta()->where('status', 'terdaftar')->delete();
        $sesi->update(['is_peserta_override' => false]);

        return $this->autoSyncPeserta($sesi);
    }

    /**
     * Get list of pengawas users for dropdown.
     */
    public function getPengawasList(): mixed
    {
        return $this->repository->getPengawasList();
    }

    /**
     * Get sekolah list filtered by paket's jenjang and sekolah_id.
     */
    public function getSekolahListForPaket(PaketUjian $paket): mixed
    {
        return $this->sekolahRepository->getForPaket($paket->jenjang, $paket->sekolah_id);
    }
}
