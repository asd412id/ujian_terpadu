<?php

namespace App\Services;

use App\Repositories\PesertaRepository;
use App\Repositories\SesiUjianRepository;

class KartuLoginService
{
    public function __construct(
        protected PesertaRepository $repository,
        protected SesiUjianRepository $sesiUjianRepository
    ) {}

    /**
     * Generate kartu login data with filters.
     */
    public function generateKartuLogin(string $sekolahId, array $filters = []): array
    {
        $peserta = $this->repository->getBySekolahFiltered($sekolahId, $filters);
        $kelasList = $this->repository->getKelasBySekolah($sekolahId);

        return compact('peserta', 'kelasList');
    }

    /**
     * Get kartu login data for a specific sekolah (for batch print).
     */
    public function getKartuBySekolah(string $sekolahId): mixed
    {
        return $this->repository->getActiveBySekolah($sekolahId)
            ->map(function ($peserta) {
                $peserta->password_kartu = $this->decryptPassword($peserta->password_plain);
                return $peserta;
            });
    }

    /**
     * Get print data for specific peserta IDs or a single peserta.
     */
    public function printKartu(array $pesertaIds): mixed
    {
        return $this->repository->getByIds($pesertaIds)
            ->map(function ($peserta) {
                $peserta->password_kartu = $this->decryptPassword($peserta->password_plain);
                return $peserta;
            });
    }

    /**
     * Get single peserta kartu data.
     */
    public function getKartuPeserta(string $pesertaId): array
    {
        $peserta = $this->repository->findOrFail($pesertaId);
        $passwordKartu = $this->decryptPassword($peserta->password_plain);

        return compact('peserta', 'passwordKartu');
    }

    /**
     * Get kartu data for a sesi ujian (preview/print per sesi).
     */
    public function getKartuBySesi(string $sesiId): array
    {
        $sesi = $this->sesiUjianRepository->findSesiWithPeserta($sesiId);

        $pesertaList = $sesi->sesiPeserta->map(function ($sp) {
            $peserta = $sp->peserta;
            $peserta->password_kartu = $this->decryptPassword($peserta->password_plain);
            return $peserta;
        });

        return [
            'sesi'        => $sesi,
            'paket'       => $sesi->paket,
            'sekolah'     => $sesi->paket->sekolah,
            'pesertaList' => $pesertaList,
        ];
    }

    /**
     * Safely decrypt password_plain with fallback on failure.
     */
    protected function decryptPassword(?string $encrypted): string
    {
        if (empty($encrypted)) {
            return '(hubungi admin)';
        }

        try {
            return decrypt($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            report($e);
            return '(error - hubungi admin)';
        }
    }
}