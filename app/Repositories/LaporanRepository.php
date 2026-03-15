<?php

namespace App\Repositories;

use App\Models\JawabanPeserta;
use App\Models\PaketUjian;
use App\Models\SesiPeserta;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanRepository
{
    /**
     * Get hasil ujian by sekolah (all submitted/graded).
     */
    public function getHasilBySekolah(string $sekolahId): Collection
    {
        return SesiPeserta::with(['peserta', 'sesi.paket'])
            ->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->get();
    }

    /**
     * Get hasil ujian by paket.
     */
    public function getHasilByPaket(string $paketId): Collection
    {
        return SesiPeserta::with(['peserta', 'sesi'])
            ->whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->get();
    }

    /**
     * Get statistik (rekap) for a specific paket ujian (single aggregate query).
     */
    public function getStatistik(string $paketId): array
    {
        $row = SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->selectRaw('
                COUNT(*) as total_peserta,
                ROUND(AVG(nilai_akhir), 1) as rata_rata,
                MAX(nilai_akhir) as nilai_max,
                MIN(nilai_akhir) as nilai_min,
                SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
                SUM(CASE WHEN nilai_akhir < 70 OR nilai_akhir IS NULL THEN 1 ELSE 0 END) as tidak_lulus
            ')->first();

        return [
            'total_peserta' => (int) ($row->total_peserta ?? 0),
            'rata_rata'     => (float) ($row->rata_rata ?? 0),
            'nilai_max'     => (float) ($row->nilai_max ?? 0),
            'nilai_min'     => (float) ($row->nilai_min ?? 0),
            'lulus'         => (int) ($row->lulus ?? 0),
            'tidak_lulus'   => (int) ($row->tidak_lulus ?? 0),
        ];
    }

    /**
     * Get detail nilai for a specific peserta in a sesi.
     */
    public function getDetailNilaiPeserta(string $sesiPesertaId): ?SesiPeserta
    {
        return SesiPeserta::with(['peserta', 'sesi.paket', 'jawaban.soal'])
            ->find($sesiPesertaId);
    }

    /**
     * Get list of all active sekolah for filter dropdowns.
     */
    public function getSekolahList(): Collection
    {
        return Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get list of all paket ujian for filter dropdowns.
     */
    public function getPaketList(): Collection
    {
        return PaketUjian::orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Get paginated hasil ujian with filters (for dinas dashboard).
     */
    public function getHasilUjianFiltered(array $filters = []): LengthAwarePaginator
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where(function ($q) {
                    $q->where('nilai_akhir', '<', 70)->orWhereNull('nilai_akhir');
                });
            }
        }

        $perPage = $filters['per_page'] ?? 30;
        return $query->latest('updated_at')->paginate($perPage);
    }

    /**
     * Build rekap statistics (single aggregate query).
     */
    public function buildRekap(array $filters = []): array
    {
        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where(function ($q) {
                    $q->where('nilai_akhir', '<', 70)->orWhereNull('nilai_akhir');
                });
            }
        }

        $row = $query->selectRaw('
            COUNT(*) as total_peserta,
            SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
            SUM(CASE WHEN nilai_akhir < 70 OR nilai_akhir IS NULL THEN 1 ELSE 0 END) as tidak_lulus,
            ROUND(AVG(nilai_akhir), 1) as rata_rata
        ')->first();

        return [
            'total_peserta' => (int) ($row->total_peserta ?? 0),
            'sudah_ujian'   => (int) ($row->total_peserta ?? 0),
            'lulus'         => (int) ($row->lulus ?? 0),
            'tidak_lulus'   => (int) ($row->tidak_lulus ?? 0),
            'rata_rata'     => (float) ($row->rata_rata ?? 0),
        ];
    }

    /**
     * Get global statistik (no paket filter, single aggregate).
     */
    public function getGlobalStatistik(): array
    {
        $row = SesiPeserta::whereIn('status', ['submit', 'dinilai'])
            ->selectRaw('
                COUNT(*) as total_peserta,
                ROUND(AVG(nilai_akhir), 1) as rata_rata,
                MAX(nilai_akhir) as nilai_max,
                MIN(nilai_akhir) as nilai_min,
                SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
                SUM(CASE WHEN nilai_akhir < 70 OR nilai_akhir IS NULL THEN 1 ELSE 0 END) as tidak_lulus
            ')->first();

        return [
            'total_peserta' => (int) ($row->total_peserta ?? 0),
            'rata_rata'     => (float) ($row->rata_rata ?? 0),
            'nilai_max'     => (float) ($row->nilai_max ?? 0),
            'nilai_min'     => (float) ($row->nilai_min ?? 0),
            'lulus'         => (int) ($row->lulus ?? 0),
            'tidak_lulus'   => (int) ($row->tidak_lulus ?? 0),
        ];
    }

    /**
     * Get all hasil ujian for export (no pagination).
     */
    public function getHasilForExport(array $filters = []): Collection
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where(function ($q) {
                    $q->where('nilai_akhir', '<', 70)->orWhereNull('nilai_akhir');
                });
            }
        }

        return $query->latest('updated_at')->get();
    }

    /**
     * Chunk hasil ujian for memory-efficient export.
     */
    public function chunkHasilForExport(array $filters, int $chunkSize, callable $callback): void
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where(function ($q) {
                    $q->where('nilai_akhir', '<', 70)->orWhereNull('nilai_akhir');
                });
            }
        }

        $query->latest('updated_at')->chunkById($chunkSize, $callback);
    }

    /**
     * Get sesi_peserta IDs for export filters (for perSoal analysis).
     */
    public function getExportSesiPesertaIds(array $filters): \Illuminate\Support\Collection
    {
        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where(function ($q) {
                    $q->where('nilai_akhir', '<', 70)->orWhereNull('nilai_akhir');
                });
            }
        }

        return $query->pluck('id');
    }

    /**
     * Build per-soal analysis using DB aggregates (no N+1, no full load).
     */
    public function buildPerSoalAnalysis(\Illuminate\Support\Collection $sesiPesertaIds): array
    {
        if ($sesiPesertaIds->isEmpty()) {
            return [];
        }

        $rows = JawabanPeserta::join('soal', 'jawaban_peserta.soal_id', '=', 'soal.id')
            ->leftJoin('kategori_soal', 'soal.kategori_id', '=', 'kategori_soal.id')
            ->whereIn('jawaban_peserta.sesi_peserta_id', $sesiPesertaIds)
            ->groupBy('jawaban_peserta.soal_id', 'soal.tipe_soal', 'soal.pertanyaan', 'kategori_soal.nama')
            ->selectRaw('
                jawaban_peserta.soal_id,
                soal.tipe_soal,
                LEFT(soal.pertanyaan, 200) as pertanyaan_raw,
                kategori_soal.nama as kategori_nama,
                COUNT(*) as total_dijawab,
                SUM(CASE WHEN jawaban_peserta.is_terjawab = 0 THEN 1 ELSE 0 END) as kosong,
                SUM(CASE WHEN jawaban_peserta.is_terjawab = 1 AND (COALESCE(jawaban_peserta.skor_auto, 0) > 0 OR COALESCE(jawaban_peserta.skor_manual, 0) > 0) THEN 1 ELSE 0 END) as benar,
                SUM(CASE WHEN jawaban_peserta.is_terjawab = 1 AND COALESCE(jawaban_peserta.skor_auto, 0) = 0 AND COALESCE(jawaban_peserta.skor_manual, 0) = 0 THEN 1 ELSE 0 END) as salah,
                SUM(COALESCE(jawaban_peserta.skor_auto, 0) + COALESCE(jawaban_peserta.skor_manual, 0)) as total_skor
            ')
            ->orderBy('jawaban_peserta.soal_id')
            ->get();

        $result = [];
        $nomor = 1;
        foreach ($rows as $row) {
            $total = (int) $row->total_dijawab ?: 1;
            $benar = (int) $row->benar;
            $result[] = [
                'nomor'         => $nomor++,
                'tipe'          => $row->tipe_soal ?? '-',
                'kategori'      => $row->kategori_nama ?? '-',
                'pertanyaan'    => mb_substr(strip_tags($row->pertanyaan_raw ?? ''), 0, 120),
                'total_dijawab' => (int) $row->total_dijawab,
                'benar'         => $benar,
                'salah'         => (int) $row->salah,
                'kosong'        => (int) $row->kosong,
                'pct_benar'     => round(($benar / $total) * 100, 1),
                'rata_skor'     => round((float) $row->total_skor / $total, 2),
            ];
        }

        return $result;
    }

    /**
     * Find paket with soal and opsi for analisis.
     */
    public function findPaketWithSoal(string $paketId): PaketUjian
    {
        return PaketUjian::with('soal.opsiJawaban')->findOrFail($paketId);
    }

    /**
     * Get submitted sesi_peserta IDs for a paket.
     */
    public function getSubmittedSesiPesertaIds(string $paketId): \Illuminate\Support\Collection
    {
        return SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->pluck('id');
    }

    /**
     * Get per-soal aggregates (benar/salah/kosong) for given sesi_peserta IDs.
     */
    public function getSoalAggregates(\Illuminate\Support\Collection $sesiPesertaIds): Collection
    {
        return JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
            ->groupBy('soal_id')
            ->selectRaw('
                soal_id,
                COUNT(*) as total_menjawab,
                SUM(CASE WHEN is_terjawab = 0 THEN 1 ELSE 0 END) as kosong,
                SUM(CASE WHEN is_terjawab = 1 AND (COALESCE(skor_auto, 0) > 0 OR COALESCE(skor_manual, 0) > 0) THEN 1 ELSE 0 END) as benar,
                SUM(CASE WHEN is_terjawab = 1 AND COALESCE(skor_auto, 0) = 0 AND COALESCE(skor_manual, 0) = 0 THEN 1 ELSE 0 END) as salah
            ')
            ->get()
            ->keyBy('soal_id');
    }

    /**
     * Get top/bottom N sesi_peserta IDs by nilai_akhir.
     */
    public function getTopBottomIds(\Illuminate\Support\Collection $sesiPesertaIds, int $count): array
    {
        $topIds = SesiPeserta::whereIn('id', $sesiPesertaIds)
            ->orderByDesc('nilai_akhir')
            ->limit($count)
            ->pluck('id');
        $bottomIds = SesiPeserta::whereIn('id', $sesiPesertaIds)
            ->orderBy('nilai_akhir')
            ->limit($count)
            ->pluck('id');

        return [$topIds, $bottomIds];
    }

    /**
     * Get per-soal benar counts for given sesi_peserta IDs.
     */
    public function getBenarCountsBySoal(\Illuminate\Support\Collection $sesiPesertaIds): \Illuminate\Support\Collection
    {
        return JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
            ->where('is_terjawab', true)
            ->whereRaw('(COALESCE(skor_auto, 0) > 0 OR COALESCE(skor_manual, 0) > 0)')
            ->groupBy('soal_id')
            ->selectRaw('soal_id, COUNT(*) as benar')
            ->pluck('benar', 'soal_id');
    }

    /**
     * Get distractor counts for PG soal.
     */
    public function getDistractorCounts(\Illuminate\Support\Collection $sesiPesertaIds, \Illuminate\Support\Collection $pgSoalIds): array
    {
        $distractorCounts = [];
        if ($pgSoalIds->isNotEmpty()) {
            // Use DB aggregate instead of loading all rows into memory
            $rows = JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
                ->whereIn('soal_id', $pgSoalIds)
                ->whereNotNull('jawaban_pg')
                ->select('soal_id', 'jawaban_pg')
                ->cursor();

            foreach ($rows as $j) {
                $soalId = $j->soal_id;
                $pg = $j->jawaban_pg;
                if (is_array($pg)) {
                    foreach ($pg as $label) {
                        $distractorCounts[$soalId][$label] = ($distractorCounts[$soalId][$label] ?? 0) + 1;
                    }
                }
            }
        }
        return $distractorCounts;
    }

    /**
     * Find sesi_peserta with full detail for siswa view.
     */
    public function findSesiPesertaWithDetail(string $sesiPesertaId): SesiPeserta
    {
        return SesiPeserta::with(['peserta.sekolah', 'sesi.paket.soal.opsiJawaban', 'jawaban'])
            ->findOrFail($sesiPesertaId);
    }

    /**
     * Find paket name by ID.
     */
    public function findPaketName(string $paketId): ?string
    {
        return PaketUjian::find($paketId)?->nama;
    }

    /**
     * Find sekolah name by ID.
     */
    public function findSekolahName(string $sekolahId): ?string
    {
        return Sekolah::find($sekolahId)?->nama;
    }
}
