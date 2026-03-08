<?php

namespace App\Services;

use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use App\Models\SesiPeserta;
use App\Repositories\SesiUjianRepository;
use App\Repositories\JawabanRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UjianService
{
    public function __construct(
        protected SesiUjianRepository $sesiUjianRepository,
        protected JawabanRepository $jawabanRepository,
        protected PenilaianService $penilaianService
    ) {}

    /**
     * Start ujian for a peserta — set status, record metadata, prepare soal.
     *
     * @throws ValidationException
     */
    public function startUjian(string $sesiPesertaId, string $pesertaId, array $requestMeta = []): array
    {
        $sesiPeserta = SesiPeserta::with(['sesi.paket'])->findOrFail($sesiPesertaId);

        // Verify ownership
        if ($sesiPeserta->peserta_id !== $pesertaId) {
            abort(403, 'Anda tidak memiliki akses ke sesi ujian ini.');
        }

        // Already submitted
        if ($sesiPeserta->status === 'submit') {
            return [
                'sesiPeserta' => $sesiPeserta,
                'already_submitted' => true,
            ];
        }

        // Set status mengerjakan + catat waktu mulai
        if (in_array($sesiPeserta->status, ['belum_login', 'login'])) {
            $sesiPeserta->update([
                'status'       => 'mengerjakan',
                'mulai_at'     => $sesiPeserta->mulai_at ?? now(),
                'ip_address'   => $requestMeta['ip_address'] ?? null,
                'browser_info' => $requestMeta['user_agent'] ?? null,
                'device_type'  => $this->detectDevice($requestMeta['user_agent'] ?? ''),
                'token_ujian'  => Str::random(64),
            ]);

            LogAktivitasUjian::create([
                'sesi_peserta_id' => $sesiPeserta->id,
                'tipe_event'      => 'mulai_ujian',
                'ip_address'      => $requestMeta['ip_address'] ?? null,
                'created_at'      => now(),
            ]);
        }

        $paket = $sesiPeserta->sesi->paket;

        // Cache soal for performance
        $cacheKey = "paket_soal_{$paket->id}_peserta_{$pesertaId}";
        $soalList = Cache::remember($cacheKey, 3600 * 8, function () use ($paket, $sesiPeserta) {
            return $this->getSoalForPeserta($paket, $sesiPeserta);
        });

        // Get existing answers
        $jawabanExisting = JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
            ->get()
            ->keyBy('soal_id');

        $sisaWaktu = $sesiPeserta->sisa_waktu_detik;

        return [
            'sesiPeserta'     => $sesiPeserta->fresh(),
            'paket'           => $paket,
            'soalList'        => $soalList,
            'jawabanExisting' => $jawabanExisting,
            'sisaWaktu'       => $sisaWaktu,
            'already_submitted' => false,
        ];
    }

    /**
     * Get soal for an active ujian session.
     */
    public function getSoalUjian(string $sesiPesertaId): array
    {
        $sesiPeserta = SesiPeserta::with(['sesi.paket'])->findOrFail($sesiPesertaId);
        $paket = $sesiPeserta->sesi->paket;

        $cacheKey = "paket_soal_{$paket->id}_peserta_{$sesiPeserta->peserta_id}";
        $soalList = Cache::remember($cacheKey, 3600 * 8, function () use ($paket, $sesiPeserta) {
            return $this->getSoalForPeserta($paket, $sesiPeserta);
        });

        return $soalList;
    }

    /**
     * Get the current status of an ujian session.
     */
    public function getStatusUjian(string $sesiPesertaId): array
    {
        $sesiPeserta = SesiPeserta::with(['sesi.paket'])->findOrFail($sesiPesertaId);

        return [
            'status'            => $sesiPeserta->status,
            'mulai_at'          => $sesiPeserta->mulai_at,
            'sisa_waktu_detik'  => $sesiPeserta->sisa_waktu_detik,
            'soal_terjawab'     => $sesiPeserta->soal_terjawab,
            'is_active'         => in_array($sesiPeserta->status, ['login', 'mengerjakan']),
        ];
    }

    /**
     * Submit/selesaikan ujian.
     */
    public function selesaikanUjian(string $sesiPesertaId, string $pesertaId): array
    {
        $sesiPeserta = SesiPeserta::with(['sesi.paket'])->findOrFail($sesiPesertaId);

        // Verify ownership
        if ($sesiPeserta->peserta_id !== $pesertaId) {
            abort(403, 'Anda tidak memiliki akses ke sesi ujian ini.');
        }

        // Already submitted
        if ($sesiPeserta->status === 'submit') {
            return $this->getHasilUjian($sesiPesertaId);
        }

        $durasi = $sesiPeserta->mulai_at
            ? now()->diffInSeconds($sesiPeserta->mulai_at)
            : 0;

        $sesiPeserta->update([
            'status'              => 'submit',
            'submit_at'           => now(),
            'durasi_aktual_detik' => $durasi,
        ]);

        // Calculate score automatically
        $hasil = $this->penilaianService->hitungNilai($sesiPeserta);
        $sesiPeserta->update($hasil);

        LogAktivitasUjian::create([
            'sesi_peserta_id' => $sesiPeserta->id,
            'tipe_event'      => 'submit_ujian',
            'detail'          => ['durasi' => $durasi],
            'created_at'      => now(),
        ]);

        // Clear cached soal
        $paketId = $sesiPeserta->sesi->paket_id;
        Cache::forget("paket_soal_{$paketId}_peserta_{$pesertaId}");

        return $this->getHasilUjian($sesiPesertaId);
    }

    /**
     * Get hasil/ringkasan ujian after submission.
     */
    public function getHasilUjian(string $sesiPesertaId): array
    {
        $sesiPeserta = SesiPeserta::with(['sesi.paket', 'jawaban.soal'])->findOrFail($sesiPesertaId);

        $totalSoal = $sesiPeserta->sesi->paket->soal()->count();
        $terjawab  = $sesiPeserta->jawaban()->where('is_terjawab', true)->count();
        $kosong    = max(0, $totalSoal - $terjawab);
        $ragu      = (int) $sesiPeserta->soal_ditandai;

        $mulai   = $sesiPeserta->mulai_at;
        $selesai = $sesiPeserta->submit_at ?? now();
        $durasi  = $mulai ? (int) $mulai->diffInMinutes($selesai) . ' menit' : '-';

        $ringkasan = compact('terjawab', 'kosong', 'ragu', 'durasi');

        return [
            'sesiPeserta' => $sesiPeserta,
            'ringkasan'   => $ringkasan,
        ];
    }

    /**
     * Prepare soal list for a peserta (handles shuffling and order persistence).
     */
    private function getSoalForPeserta($paket, SesiPeserta $sesiPeserta): array
    {
        $soalQuery = $paket->soal()
            ->with(['opsiJawaban', 'pasangan', 'kategori'])
            ->get();

        // Use saved order for consistency (offline support)
        if ($sesiPeserta->urutan_soal) {
            $urutan = $sesiPeserta->urutan_soal;
            $soalMap = $soalQuery->keyBy('id');
            $soalList = collect($urutan)->map(fn ($id) => $soalMap[$id] ?? null)->filter()->values();
        } else {
            $soalList = $paket->acak_soal ? $soalQuery->shuffle() : $soalQuery;

            // Shuffle options per soal if setting is active
            if ($paket->acak_opsi) {
                $soalList = $soalList->map(function ($soal) {
                    $soal->setRelation('opsiJawaban', $soal->opsiJawaban->shuffle()->values());
                    return $soal;
                });
            }

            // Persist order for offline consistency
            $sesiPeserta->update(['urutan_soal' => $soalList->pluck('id')->toArray()]);
        }

        return $soalList->toArray();
    }

    /**
     * Detect device type from user agent string.
     */
    private function detectDevice(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) return 'mobile';
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        return 'desktop';
    }
}
