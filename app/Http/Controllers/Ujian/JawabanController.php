<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Jobs\LogAktivitasUjianJob;
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
            'answers.*.soal_id'  => 'required|string',
            'answers.*.jawaban'  => 'present',
            'answers.*.idempotency_key' => 'required|string|max:128',
            'answers.*.client_timestamp' => 'nullable|integer',
            'soal_ditandai'      => 'nullable|integer|min:0',
            'tandai_list'        => 'nullable|array',
            'tandai_list.*'      => 'string',
            'final_submit'       => 'nullable|boolean',
        ]);

        try {
            $result = $this->jawabanService->syncOfflineAnswers(
                sesiToken: $data['sesi_token'],
                answers: $data['answers'],
                requestMeta: [
                    'ip_address'     => $request->ip(),
                    'soal_ditandai'  => $data['soal_ditandai'] ?? null,
                    'tandai_list'    => $data['tandai_list'] ?? null,
                ],
                isFinalSubmit: (bool) ($data['final_submit'] ?? false),
                preloadedSesiPeserta: $request->attributes->get('sesiPeserta'),
            );

            return response()->json($result, ($result['accepted'] ?? true) ? 200 : 422);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Gagal menyimpan jawaban. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Cek status ujian dan waktu tersisa (server-authoritative)
     */
    public function status(Request $request, string $token): JsonResponse
    {
        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);

        // Verify the requesting client owns this token (prevent IDOR)
        $authenticatedSp = $request->attributes->get('sesiPeserta');
        if ($authenticatedSp && $authenticatedSp->token_ujian !== $token) {
            abort(403, 'Token mismatch.');
        }

        $result = $this->jawabanService->getStatusByToken($token);

        return response()->json($result);
    }

    /**
     * Submit ujian via API (dari offline submit button)
     */
    public function submitApi(Request $request, string $token): JsonResponse
    {
        try {
            abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);

            $data = $request->validate([
                'answers'            => 'nullable|array|max:200',
                'answers.*.soal_id'  => 'required|string',
                'answers.*.jawaban'  => 'present',
            ]);

            $finalAnswers = $data['answers'] ?? [];

            $result = $this->jawabanService->submitByToken($token, $finalAnswers);

            // Add redirect URL for already-submitted or newly-submitted
            if (!isset($result['redirect'])) {
                $sesiPeserta = $this->jawabanService->findSesiPesertaByToken($token);
                if ($sesiPeserta) {
                    $result['redirect'] = route('ujian.selesai', $sesiPeserta);
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Gagal submit ujian. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Log cheating/anti-cheat events dari browser
     */
    public function logCheating(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'  => 'required|string|size:64',
            'event'  => 'required|string|in:ganti_tab,fullscreen_exit,fullscreen_enter,copy_paste,klik_kanan,tidak_fokus,screenshot_attempt,browser_minimize',
            'detail' => 'nullable|array',
        ]);

        $sesiPeserta = $request->attributes->get('sesiPeserta');

        if (!$sesiPeserta || !in_array($sesiPeserta->status, ['login', 'mengerjakan'])) {
            return response()->json(['ok' => false, 'error' => 'Sesi tidak ditemukan'], 404);
        }

        // Skip logging if anti_curang is disabled on the paket
        $paket = $sesiPeserta->sesi?->paket;
        if ($paket && !$paket->anti_curang) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        LogAktivitasUjianJob::dispatch(
            $sesiPeserta->id,
            $data['event'],
            $data['detail'] ?? [],
            $request->ip(),
        );

        return response()->json(['ok' => true]);
    }
}
