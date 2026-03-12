<?php

namespace App\Repositories;

use App\Models\JawabanPeserta;
use App\Models\KategoriSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    /**
     * Get dinas dashboard statistics (all aggregate counts).
     */
    public function getDinasStats(): array
    {
        $sekolahAktifCount = SesiUjian::where('sesi_ujian.status', 'berlangsung')
            ->join('paket_ujian', 'sesi_ujian.paket_id', '=', 'paket_ujian.id')
            ->whereNotNull('paket_ujian.sekolah_id')
            ->distinct('paket_ujian.sekolah_id')
            ->count('paket_ujian.sekolah_id');

        return [
            'total_sekolah'       => Sekolah::where('is_active', true)->count(),
            'sekolah_aktif'       => $sekolahAktifCount,
            'total_peserta'       => Peserta::count(),
            'total_paket'         => PaketUjian::count(),
            'paket_aktif'         => PaketUjian::where('status', 'aktif')->count(),
            'sesi_berlangsung'    => SesiUjian::where('status', 'berlangsung')->count(),
            'peserta_online'      => SesiPeserta::whereIn('status', ['login', 'mengerjakan'])->count(),
            'essay_belum_dinilai' => JawabanPeserta::where('is_terjawab', true)
                ->whereNull('skor_manual')
                ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
                ->count(),
            'total_soal'          => Soal::count(),
            'total_kategori'      => KategoriSoal::where('is_active', true)->count(),
        ];
    }

    /**
     * Get active sesi list for dinas dashboard (berlangsung, recent 10).
     */
    public function getActiveSesiList(int $limit = 10): Collection
    {
        return SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung')
            ->latest('waktu_mulai')
            ->take($limit)
            ->get();
    }

    /**
     * Find sekolah by ID.
     */
    public function findSekolah(string $sekolahId): ?Sekolah
    {
        return Sekolah::find($sekolahId);
    }

    /**
     * Get eligible paket IDs for a sekolah.
     */
    public function getEligiblePaketIds(string $sekolahId, ?string $jenjang): \Illuminate\Support\Collection
    {
        return PaketUjian::where('status', 'aktif')
            ->where(function ($q) use ($sekolahId, $jenjang) {
                $q->where('sekolah_id', $sekolahId)
                  ->orWhere(function ($q2) use ($jenjang) {
                      $q2->whereNull('sekolah_id');
                      if ($jenjang) {
                          $q2->where(fn ($q3) => $q3->where('jenjang', $jenjang)->orWhere('jenjang', 'SEMUA'));
                      }
                  });
            })
            ->pluck('id');
    }

    /**
     * Get sekolah dashboard stats.
     */
    public function getSekolahStats(string $sekolahId, \Illuminate\Support\Collection $paketIds): array
    {
        return [
            'total_peserta' => Peserta::where('sekolah_id', $sekolahId)->count(),
            'total_paket'   => $paketIds->count(),
            'sesi_aktif'    => SesiUjian::whereIn('paket_id', $paketIds)->where('status', 'berlangsung')->count(),
        ];
    }

    /**
     * Get upcoming sesi for sekolah.
     */
    public function getUpcomingSesi(\Illuminate\Support\Collection $paketIds, int $limit = 5): Collection
    {
        return SesiUjian::whereIn('paket_id', $paketIds)
            ->whereIn('status', ['persiapan', 'menunggu', 'berlangsung'])
            ->with('paket')
            ->orderBy('waktu_mulai')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pengawas sesi list with withCount.
     */
    public function getPengawasSesiList(string $pengawasId): Collection
    {
        return SesiUjian::with(['paket'])
            ->withCount([
                'sesiPeserta as peserta_mengerjakan' => fn ($q) => $q->where('status', 'mengerjakan'),
            ])
            ->where('pengawas_id', $pengawasId)
            ->whereIn('status', ['persiapan', 'berlangsung'])
            ->get();
    }
}
