<?php

namespace App\Services;

use App\Jobs\LogAktivitasUjianJob;
use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use App\Models\SesiPeserta;
use App\Repositories\JawabanRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JawabanService
{
    public function __construct(
        protected JawabanRepository $repository
    ) {}

    /**
     * Simpan jawaban (single answer save).
     */
    public function simpanJawaban(string $sesiPesertaId, string $soalId, mixed $jawaban, ?string $idempotencyKey = null): mixed
    {
        $sesiPeserta = SesiPeserta::whereIn('status', ['mengerjakan', 'login'])
            ->findOrFail($sesiPesertaId);

        // Check remaining time
        if ($sesiPeserta->sisa_waktu_detik <= 0) {
            throw ValidationException::withMessages([
                'waktu' => 'Waktu ujian telah habis.',
            ]);
        }

        $jawabanData = $this->parseJawaban($jawaban);

        $result = JawabanPeserta::updateOrCreate(
            ['sesi_peserta_id' => $sesiPesertaId, 'soal_id' => $soalId],
            array_merge($jawabanData, [
                'idempotency_key' => $idempotencyKey,
                'waktu_jawab'     => now(),
            ])
        );

        // Update answered count
        $terjawab = JawabanPeserta::where('sesi_peserta_id', $sesiPesertaId)
            ->where('is_terjawab', true)
            ->count();
        $sesiPeserta->update(['soal_terjawab' => $terjawab]);

        return $result;
    }

    /**
     * Sync offline answers — batch save from IndexedDB.
     *
     * @param  string  $sesiToken  The 64-char session token
     * @param  array   $answers    Array of answer objects
     * @param  array   $requestMeta  Additional request metadata (ip_address, etc.)
     * @return array   Sync summary
     *
     * @throws ValidationException
     */
    public function syncOfflineAnswers(string $sesiToken, array $answers, array $requestMeta = [], bool $isFinalSubmit = false): array
    {
        // Eager-load sesi.paket to avoid N+1 on sisa_waktu_detik
        $sesiPeserta = SesiPeserta::with('sesi.paket')
            ->where('token_ujian', $sesiToken)
            ->whereIn('status', ['mengerjakan', 'login', 'submit'])
            ->firstOrFail();

        // Validate time — allow sync during final submit even if time expired
        if (!$isFinalSubmit && $sesiPeserta->sisa_waktu_detik <= 0) {
            throw ValidationException::withMessages([
                'waktu' => 'Waktu ujian telah habis.',
            ]);
        }

        $synced  = 0;
        $skipped = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            // --- Bulk idempotency check (1 query instead of N) ---
            $incomingKeys = array_filter(array_column($answers, 'idempotency_key'));
            $existingKeys = [];
            if (!empty($incomingKeys)) {
                $existingKeys = JawabanPeserta::whereIn('idempotency_key', $incomingKeys)
                    ->pluck('idempotency_key')
                    ->flip()
                    ->all();
            }

            // --- Filter & prepare bulk upsert data ---
            $upsertRows = [];
            $now = now();
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

            // --- Single bulk UPSERT (1 query instead of N×updateOrCreate) ---
            if (!empty($upsertRows)) {
                DB::table('jawaban_peserta')->upsert(
                    // Rows that don't have an id yet need one for UUID primary key
                    collect($upsertRows)->map(fn ($row) => array_merge($row, [
                        'id'         => $row['id'] ?? Str::uuid()->toString(),
                        'created_at' => $row['created_at'] ?? $now,
                    ]))->all(),
                    // Unique key for conflict detection
                    ['sesi_peserta_id', 'soal_id'],
                    // Columns to update on conflict
                    ['jawaban_pg', 'jawaban_teks', 'jawaban_pasangan', 'is_terjawab', 'idempotency_key', 'waktu_jawab', 'updated_at']
                );
            }

            // --- Update answered count (1 query) ---
            $terjawab = JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                ->where('is_terjawab', true)
                ->count();
            $updateData = ['soal_terjawab' => $terjawab];
            if (isset($requestMeta['soal_ditandai'])) {
                $updateData['soal_ditandai'] = $requestMeta['soal_ditandai'];
            }
            $sesiPeserta->update($updateData);

            // --- Optimized tandai_list (skip if empty, single query if needed) ---
            if (!empty($requestMeta['tandai_list']) && is_array($requestMeta['tandai_list'])) {
                $tandaiIds = $requestMeta['tandai_list'];
                // Single pass: reset non-tandai to false, set tandai to true
                JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                    ->whereNotIn('soal_id', $tandaiIds)
                    ->where('is_ditandai', true)
                    ->update(['is_ditandai' => false]);
                JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                    ->whereIn('soal_id', $tandaiIds)
                    ->where('is_ditandai', false)
                    ->update(['is_ditandai' => true]);
            } elseif (isset($requestMeta['tandai_list']) && empty($requestMeta['tandai_list'])) {
                // Explicitly empty list — reset all only if any are marked
                JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                    ->where('is_ditandai', true)
                    ->update(['is_ditandai' => false]);
            }

            DB::commit();

            // --- Async log via queue (removed from hot path) ---
            LogAktivitasUjianJob::dispatch(
                $sesiPeserta->id,
                'sync_offline',
                ['synced' => $synced, 'skipped' => $skipped],
                $requestMeta['ip_address'] ?? null,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
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
     * Update a specific jawaban.
     */
    public function updateJawaban(string $jawabanId, array $data): mixed
    {
        $jawaban = JawabanPeserta::findOrFail($jawabanId);
        $jawaban->update($data);
        return $jawaban->fresh();
    }

    /**
     * Get ujian status by token (server-authoritative).
     */
    public function getStatusByToken(string $token): array
    {
        $sesiPeserta = SesiPeserta::with('sesi.paket')
            ->where('token_ujian', $token)->firstOrFail();

        return [
            'status'            => $sesiPeserta->status,
            'elapsed_seconds'   => $sesiPeserta->mulai_at
                ? now()->diffInSeconds($sesiPeserta->mulai_at) : 0,
            'remaining_seconds' => $sesiPeserta->sisa_waktu_detik,
            'soal_terjawab'     => $sesiPeserta->soal_terjawab,
            'server_timestamp'  => now()->timestamp,
            'is_active'         => in_array($sesiPeserta->status, ['login', 'mengerjakan']),
        ];
    }

    /**
     * Submit ujian via API token.
     */
    public function submitByToken(string $token, array $finalAnswers = []): array
    {
        $sesiPeserta = SesiPeserta::with('sesi.paket')
            ->where('token_ujian', $token)
            ->whereIn('status', ['login', 'mengerjakan'])
            ->firstOrFail();

        if ($sesiPeserta->status === 'submit') {
            return [
                'message'     => 'Sudah disubmit',
                'nilai_akhir' => $sesiPeserta->nilai_akhir,
            ];
        }

        // Final sync if answers included — use isFinalSubmit=true to bypass time check
        if (!empty($finalAnswers)) {
            try {
                $this->syncOfflineAnswers($token, $finalAnswers, [], true);
            } catch (\Exception $e) {
                // Log but don't block submit
                LogAktivitasUjian::create([
                    'sesi_peserta_id' => $sesiPeserta->id,
                    'tipe_event'      => 'final_sync_error',
                    'detail'          => ['error' => $e->getMessage()],
                    'created_at'      => now(),
                ]);
            }
        }

        $durasi = $sesiPeserta->mulai_at
            ? now()->diffInSeconds($sesiPeserta->mulai_at) : 0;

        $sesiPeserta->update([
            'status'              => 'submit',
            'submit_at'           => now(),
            'durasi_aktual_detik' => $durasi,
        ]);

        // Calculate score
        $penilaian = app(PenilaianService::class);
        $hasil = $penilaian->hitungNilai($sesiPeserta);
        $sesiPeserta->update($hasil);

        return [
            'message'     => 'Ujian berhasil disubmit',
            'nilai_akhir' => $sesiPeserta->fresh()->nilai_akhir,
        ];
    }

    /**
     * Parse jawaban to determine type and structure.
     */
    private function parseJawaban(mixed $jawaban): array
    {
        $isTerjawab = !empty($jawaban);

        // Detect answer type
        if (is_array($jawaban)) {
            // PG: ["A"] or PG Kompleks: ["A","C"] or Pasangan: [[1,3],[2,1]]
            // Benar/Salah: {"1":"benar","2":"salah"} — associative array with string keys
            $isPasangan = isset($jawaban[0]) && is_array($jawaban[0]);
            $isBenarSalah = !$isPasangan && !array_is_list($jawaban);

            if ($isBenarSalah) {
                // Benar/Salah format: store as-is in jawaban_pg (JSON object)
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
}
