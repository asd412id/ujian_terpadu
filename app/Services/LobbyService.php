<?php

namespace App\Services;

use App\Repositories\SesiUjianRepository;
use App\Repositories\PaketUjianRepository;
use Illuminate\Support\Facades\Cache;

class LobbyService
{
    public function __construct(
        protected SesiUjianRepository $sesiUjianRepository,
        protected PaketUjianRepository $paketUjianRepository
    ) {}

    /**
     * Get available ujian (active sessions) for a peserta.
     * Only shows sesi that are 'berlangsung' and within schedule window.
     * Cached 10s per peserta to reduce DB load during mass login.
     */
    public function getAvailableUjian(string $pesertaId): mixed
    {
        // Cache only the DB query result; schedule_status is computed fresh
        // per request since it depends on the current time.
        $sesiList = Cache::remember("lobby_available:{$pesertaId}", 10, function () use ($pesertaId) {
            return $this->sesiUjianRepository
                ->getAvailableSesiForPeserta($pesertaId)
                ->sortBy(fn ($sp) => $sp->sesi->waktu_mulai)
                ->values();
        });

        $now = now();
        return $sesiList->map(function ($sp) use ($now) {
            $item = clone $sp;
            $sesi = $item->sesi;
            $item->schedule_status = 'open';

            if ($sesi->waktu_mulai && $now->lt($sesi->waktu_mulai)) {
                $item->schedule_status = 'belum_mulai';
            } elseif ($sesi->waktu_selesai && $now->gt($sesi->waktu_selesai)) {
                $item->schedule_status = 'sudah_selesai';
            }

            return $item;
        });
    }

    /**
     * Get completed ujian history for a peserta.
     * Cached 30s per peserta.
     */
    public function getUjianHistory(string $pesertaId): mixed
    {
        return Cache::remember("lobby_history:{$pesertaId}", 30, function () use ($pesertaId) {
            return $this->sesiUjianRepository->getCompletedSesiForPeserta($pesertaId);
        });
    }

    /**
     * Get full lobby data for a peserta (available + history).
     */
    public function getLobbyData(string $pesertaId): array
    {
        return [
            'sesiTersedia' => $this->getAvailableUjian($pesertaId),
            'sesiSelesai'  => $this->getUjianHistory($pesertaId),
        ];
    }
}
