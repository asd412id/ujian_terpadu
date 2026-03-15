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

        // Combine simple model counts into fewer queries
        $counts = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM sekolah WHERE is_active = 1) as total_sekolah,
                (SELECT COUNT(*) FROM peserta WHERE deleted_at IS NULL) as total_peserta,
                (SELECT COUNT(*) FROM paket_ujian WHERE deleted_at IS NULL) as total_paket,
                (SELECT COUNT(*) FROM paket_ujian WHERE status = 'aktif' AND deleted_at IS NULL) as paket_aktif,
                (SELECT COUNT(*) FROM sesi_ujian WHERE status = 'berlangsung') as sesi_berlangsung,
                (SELECT COUNT(*) FROM sesi_peserta WHERE status IN ('login','mengerjakan')) as peserta_online,
                (SELECT COUNT(*) FROM soal WHERE deleted_at IS NULL) as total_soal,
                (SELECT COUNT(*) FROM kategori_soal WHERE is_active = 1) as total_kategori
        ");

        $essayBelumDinilai = JawabanPeserta::where('is_terjawab', true)
            ->whereNull('skor_manual')
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->count();

        return [
            'total_sekolah'       => (int) $counts->total_sekolah,
            'sekolah_aktif'       => $sekolahAktifCount,
            'total_peserta'       => (int) $counts->total_peserta,
            'total_paket'         => (int) $counts->total_paket,
            'paket_aktif'         => (int) $counts->paket_aktif,
            'sesi_berlangsung'    => (int) $counts->sesi_berlangsung,
            'peserta_online'      => (int) $counts->peserta_online,
            'essay_belum_dinilai' => $essayBelumDinilai,
            'total_soal'          => (int) $counts->total_soal,
            'total_kategori'      => (int) $counts->total_kategori,
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
