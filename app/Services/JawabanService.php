<?php

namespace App\Services;

use App\Jobs\LogAktivitasUjianJob;
use App\Models\SesiPeserta;
use App\Repositories\JawabanRepository;
use App\Repositories\SoalRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JawabanService
{
    public function __construct(
        protected JawabanRepository $repository,
        protected SoalRepository $soalRepository,
        protected PenilaianService $penilaianService
    ) {}

    /**
     * Simpan jawaban (single answer save).
     */
    public function simpanJawaban(string $sesiPesertaId, string $soalId, mixed $jawaban, ?string $idempotencyKey = null): mixed
    {
        $sesiPeserta = $this->repository->findActiveSesiPeserta($sesiPesertaId);

        if ($sesiPeserta->sisa_waktu_detik <= 0) {
            throw ValidationException::withMessages([
                'waktu' => 'Waktu ujian telah habis.',
            ]);
        }

        $jawabanData = $this->parseJawaban($jawaban);

        $result = $this->repository->createOrUpdate($sesiPesertaId, $soalId, array_merge($jawabanData, [
            'idempotency_key' => $idempotencyKey,
            'waktu_jawab'     => now(),
        ]));

        $terjawab = $this->repository->countAnswered($sesiPesertaId);
        $sesiPeserta->update(['soal_terjawab' => $terjawab]);

        return $result;
    }

    /**
     * Sync offline answers — batch save from IndexedDB.
     */
    public function syncOfflineAnswers(string $sesiToken, array $answers, array $requestMeta = [], bool $isFinalSubmit = false): array
    {
        $sesiPeserta = $this->repository->findSesiPesertaByTokenWithPaket(
            $sesiToken, ['mengerjakan', 'login', 'submit', 'dinilai']
        );

        $isAlreadySubmitted = in_array($sesiPeserta->status, ['submit', 'dinilai']);

        // C1 fix: Reject new answers if already submitted/graded (only allow idempotent re-sync)
        // Late sync is only allowed within 5 minutes of submit_at
        if ($isAlreadySubmitted) {
            $submitAt = $sesiPeserta->submit_at;
            $lateSyncWindowSeconds = 300; // 5 minutes
            if ($submitAt && now()->diffInSeconds($submitAt, false) < -$lateSyncWindowSeconds) {
                return [
                    'accepted'    => false,
                    'synced'      => 0,
                    'skipped'     => count($answers),
                    'errors'      => ['Late sync window expired'],
                    'server_time' => now()->timestamp,
                ];
            }
        }

        // H1 fix: Reject routine sync once exam time expires, but still allow final submit sync.
        if (!$isFinalSubmit && !$isAlreadySubmitted && $sesiPeserta->sisa_waktu_detik <= 0) {
            return [
                'accepted'    => false,
                'synced'      => 0,
                'skipped'     => count($answers),
                'errors'      => ['Waktu ujian telah habis'],
                'server_time' => now()->timestamp,
            ];
        }

        // H3 fix: Validate soal_ids belong to the assigned paket
        $paketSoalIds = $sesiPeserta->sesi->paket->soal()->pluck('soal.id')->toArray();
        $answers = array_filter($answers, function ($ans) use ($paketSoalIds) {
            return in_array($ans['soal_id'] ?? '', $paketSoalIds);
        });
        $answers = array_values($answers);

        $errors  = [];
        $synced  = 0;
        $skipped = 0;
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            DB::beginTransaction();
            try {
                $incomingKeys = array_filter(array_column($answers, 'idempotency_key'));
                $existingKeys = [];
                if (!empty($incomingKeys)) {
                    $existingKeys = $this->repository->getExistingIdempotencyKeys($incomingKeys);
                }

                $upsertRows = [];
                $now = now();
                $synced = 0;
                $skipped = 0;
                foreach ($answers as $ans) {
                    $key = $ans['idempotency_key'] ?? null;
                    if ($key && isset($existingKeys[$key])) {
                        $skipped++;
                        continue;
                    }

                    $jawabanData = $this->parseJawaban($ans['jawaban'] ?? null);

                    $upsertRows[] = [
                        'sesi_peserta_id' => $sesiPeserta->id,
                        'soal_id'         => $ans['soal_id'],
                        'jawaban_pg'      => isset($jawabanData['jawaban_pg']) ? json_encode($jawabanData['jawaban_pg']) : null,
                        'jawaban_teks'    => $jawabanData['jawaban_teks'],
                        'jawaban_pasangan'=> isset($jawabanData['jawaban_pasangan']) ? json_encode($jawabanData['jawaban_pasangan']) : null,
                        'is_terjawab'     => $jawabanData['is_terjawab'],
                        'idempotency_key' => $key,
                        'waktu_jawab'     => $now,
                        'updated_at'      => $now,
                    ];
                    $synced++;
                }

                $this->repository->bulkUpsert($upsertRows);

                $terjawab = $this->repository->countAnswered($sesiPeserta->id);
                $updateData = ['soal_terjawab' => $terjawab];
                if (isset($requestMeta['soal_ditandai'])) {
                    $updateData['soal_ditandai'] = $requestMeta['soal_ditandai'];
                }
                $sesiPeserta->update($updateData);

                if (!empty($requestMeta['tandai_list']) && is_array($requestMeta['tandai_list'])) {
                    $this->repository->syncTandaiList($sesiPeserta->id, $requestMeta['tandai_list']);
                } elseif (isset($requestMeta['tandai_list']) && empty($requestMeta['tandai_list'])) {
                    $this->repository->clearAllTandai($sesiPeserta->id);
                }

                DB::commit();

                LogAktivitasUjianJob::dispatch(
                    $sesiPeserta->id,
                    'sync_offline',
                    ['synced' => $synced, 'skipped' => $skipped, 'late_sync' => $isAlreadySubmitted],
                    $requestMeta['ip_address'] ?? null,
                );

                if ($isAlreadySubmitted && $synced > 0) {
                    \App\Jobs\HitungNilaiJob::dispatch($sesiPeserta->id, 'rescore_late_sync');

                    LogAktivitasUjianJob::dispatch(
                        $sesiPeserta->id,
                        'rescore_late_sync',
                        ['synced' => $synced],
                        $requestMeta['ip_address'] ?? null,
                    );
                }

                break;
            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                // Retry on lock wait timeout (1205) or deadlock (1213)
                if (in_array($e->errorInfo[1] ?? null, [1205, 1213]) && $attempt < $maxRetries) {
                    usleep(50000 * $attempt);
                    continue;
                }
                throw $e;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return [
            'accepted'    => true,
            'synced'      => $synced,
            'skipped'     => $skipped,
            'errors'      => $errors,
            'server_time' => now()->timestamp,
        ];
    }

    /**
     * Get all jawaban for a sesi peserta.
     */
    public function getJawabanBySesi(string $sesiPesertaId): mixed
    {
        return $this->repository->getBySesiPeserta($sesiPesertaId);
    }

    /**
     * Update a specific jawaban (admin grading only — not exposed to students).
     */
    public function updateJawaban(string $jawabanId, array $data): mixed
    {
        $jawaban = $this->repository->findOrFail($jawabanId);
        // Only allow grading-related fields to be updated
        $allowedFields = ['skor_manual', 'catatan_penilai'];
        $filteredData = array_intersect_key($data, array_flip($allowedFields));
        $jawaban->update($filteredData);
        return $jawaban->fresh();
    }

    /**
     * Get ujian status by token (server-authoritative).
     */
    public function getStatusByToken(string $token): array
    {
        $sesiPeserta = $this->repository->findSesiPesertaByTokenWithPaketAny($token);

        // Count server-side violations for anti-cheat enforcement
        // Only count events that trigger recordViolation() on client: ganti_tab, fullscreen_exit
        $violationCount = \App\Models\LogAktivitasUjian::where('sesi_peserta_id', $sesiPeserta->id)
            ->whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
            ->count();

        return [
            'status'            => $sesiPeserta->status,
            'sesi_status'       => $sesiPeserta->sesi->status ?? 'selesai',
            'elapsed_seconds'   => $sesiPeserta->mulai_at
                ? now()->diffInSeconds($sesiPeserta->mulai_at) : 0,
            'remaining_seconds' => $sesiPeserta->sisa_waktu_detik,
            'durasi_menit'      => $sesiPeserta->sesi->paket->durasi_menit ?? null,
            'waktu_selesai_sesi' => $sesiPeserta->sesi->waktu_selesai?->timestamp,
            'soal_terjawab'     => $sesiPeserta->soal_terjawab,
            'server_timestamp'  => now()->timestamp,
            'is_active'         => in_array($sesiPeserta->status, ['login', 'mengerjakan']),
            'nilai_akhir'       => $sesiPeserta->nilai_akhir,
            'jumlah_benar'      => $sesiPeserta->jumlah_benar,
            'jumlah_salah'      => $sesiPeserta->jumlah_salah,
            'jumlah_kosong'     => $sesiPeserta->jumlah_kosong,
            'tampilkan_hasil'   => (bool) ($sesiPeserta->sesi->paket->tampilkan_hasil ?? false),
            'violation_count'   => $violationCount,
        ];
    }

    /**
     * Submit ujian via API token.
     */
    public function submitByToken(string $token, array $finalAnswers = []): array
    {
        $sesiPeserta = $this->repository->findSesiPesertaByTokenWithPaket(
            $token, ['login', 'mengerjakan', 'submit']
        );

        $syncResult = ['synced' => 0, 'skipped' => 0];

        if ($sesiPeserta->status === 'submit') {
            // Already auto-submitted by server — sync any late answers and queue re-score
            if (!empty($finalAnswers)) {
                try {
                    $syncResult = $this->syncOfflineAnswers($token, $finalAnswers, [], true);
                } catch (\Exception $e) {
                    $this->repository->createLog([
                        'sesi_peserta_id' => $sesiPeserta->id,
                        'tipe_event'      => 'late_submit_sync_error',
                        'detail'          => ['error' => $e->getMessage()],
                        'created_at'      => now(),
                    ]);
                }
            }
            $sesiPeserta->refresh();
            return [
                'message'         => 'Sudah disubmit',
                'nilai_akhir'     => $sesiPeserta->nilai_akhir,
                'synced'          => $syncResult['synced'] ?? 0,
                'skipped'         => $syncResult['skipped'] ?? 0,
                'soal_terjawab'   => $sesiPeserta->soal_terjawab,
            ];
        }

        if (!empty($finalAnswers)) {
            try {
                $syncResult = $this->syncOfflineAnswers($token, $finalAnswers, [], true);
            } catch (\Exception $e) {
                $this->repository->createLog([
                    'sesi_peserta_id' => $sesiPeserta->id,
                    'tipe_event'      => 'final_sync_error',
                    'detail'          => ['error' => $e->getMessage()],
                    'created_at'      => now(),
                ]);
            }
        }

        $wasNewSubmit = false;
        DB::transaction(function () use (&$sesiPeserta, &$wasNewSubmit) {
            $locked = SesiPeserta::whereKey($sesiPeserta->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'submit') {
                $durasi = $locked->mulai_at
                    ? (int) $locked->mulai_at->diffInSeconds(now(), false) : 0;

                $locked->update([
                    'status'              => 'submit',
                    'submit_at'           => now(),
                    'durasi_aktual_detik' => $durasi,
                ]);
                $wasNewSubmit = true;
            }

            $sesiPeserta = $locked->refresh();
        });

        // Dispatch scoring once for the initial submit transition.
        if ($wasNewSubmit) {
            \App\Jobs\HitungNilaiJob::dispatch($sesiPeserta->id, 'submit');
        }

        $sesiPeserta->refresh();
        return [
            'message'         => $wasNewSubmit ? 'Ujian berhasil disubmit' : 'Sudah disubmit',
            'redirect'        => route('ujian.selesai', $sesiPeserta),
            'synced'          => $syncResult['synced'] ?? 0,
            'skipped'         => $syncResult['skipped'] ?? 0,
            'soal_terjawab'   => $sesiPeserta->soal_terjawab,
        ];
    }

    /**
     * Parse jawaban to determine type and structure.
     */
    private function parseJawaban(mixed $jawaban): array
    {
        $isTerjawab = !empty($jawaban);

        if (is_array($jawaban)) {
            $isPasangan = isset($jawaban[0]) && is_array($jawaban[0]);
            $isBenarSalah = !$isPasangan && !array_is_list($jawaban);

            if ($isBenarSalah) {
                return [
                    'jawaban_pg'       => $jawaban,
                    'jawaban_pasangan' => null,
                    'jawaban_teks'     => null,
                    'is_terjawab'      => $isTerjawab,
                ];
            }

            return [
                'jawaban_pg'       => $isPasangan ? null : $jawaban,
                'jawaban_pasangan' => $isPasangan ? $jawaban : null,
                'jawaban_teks'     => null,
                'is_terjawab'      => $isTerjawab,
            ];
        }

        return [
            'jawaban_pg'       => null,
            'jawaban_pasangan' => null,
            'jawaban_teks'     => (string) $jawaban,
            'is_terjawab'      => $isTerjawab && trim((string) $jawaban) !== '',
        ];
    }

    /**
     * Validate soal IDs and return invalid ones.
     */
    public function validateSoalIds(array $soalIds): array
    {
        $validIds = $this->soalRepository->getValidIds($soalIds);
        return array_diff($soalIds, array_keys($validIds));
    }

    /**
     * Find sesi peserta by token (any status).
     */
    public function findSesiPesertaByToken(string $token): ?SesiPeserta
    {
        return $this->repository->findSesiPesertaByTokenAny($token);
    }

    /**
     * Find active sesi peserta by token (mengerjakan or login status only).
     */
    public function findActiveSesiPesertaByToken(string $token): ?SesiPeserta
    {
        return $this->repository->findSesiPesertaByToken($token);
    }
}
