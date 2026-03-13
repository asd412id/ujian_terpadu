<?php

namespace App\Services;

use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Repositories\SesiUjianRepository;
use App\Repositories\SekolahRepository;

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
        $activePeserta = $sesi->sesiPeserta()
            ->whereIn('status', ['login', 'mengerjakan'])
            ->get();

        if ($activePeserta->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($activePeserta as $sp) {
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

        return $count;
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
                $sesi->sesiPeserta()->where('status', 'terdaftar')->delete();
                $this->autoSyncPeserta($sesi);
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
