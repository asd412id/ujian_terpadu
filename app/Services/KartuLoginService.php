<?php

namespace App\Services;

use App\Repositories\PesertaRepository;
use App\Repositories\SesiUjianRepository;
use App\Support\SearchHelper;

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
     * Generate kartu login data for dinas (cross-school, with sekolah + kelas filter).
     */
    public function generateKartuLoginDinas(array $filters = []): array
    {
        $query = \App\Models\Peserta::with('sekolah')
            ->when(!empty($filters['sekolah_id']), fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']))
            ->when(!empty($filters['kelas']), fn ($q) => $q->where('kelas', $filters['kelas']))
            ->when(!empty($filters['q']), function ($q) use ($filters) {
                $search = $filters['q'];
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', SearchHelper::containsLike($search))
                      ->orWhere('nis', 'like', SearchHelper::containsLike($search))
                      ->orWhere('nisn', 'like', SearchHelper::containsLike($search));
                });
            })
            ->orderBy('sekolah_id')
            ->orderBy('kelas')
            ->orderBy('nama');

        $peserta = $query->paginate($filters['per_page'] ?? 25)->withQueryString();

        $sekolahList = \App\Models\Sekolah::orderBy('nama')->get();

        return compact('peserta', 'sekolahList');
    }

    /**
     * Get all active peserta for batch print (dinas, cross-school).
     */
    public function getKartuAllDinas(?string $sekolahId = null): \Illuminate\Support\Collection
    {
        return \App\Models\Peserta::with('sekolah')
            ->where('is_active', true)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->orderBy('sekolah_id')
            ->orderBy('kelas')
            ->orderBy('nama')
            ->get()
            ->map(function ($peserta) {
                $peserta->password_kartu = $this->decryptPassword($peserta->password_plain);
                return $peserta;
            });
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