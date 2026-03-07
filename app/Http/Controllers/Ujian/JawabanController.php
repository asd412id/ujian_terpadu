<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Models\SesiPeserta;
use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JawabanController extends Controller
{
    /**
     * Sync offline answers — menerima batch jawaban dari IndexedDB
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sesi_token'         => 'required|string|size:64',
            'answers'            => 'required|array|max:200',
            'answers.*.soal_id'  => 'required|string|exists:soal,id',
            'answers.*.jawaban'  => 'required',
            'answers.*.idempotency_key' => 'required|string|max:64',
            'answers.*.client_timestamp' => 'nullable|integer',
        ]);

        $sesiPeserta = SesiPeserta::where('token_ujian', $data['sesi_token'])
            ->whereIn('status', ['mengerjakan', 'login'])
            ->firstOrFail();

        // Validasi waktu — cegah submit setelah ujian habis
        if ($sesiPeserta->sisa_waktu_detik <= 0) {
            return response()->json(['error' => 'Waktu ujian telah habis'], 422);
        }

        $synced  = 0;
        $skipped = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($data['answers'] as $ans) {
                // Idempotency check — skip jika sudah pernah diterima
                $existing = JawabanPeserta::where('idempotency_key', $ans['idempotency_key'])->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                $jawabanData = $this->parseJawaban($ans);

                JawabanPeserta::updateOrCreate(
                    ['sesi_peserta_id' => $sesiPeserta->id, 'soal_id' => $ans['soal_id']],
                    array_merge($jawabanData, [
                        'idempotency_key' => $ans['idempotency_key'],
                        'waktu_jawab'     => now(),
                    ])
                );

                $synced++;
            }

            // Update jumlah soal terjawab
            $terjawab = JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
                ->where('is_terjawab', true)
                ->count();
            $sesiPeserta->update(['soal_terjawab' => $terjawab]);

            DB::commit();

            LogAktivitasUjian::create([
                'sesi_peserta_id' => $sesiPeserta->id,
                'tipe_event'      => 'sync_offline',
                'detail'          => ['synced' => $synced, 'skipped' => $skipped],
                'ip_address'      => $request->ip(),
                'created_at'      => now(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal menyimpan jawaban: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'synced'  => $synced,
            'skipped' => $skipped,
            'errors'  => $errors,
            'server_time' => now()->timestamp,
        ]);
    }

    /**
     * Cek status ujian dan waktu tersisa (server-authoritative)
     */
    public function status(string $token): JsonResponse
    {
        $sesiPeserta = SesiPeserta::where('token_ujian', $token)->firstOrFail();

        return response()->json([
            'status'            => $sesiPeserta->status,
            'elapsed_seconds'   => $sesiPeserta->mulai_at
                ? now()->diffInSeconds($sesiPeserta->mulai_at) : 0,
            'remaining_seconds' => $sesiPeserta->sisa_waktu_detik,
            'soal_terjawab'     => $sesiPeserta->soal_terjawab,
            'server_timestamp'  => now()->timestamp,
            'is_active'         => in_array($sesiPeserta->status, ['login', 'mengerjakan']),
        ]);
    }

    /**
     * Submit ujian via API (dari offline submit button)
     */
    public function submitApi(Request $request, string $token): JsonResponse
    {
        $sesiPeserta = SesiPeserta::where('token_ujian', $token)
            ->whereIn('status', ['login', 'mengerjakan'])
            ->firstOrFail();

        if ($sesiPeserta->status === 'submit') {
            return response()->json(['message' => 'Sudah disubmit'], 200);
        }

        // Final sync jika ada jawaban terlampir
        if ($request->has('answers') && count($request->answers) > 0) {
            $this->syncOffline($request);
        }

        $durasi = $sesiPeserta->mulai_at
            ? now()->diffInSeconds($sesiPeserta->mulai_at) : 0;

        $sesiPeserta->update([
            'status'              => 'submit',
            'submit_at'           => now(),
            'durasi_aktual_detik' => $durasi,
        ]);

        // Hitung nilai
        $penilaian = app(\App\Services\PenilaianService::class);
        $hasil = $penilaian->hitungNilai($sesiPeserta);
        $sesiPeserta->update($hasil);

        return response()->json([
            'message'     => 'Ujian berhasil disubmit',
            'nilai_akhir' => $sesiPeserta->fresh()->nilai_akhir,
            'redirect'    => route('ujian.selesai', $sesiPeserta),
        ]);
    }

    private function parseJawaban(array $ans): array
    {
        $jawaban    = $ans['jawaban'];
        $isTerjawab = ! empty($jawaban);

        // Deteksi tipe jawaban
        if (is_array($jawaban)) {
            // PG: ["A"] atau PG Kompleks: ["A","C"] atau Pasangan: [[1,3],[2,1]]
            $isPasangan = isset($jawaban[0]) && is_array($jawaban[0]);
            return [
                'jawaban_pg'       => $isPasangan ? null : $jawaban,
                'jawaban_pasangan' => $isPasangan ? $jawaban : null,
                'is_terjawab'      => $isTerjawab,
            ];
        }

        return [
            'jawaban_teks' => (string) $jawaban,
            'is_terjawab'  => $isTerjawab && trim((string) $jawaban) !== '',
        ];
    }
}
