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
     */
    public function getAvailableUjian(string $pesertaId): mixed
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['terdaftar', 'belum_login', 'login', 'mengerjakan'])
            ->get();
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
