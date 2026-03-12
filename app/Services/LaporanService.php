<?php

namespace App\Services;

use App\Models\JawabanPeserta;
use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\SesiPeserta;
use App\Repositories\LaporanRepository;

class LaporanService
{
    public function __construct(
        protected LaporanRepository $repository
    ) {}

    /**
     * Get hasil ujian with filters and pagination.
     */
    public function getHasilUjian(array $filters = []): array
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
        $paketList = PaketUjian::orderBy('nama')->get(['id', 'nama']);

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
                $query->where('nilai_akhir', '<', 70);
            }
        }

        $perPage = $filters['per_page'] ?? 30;
        $data = $query->latest('updated_at')->paginate($perPage);

        $rekap = $this->buildRekap($filters);

        return compact('sekolahList', 'paketList', 'data', 'rekap');
    }

    /**
     * Build rekap statistics based on current filters (single aggregate query).
     */
    protected function buildRekap(array $filters = []): array
    {
        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        $row = $query->selectRaw('
            COUNT(*) as total_peserta,
            SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
            SUM(CASE WHEN nilai_akhir < 70 THEN 1 ELSE 0 END) as tidak_lulus,
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
     * Get hasil ujian by sekolah.
     */
    public function getHasilBySekolah(string $sekolahId): mixed
    {
        return $this->repository->getHasilBySekolah($sekolahId);
    }

    /**
     * Get hasil ujian by paket.
     */
    public function getHasilByPaket(string $paketId): mixed
    {
        return $this->repository->getHasilByPaket($paketId);
    }

    /**
     * Get laporan statistics for a specific paket (single aggregate query).
     */
    public function getStatistik(?string $paketId = null): array
    {
        if ($paketId) {
            return $this->repository->getStatistik($paketId);
        }

        $row = SesiPeserta::whereIn('status', ['submit', 'dinilai'])
            ->selectRaw('
                COUNT(*) as total_peserta,
                ROUND(AVG(nilai_akhir), 1) as rata_rata,
                MAX(nilai_akhir) as nilai_max,
                MIN(nilai_akhir) as nilai_min,
                SUM(CASE WHEN nilai_akhir >= 70 THEN 1 ELSE 0 END) as lulus,
                SUM(CASE WHEN nilai_akhir < 70 THEN 1 ELSE 0 END) as tidak_lulus
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
     * Get rekap nilai with filters.
     */
    public function getRekapNilai(array $filters = []): mixed
    {
        return $this->repository->getRekapNilai(
            $filters['sekolah_id'] ?? null,
            $filters['paket_id'] ?? null
        );
    }

    /**
     * Export hasil ujian data for Excel generation (enriched).
     * Optimized: no jawaban eager-load, per-soal analysis via DB aggregates.
     */
    public function exportHasil(array $filters = []): array
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
                $query->where('nilai_akhir', '<', 70);
            }
        }

        $results = $query->latest('updated_at')->get();

        $hasilData = $results->map(fn ($sp) => [
            'nama_peserta'   => $sp->peserta->nama ?? '-',
            'nis'            => $sp->peserta->nis ?? '-',
            'nisn'           => $sp->peserta->nisn ?? '-',
            'kelas'          => $sp->peserta->kelas ?? '-',
            'jurusan'        => $sp->peserta->jurusan ?? '-',
            'sekolah'        => $sp->peserta->sekolah->nama ?? '-',
            'paket'          => $sp->sesi->paket->nama ?? '-',
            'sesi'           => $sp->sesi->nama_sesi ?? '-',
            'nilai_akhir'    => $sp->nilai_akhir ?? 0,
            'jumlah_benar'   => $sp->jumlah_benar ?? 0,
            'jumlah_salah'   => $sp->jumlah_salah ?? 0,
            'jumlah_kosong'  => $sp->jumlah_kosong ?? 0,
            'total_soal'     => ($sp->jumlah_benar ?? 0) + ($sp->jumlah_salah ?? 0) + ($sp->jumlah_kosong ?? 0),
            'durasi_menit'   => $sp->durasi_aktual_detik ? round($sp->durasi_aktual_detik / 60, 1) : 0,
            'status'         => ucfirst($sp->status),
            'keterangan'     => ($sp->nilai_akhir ?? 0) >= 70 ? 'Lulus' : 'Tidak Lulus',
            'mulai_at'       => $sp->mulai_at?->format('Y-m-d H:i:s') ?? '-',
            'submit_at'      => $sp->submit_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $sesiPesertaIds = $results->pluck('id');
        $perSoalData = $this->buildPerSoalAnalysisFromDb($sesiPesertaIds);

        $filterNames = [
            'paket_nama'   => null,
            'sekolah_nama' => null,
            'status'       => $filters['status'] ?? '',
        ];
        if (!empty($filters['paket_id'])) {
            $filterNames['paket_nama'] = PaketUjian::find($filters['paket_id'])?->nama;
        }
        if (!empty($filters['sekolah_id'])) {
            $filterNames['sekolah_nama'] = Sekolah::find($filters['sekolah_id'])?->nama;
        }

        return [
            'hasil'      => $hasilData,
            'perSoal'    => $perSoalData,
            'rekap'      => $this->buildRekap($filters),
            'filters'    => $filterNames,
        ];
    }

    /**
     * Build per-soal analysis using DB aggregates (no N+1, no full load).
     */
    protected function buildPerSoalAnalysisFromDb($sesiPesertaIds): array
    {
        if ($sesiPesertaIds->isEmpty()) {
            return [];
        }

        $rows = JawabanPeserta::join('soal', 'jawaban_peserta.soal_id', '=', 'soal.id')
            ->whereIn('jawaban_peserta.sesi_peserta_id', $sesiPesertaIds)
            ->groupBy('jawaban_peserta.soal_id', 'soal.tipe_soal', 'soal.pertanyaan')
            ->selectRaw('
                jawaban_peserta.soal_id,
                soal.tipe_soal,
                LEFT(soal.pertanyaan, 200) as pertanyaan_raw,
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
     * Get analisis soal (item analysis) for a specific paket.
     * Optimized: uses DB aggregates for benar/salah/kosong, targeted loads for discrimination.
     */
    public function getAnalisisSoal(string $paketId): array
    {
        $paket = PaketUjian::with('soal.opsiJawaban')->findOrFail($paketId);

        $sesiPesertaIds = SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->pluck('id');

        if ($sesiPesertaIds->isEmpty()) {
            return ['paket' => $paket, 'analisis' => [], 'summary' => []];
        }

        $totalPeserta = $sesiPesertaIds->count();

        // DB aggregate for per-soal benar/salah/kosong (single query, no full load)
        $soalAggregates = JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
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

        // Top/bottom 27% groups for discrimination index
        $topCount = max(1, (int) ceil($totalPeserta * 0.27));
        $topIds = SesiPeserta::whereIn('id', $sesiPesertaIds)
            ->orderByDesc('nilai_akhir')
            ->limit($topCount)
            ->pluck('id');
        $bottomIds = SesiPeserta::whereIn('id', $sesiPesertaIds)
            ->orderBy('nilai_akhir')
            ->limit($topCount)
            ->pluck('id');

        // Per-soal benar counts for top and bottom groups (2 aggregate queries)
        $topBenarBySoal = JawabanPeserta::whereIn('sesi_peserta_id', $topIds)
            ->where('is_terjawab', true)
            ->whereRaw('(COALESCE(skor_auto, 0) > 0 OR COALESCE(skor_manual, 0) > 0)')
            ->groupBy('soal_id')
            ->selectRaw('soal_id, COUNT(*) as benar')
            ->pluck('benar', 'soal_id');

        $bottomBenarBySoal = JawabanPeserta::whereIn('sesi_peserta_id', $bottomIds)
            ->where('is_terjawab', true)
            ->whereRaw('(COALESCE(skor_auto, 0) > 0 OR COALESCE(skor_manual, 0) > 0)')
            ->groupBy('soal_id')
            ->selectRaw('soal_id, COUNT(*) as benar')
            ->pluck('benar', 'soal_id');

        // Distractor analysis: load PG jawaban_pg grouped by soal_id (only for PG soal)
        $pgSoalIds = $paket->soal->whereIn('tipe_soal', ['pilihan_ganda', 'pilihan_ganda_kompleks'])->pluck('id');
        $distractorCounts = [];
        if ($pgSoalIds->isNotEmpty()) {
            $pgJawaban = JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
                ->whereIn('soal_id', $pgSoalIds)
                ->select('soal_id', 'jawaban_pg')
                ->get();
            foreach ($pgJawaban as $j) {
                $soalId = $j->soal_id;
                $pg = $j->jawaban_pg;
                if (is_array($pg)) {
                    foreach ($pg as $label) {
                        $distractorCounts[$soalId][$label] = ($distractorCounts[$soalId][$label] ?? 0) + 1;
                    }
                }
            }
        }

        $analisis = [];
        $totalDifficulty = 0;
        $countAnalyzed = 0;

        foreach ($paket->soal->sortBy('urutan') as $idx => $soal) {
            $agg = $soalAggregates->get($soal->id);
            $totalMenjawab = (int) ($agg->total_menjawab ?? 0);

            if ($totalMenjawab === 0) {
                $analisis[] = [
                    'nomor' => $idx + 1,
                    'soal_id' => $soal->id,
                    'tipe' => $soal->tipe_soal,
                    'pertanyaan' => mb_substr(strip_tags($soal->pertanyaan ?? ''), 0, 100),
                    'tingkat_kesulitan' => null,
                    'daya_beda' => null,
                    'pct_benar' => 0,
                    'pct_salah' => 0,
                    'pct_kosong' => 0,
                    'total_menjawab' => 0,
                    'distractors' => [],
                    'kategori_kesulitan' => '-',
                    'kategori_daya_beda' => '-',
                ];
                continue;
            }

            $benar = (int) ($agg->benar ?? 0);
            $salah = (int) ($agg->salah ?? 0);
            $kosong = (int) ($agg->kosong ?? 0);

            $difficulty = round($benar / $totalMenjawab, 3);

            $topBenar = (int) ($topBenarBySoal[$soal->id] ?? 0);
            $bottomBenar = (int) ($bottomBenarBySoal[$soal->id] ?? 0);
            $discrimination = $topCount > 0 ? round(($topBenar - $bottomBenar) / $topCount, 3) : 0;

            $distractors = [];
            if (in_array($soal->tipe_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'])) {
                $soalDistractors = $distractorCounts[$soal->id] ?? [];
                foreach ($soal->opsiJawaban as $opsi) {
                    $chosen = $soalDistractors[$opsi->label] ?? 0;
                    $distractors[] = [
                        'label' => $opsi->label,
                        'teks' => mb_substr($opsi->teks ?? '', 0, 40),
                        'is_benar' => $opsi->is_benar,
                        'dipilih' => $chosen,
                        'pct' => $totalMenjawab > 0 ? round(($chosen / $totalMenjawab) * 100, 1) : 0,
                    ];
                }
            }

            $katKesulitan = match (true) {
                $difficulty >= 0.7 => 'Mudah',
                $difficulty >= 0.3 => 'Sedang',
                default => 'Sulit',
            };
            $katDayaBeda = match (true) {
                $discrimination >= 0.4 => 'Sangat Baik',
                $discrimination >= 0.3 => 'Baik',
                $discrimination >= 0.2 => 'Cukup',
                default => 'Buruk',
            };

            $totalDifficulty += $difficulty;
            $countAnalyzed++;

            $analisis[] = [
                'nomor' => $idx + 1,
                'soal_id' => $soal->id,
                'tipe' => $soal->tipe_soal,
                'pertanyaan' => mb_substr(strip_tags($soal->pertanyaan ?? ''), 0, 100),
                'tingkat_kesulitan' => $difficulty,
                'daya_beda' => $discrimination,
                'pct_benar' => round(($benar / $totalMenjawab) * 100, 1),
                'pct_salah' => round(($salah / $totalMenjawab) * 100, 1),
                'pct_kosong' => round(($kosong / $totalMenjawab) * 100, 1),
                'total_menjawab' => $totalMenjawab,
                'distractors' => $distractors,
                'kategori_kesulitan' => $katKesulitan,
                'kategori_daya_beda' => $katDayaBeda,
            ];
        }

        $summary = [
            'total_soal' => count($analisis),
            'total_peserta' => $totalPeserta,
            'rata_kesulitan' => $countAnalyzed > 0 ? round($totalDifficulty / $countAnalyzed, 3) : 0,
            'soal_mudah' => collect($analisis)->where('kategori_kesulitan', 'Mudah')->count(),
            'soal_sedang' => collect($analisis)->where('kategori_kesulitan', 'Sedang')->count(),
            'soal_sulit' => collect($analisis)->where('kategori_kesulitan', 'Sulit')->count(),
            'daya_beda_baik' => collect($analisis)->filter(fn ($a) => in_array($a['kategori_daya_beda'], ['Sangat Baik', 'Baik']))->count(),
            'daya_beda_buruk' => collect($analisis)->where('kategori_daya_beda', 'Buruk')->count(),
        ];

        return ['paket' => $paket, 'analisis' => $analisis, 'summary' => $summary];
    }

    /**
     * Get detail jawaban per siswa for a specific sesi_peserta.
     */
    public function getDetailSiswa(string $sesiPesertaId): array
    {
        $sp = SesiPeserta::with(['peserta.sekolah', 'sesi.paket.soal.opsiJawaban', 'jawaban'])
            ->findOrFail($sesiPesertaId);

        $jawabanMap = $sp->jawaban->keyBy('soal_id');
        $detail = [];

        foreach ($sp->sesi->paket->soal->sortBy('urutan') as $idx => $soal) {
            $j = $jawabanMap->get($soal->id);
            $detail[] = [
                'nomor' => $idx + 1,
                'tipe' => $soal->tipe_soal,
                'pertanyaan' => mb_substr(strip_tags($soal->pertanyaan ?? ''), 0, 120),
                'jawaban' => $j ? ($j->jawaban_pg ?? $j->jawaban_teks ?? '-') : '-',
                'is_terjawab' => $j?->is_terjawab ?? false,
                'skor' => $j ? round(($j->skor_auto ?? 0) + ($j->skor_manual ?? 0), 2) : 0,
                'bobot' => $soal->bobot ?? 1,
                'is_benar' => $j && $j->is_terjawab && (($j->skor_auto ?? 0) > 0 || ($j->skor_manual ?? 0) > 0),
            ];
        }

        return [
            'sesiPeserta' => $sp,
            'detail' => $detail,
        ];
    }

    /**
     * Get detail nilai for a specific peserta.
     */
    public function getDetailNilaiPeserta(string $pesertaId): mixed
    {
        return $this->repository->getDetailNilaiPeserta($pesertaId);
    }
}
