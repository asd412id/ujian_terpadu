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
                $peserta->password_kartu = $peserta->password_plain
                    ? decrypt($peserta->password_plain)
                    : '(hubungi admin)';
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
                $peserta->password_kartu = $peserta->password_plain
                    ? decrypt($peserta->password_plain)
                    : '(hubungi admin)';
                return $peserta;
            });
    }

    /**
     * Get single peserta kartu data.
     */
    public function getKartuPeserta(string $pesertaId): array
    {
        $peserta = $this->repository->findOrFail($pesertaId);
        $passwordKartu = $peserta->password_plain
            ? decrypt($peserta->password_plain)
            : '(hubungi admin)';

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
            $peserta->password_kartu = $peserta->password_plain
                ? decrypt($peserta->password_plain)
                : '(hubungi admin)';
            return $peserta;
        });

        return [
            'sesi'        => $sesi,
            'paket'       => $sesi->paket,
            'sekolah'     => $sesi->paket->sekolah,
            'pesertaList' => $pesertaList,
        ];
    }
}
