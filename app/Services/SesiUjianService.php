<?php

namespace App\Services;

use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Repositories\JawabanRepository;
use App\Repositories\SesiUjianRepository;
use App\Repositories\SekolahRepository;
use App\Support\SearchHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SesiUjianService
{
    public function __construct(
        protected PenilaianService $penilaianService,
        protected SesiUjianRepository $repository,
        protected SekolahRepository $sekolahRepository,
        protected RedisExamService $redisExam,
        protected JawabanRepository $jawabanRepository
    ) {}

    public function createSesi(PaketUjian $paket, array $data): SesiUjian
    {
        $pesertaMode = $data['peserta_mode'] ?? 'manual';
        unset($data['peserta_mode']);

        $data['paket_id'] = $paket->id;
        $data['status']   = $data['status'] ?? 'persiapan';
        $data['is_peserta_override'] = $pesertaMode !== 'all';

        $sesi = $this->repository->createSesi($data);

        if ($pesertaMode === 'all') {
            $this->autoSyncPeserta($sesi);
        }

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
                    // Flush buffered answers from Redis to DB before scoring
                    if (!$this->redisExam->forceFlush($sp->id, $this->jawabanRepository)) {
                        Log::warning('[SesiUjian] Redis flush failed during force submit, proceeding with DB data', [
                            'sesi_peserta_id' => $sp->id,
                            'sesi_id'         => $sesi->id,
                        ]);
                    }

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
        DB::transaction(function () use ($sp) {
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

            // 4. Clean up Redis exam buffer (prevent stale data being re-flushed)
            $this->redisExam->cleanupSession($sp->id);

            // 5. Clear cache soal dan monitoring jika ada
            $paketId = $sp->sesi?->paket_id;
            if ($paketId) {
                Cache::forget("paket_soal_{$paketId}_sp_{$sp->id}");
            }
            if ($sp->sesi_id) {
                Cache::forget("sesi_live_{$sp->sesi_id}");
            }
            Cache::forget("lobby_available:{$sp->peserta_id}");
            Cache::forget("lobby_history:{$sp->peserta_id}");

            // 6. Log aktivitas reset
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
                $s = SearchHelper::containsLike($search);
                $q->where('nama', 'like', $s)
                  ->orWhere('nisn', 'like', $s)
                  ->orWhere('nis', 'like', $s)
                  ->orWhere('kelas', 'like', $s)
                  ->orWhere('jurusan', 'like', $s)
                  ->orWhereHas('sekolah', fn ($sekolahQuery) => $sekolahQuery->where('nama', 'like', $s));
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
     * Deduplicates by NIS per sekolah to prevent enrolling duplicate person records.
     */
    public function syncNewPeserta(SesiUjian $sesi): int
    {
        return $this->insertPesertaWithSesiLock($sesi, function (SesiUjian $lockedSesi) {
            $paket = $lockedSesi->paket;
            $pesertaIds = $this->repository->getEligiblePesertaIds($paket->jenjang, $paket->sekolah_id);
            $existingIds = $lockedSesi->sesiPeserta()->pluck('peserta_id');

            $newIds = $pesertaIds->diff($existingIds);

            if ($newIds->isEmpty()) {
                return $newIds;
            }

            // Collect NIS keys already enrolled in this sesi
            $enrolledNisKeys = [];
            \App\Models\Peserta::whereIn('id', $existingIds)
                ->whereNotNull('nis')->where('nis', '!=', '')
                ->select('nis', 'sekolah_id')
                ->each(function ($p) use (&$enrolledNisKeys) {
                    $enrolledNisKeys[$p->nis . '|' . $p->sekolah_id] = true;
                });

            // Deduplicate new peserta: skip if NIS+sekolah already enrolled or already seen
            $newPeserta = \App\Models\Peserta::whereIn('id', $newIds)
                ->orderBy('nama')
                ->get(['id', 'nis', 'sekolah_id']);

            $seenNisKeys = $enrolledNisKeys;
            $deduped = collect();

            foreach ($newPeserta as $p) {
                if ($p->nis && $p->nis !== '') {
                    $key = $p->nis . '|' . $p->sekolah_id;
                    if (isset($seenNisKeys[$key])) {
                        continue;
                    }
                    $seenNisKeys[$key] = true;
                }
                $deduped->push($p->id);
            }

            return $deduped;
        });
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
        return $this->insertPesertaWithSesiLock($sesi, function (SesiUjian $lockedSesi) use ($pesertaIds) {
            $paket = $lockedSesi->paket;
            $allowedIds = $this->repository->getAvailablePesertaIds(
                $lockedSesi,
                $paket->jenjang,
                $paket->sekolah_id,
                null,
                null,
            );

            return collect($pesertaIds)
                ->filter()
                ->unique()
                ->intersect($allowedIds)
                ->values();
        }, true);
    }

    public function addAllAvailablePeserta(SesiUjian $sesi, ?string $search = null, ?string $sekolahId = null): int
    {
        return $this->insertPesertaWithSesiLock($sesi, function (SesiUjian $lockedSesi) use ($search, $sekolahId) {
            $paket = $lockedSesi->paket;

            return $this->repository->getAvailablePesertaIds(
                $lockedSesi,
                $paket->jenjang,
                $paket->sekolah_id,
                $sekolahId,
                $search,
            );
        }, true);
    }

    private function insertPesertaWithSesiLock(SesiUjian $sesi, callable $resolvePesertaIds, bool $markOverride = false): int
    {
        return $this->withPersiapanSesiLock($sesi, function (SesiUjian $lockedSesi) use ($resolvePesertaIds, $markOverride) {
            $newIds = $this->limitPesertaByCapacity($lockedSesi, $resolvePesertaIds($lockedSesi));

            if ($newIds->isEmpty()) {
                return 0;
            }

            $inserted = $this->repository->insertSesiPeserta($lockedSesi->id, $newIds);

            if ($markOverride && $inserted > 0) {
                $lockedSesi->update(['is_peserta_override' => true]);
            }

            return $inserted;
        });
    }

    private function limitPesertaByCapacity(SesiUjian $sesi, Collection $pesertaIds): Collection
    {
        if ($pesertaIds->isEmpty() || !$sesi->kapasitas) {
            return $pesertaIds->values();
        }

        $remainingCapacity = max($sesi->kapasitas - $sesi->sesiPeserta()->count(), 0);

        if ($remainingCapacity === 0) {
            return collect();
        }

        return $pesertaIds->values()->take($remainingCapacity);
    }

    /**
     * Remove peserta from sesi (only if status = terdaftar).
     */
    public function removePesertaFromSesi(SesiUjian $sesi, array $pesertaIds): int
    {
        return $this->withPersiapanSesiLock($sesi, function (SesiUjian $lockedSesi) use ($pesertaIds) {
            $count = $lockedSesi->sesiPeserta()
                ->whereIn('peserta_id', collect($pesertaIds)->filter()->unique()->values())
                ->where('status', 'terdaftar')
                ->delete();

            if ($count > 0) {
                $lockedSesi->update(['is_peserta_override' => true]);
            }

            return $count;
        });
    }

    /**
     * Reset sesi to auto-sync mode (remove override, re-sync from paket filter).
     */
    public function resetToAutoSync(SesiUjian $sesi): int
    {
        return $this->withPersiapanSesiLock($sesi, function (SesiUjian $lockedSesi) {
            $lockedSesi->sesiPeserta()->where('status', 'terdaftar')->delete();
            $lockedSesi->update(['is_peserta_override' => false]);

            return $this->syncNewPeserta($lockedSesi);
        });
    }

    /**
     * Get all enrolled peserta IDs (status = terdaftar) for bulk selection across pages.
     */
    public function getAllEnrolledPesertaIds(SesiUjian $sesi): array
    {
        return $sesi->sesiPeserta()
            ->where('status', 'terdaftar')
            ->pluck('peserta_id')
            ->toArray();
    }

    /**
     * Remove all peserta with status 'terdaftar' from sesi.
     */
    public function removeAllPesertaFromSesi(SesiUjian $sesi): int
    {
        return $this->withPersiapanSesiLock($sesi, function (SesiUjian $lockedSesi) {
            $count = $lockedSesi->sesiPeserta()
                ->where('status', 'terdaftar')
                ->delete();

            if ($count > 0) {
                $lockedSesi->update(['is_peserta_override' => true]);
            }

            return $count;
        });
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

    private function withPersiapanSesiLock(SesiUjian $sesi, callable $callback): mixed
    {
        return DB::transaction(function () use ($sesi, $callback) {
            $lockedSesi = $this->repository->lockSesi($sesi->id);
            $this->ensurePersiapanStatus($lockedSesi);

            return $callback($lockedSesi);
        });
    }

    private function ensurePersiapanStatus(SesiUjian $sesi): void
    {
        if ($sesi->status !== 'persiapan') {
            throw new \RuntimeException('Peserta hanya bisa diubah saat sesi masih berstatus persiapan.');
        }
    }
}
