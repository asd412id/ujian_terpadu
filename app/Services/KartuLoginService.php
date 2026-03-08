<?php

namespace App\Services;

use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Repositories\PesertaRepository;

class KartuLoginService
{
    public function __construct(
        protected PesertaRepository $repository
    ) {}

    /**
     * Generate kartu login data with filters.
     */
    public function generateKartuLogin(string $sekolahId, array $filters = []): array
    {
        $query = Peserta::where('sekolah_id', $sekolahId);

        if (!empty($filters['kelas'])) {
            $query->where('kelas', $filters['kelas']);
        }

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $peserta = $query->orderBy('kelas')
            ->orderBy('nama')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        $kelasList = Peserta::where('sekolah_id', $sekolahId)
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        return compact('peserta', 'kelasList');
    }

    /**
     * Get kartu login data for a specific sekolah (for batch print).
     */
    public function getKartuBySekolah(string $sekolahId): mixed
    {
        $sesiIds = SesiUjian::whereHas('paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->pluck('id');

        return SesiPeserta::with('peserta')
            ->whereIn('sesi_id', $sesiIds)
            ->get()
            ->map(function ($sp) {
                $peserta = $sp->peserta;
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
        return Peserta::whereIn('id', $pesertaIds)
            ->get()
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
        $peserta = Peserta::findOrFail($pesertaId);
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
        $sesi = SesiUjian::with(['paket.sekolah', 'sesiPeserta.peserta'])->findOrFail($sesiId);

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
