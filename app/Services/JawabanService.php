<?php

namespace App\Services;

use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use App\Models\SesiPeserta;
use App\Repositories\JawabanRepository;
use Illuminate\Support\Facades\DB;
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
    public function syncOfflineAnswers(string $sesiToken, array $answers, array $requestMeta = []): array
    {
        $sesiPeserta = SesiPeserta::where('token_ujian', $sesiToken)
            ->whereIn('status', ['mengerjakan', 'login'])
            ->firstOrFail();

        // Validate time — prevent submit after exam ends
        if ($sesiPeserta->sisa_waktu_detik <= 0) {
            throw ValidationException::withMessages([
                'waktu' => 'Waktu ujian telah habis.',
            ]);
        }

        $synced  = 0;
        $skipped = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($answers as $ans) {
                // Idempotency check — skip if already received
                $idempotencyKey = $ans['idempotency_key'] ?? null;
                if ($idempotencyKey) {
                    $existing = JawabanPeserta::where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                }

                $jawabanData = $this->parseJawaban($ans['jawaban'] ?? null);

                JawabanPeserta::updateOrCreate(
                    ['sesi_peserta_id' => $sesiPeserta->id, 'soal_id' => $ans['soal_id']],
                    array_merge($jawabanData, [
                        'idempotency_key' => $idempotencyKey,
                        'waktu_jawab'     => now(),
                    ])
                );

                $synced++;
            }

            // Update answered count
            $terjawab = JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                ->where('is_terjawab', true)
                ->count();
            $sesiPeserta->update(['soal_terjawab' => $terjawab]);

            DB::commit();

            LogAktivitasUjian::create([
                'sesi_peserta_id' => $sesiPeserta->id,
                'tipe_event'      => 'sync_offline',
                'detail'          => ['synced' => $synced, 'skipped' => $skipped],
                'ip_address'      => $requestMeta['ip_address'] ?? null,
                'created_at'      => now(),
            ]);
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
        $sesiPeserta = SesiPeserta::where('token_ujian', $token)->firstOrFail();

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
        $sesiPeserta = SesiPeserta::where('token_ujian', $token)
            ->whereIn('status', ['login', 'mengerjakan'])
            ->firstOrFail();

        if ($sesiPeserta->status === 'submit') {
            return [
                'message'     => 'Sudah disubmit',
                'nilai_akhir' => $sesiPeserta->nilai_akhir,
            ];
        }

        // Final sync if answers included
        if (!empty($finalAnswers)) {
            $this->syncOfflineAnswers($token, $finalAnswers);
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
            $isPasangan = isset($jawaban[0]) && is_array($jawaban[0]);
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
