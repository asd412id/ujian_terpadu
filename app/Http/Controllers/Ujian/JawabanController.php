<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Services\JawabanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JawabanController extends Controller
{
    public function __construct(
        protected JawabanService $jawabanService
    ) {}

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
            'answers.*.idempotency_key' => 'required|string|max:128',
            'answers.*.client_timestamp' => 'nullable|integer',
            'soal_ditandai'      => 'nullable|integer|min:0',
        ]);

        try {
            $result = $this->jawabanService->syncOfflineAnswers(
                sesiToken: $data['sesi_token'],
                answers: $data['answers'],
                requestMeta: [
                    'ip_address'     => $request->ip(),
                    'soal_ditandai'  => $data['soal_ditandai'] ?? null,
                ]
            );

            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menyimpan jawaban: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cek status ujian dan waktu tersisa (server-authoritative)
     */
    public function status(string $token): JsonResponse
    {
        $result = $this->jawabanService->getStatusByToken($token);

        return response()->json($result);
    }

    /**
     * Submit ujian via API (dari offline submit button)
     */
    public function submitApi(Request $request, string $token): JsonResponse
    {
        try {
            $finalAnswers = $request->has('answers') && count($request->answers) > 0
                ? $request->answers
                : [];

            $result = $this->jawabanService->submitByToken($token, $finalAnswers);

            // Add redirect URL for already-submitted or newly-submitted
            if (!isset($result['redirect'])) {
                $sesiPeserta = \App\Models\SesiPeserta::where('token_ujian', $token)->first();
                if ($sesiPeserta) {
                    $result['redirect'] = route('ujian.selesai', $sesiPeserta);
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
