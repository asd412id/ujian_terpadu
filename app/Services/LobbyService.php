<?php

namespace App\Services;

use App\Models\SesiPeserta;
use App\Repositories\SesiUjianRepository;
use App\Repositories\PaketUjianRepository;

class LobbyService
{
    public function __construct(
        protected SesiUjianRepository $sesiUjianRepository,
        protected PaketUjianRepository $paketUjianRepository
    ) {}

    /**
     * Get available ujian (active sessions) for a peserta.
     * Only shows sesi that are 'berlangsung' and within schedule window.
     */
    public function getAvailableUjian(string $pesertaId): mixed
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['terdaftar', 'belum_login', 'login', 'mengerjakan'])
            ->whereHas('sesi', function ($q) {
                $q->where('status', 'berlangsung');
            })
            ->get()
            ->sortBy(fn ($sp) => $sp->sesi->waktu_mulai)
            ->values()
            ->map(function ($sp) {
                // Add schedule info for display
                $sesi = $sp->sesi;
                $now = now();
                $sp->schedule_status = 'open'; // default: can start

                if ($sesi->waktu_mulai && $now->lt($sesi->waktu_mulai)) {
                    $sp->schedule_status = 'belum_mulai';
                } elseif ($sesi->waktu_selesai && $now->gt($sesi->waktu_selesai)) {
                    $sp->schedule_status = 'sudah_selesai';
                }

                return $sp;
            });
    }

    /**
     * Get completed ujian history for a peserta.
     */
    public function getUjianHistory(string $pesertaId): mixed
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->where('status', 'submit')
            ->latest('submit_at')
            ->get();
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
