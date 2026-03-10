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
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
        $paketList = PaketUjian::orderBy('nama')->get();

        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket', 'jawaban'])
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
     * Build rekap statistics based on current filters.
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

        $results = $query->get();

        return [
            'total_peserta' => $results->count(),
            'sudah_ujian'   => $results->count(),
            'lulus'         => $results->where('nilai_akhir', '>=', 70)->count(),
            'tidak_lulus'   => $results->where('nilai_akhir', '<', 70)->count(),
            'rata_rata'     => $results->count() > 0 ? round($results->avg('nilai_akhir'), 1) : 0,
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
     * Get laporan statistics for a specific paket.
     */
    public function getStatistik(?string $paketId = null): array
    {
        if ($paketId) {
            return $this->repository->getStatistik($paketId);
        }

        $results = SesiPeserta::whereIn('status', ['submit', 'dinilai'])->get();

        return [
            'total_peserta' => $results->count(),
            'rata_rata'     => $results->count() > 0 ? round($results->avg('nilai_akhir'), 1) : 0,
            'nilai_max'     => $results->max('nilai_akhir') ?? 0,
            'nilai_min'     => $results->min('nilai_akhir') ?? 0,
            'lulus'         => $results->where('nilai_akhir', '>=', 70)->count(),
            'tidak_lulus'   => $results->where('nilai_akhir', '<', 70)->count(),
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
     */
    public function exportHasil(array $filters = []): array
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket', 'jawaban.soal'])
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

        $perSoalData = $this->buildPerSoalAnalysis($results);

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
     * Build per-soal analysis from SesiPeserta collection.
     */
    protected function buildPerSoalAnalysis($sesiPesertaCollection): array
    {
        $soalStats = [];

        foreach ($sesiPesertaCollection as $sp) {
            foreach ($sp->jawaban as $jawaban) {
                $soalId = $jawaban->soal_id;
                if (!isset($soalStats[$soalId])) {
                    $soal = $jawaban->soal;
                    $soalStats[$soalId] = [
                        'soal_id'       => $soalId,
                        'tipe'          => $soal->tipe_soal ?? '-',
                        'pertanyaan'    => mb_substr(strip_tags($soal->pertanyaan ?? ''), 0, 120),
                        'total_dijawab' => 0,
                        'benar'         => 0,
                        'salah'         => 0,
                        'kosong'        => 0,
                        'total_skor'    => 0,
                    ];
                }

                $soalStats[$soalId]['total_dijawab']++;

                if (!$jawaban->is_terjawab) {
                    $soalStats[$soalId]['kosong']++;
                } elseif (($jawaban->skor_auto ?? 0) > 0 || ($jawaban->skor_manual ?? 0) > 0) {
                    $soalStats[$soalId]['benar']++;
                } else {
                    $soalStats[$soalId]['salah']++;
                }

                $soalStats[$soalId]['total_skor'] += ($jawaban->skor_auto ?? 0) + ($jawaban->skor_manual ?? 0);
            }
        }

        $result = [];
        $nomor = 1;
        foreach ($soalStats as $stat) {
            $total = $stat['total_dijawab'] ?: 1;
            $result[] = [
                'nomor'         => $nomor++,
                'tipe'          => $stat['tipe'],
                'pertanyaan'    => $stat['pertanyaan'],
                'total_dijawab' => $stat['total_dijawab'],
                'benar'         => $stat['benar'],
                'salah'         => $stat['salah'],
                'kosong'        => $stat['kosong'],
                'pct_benar'     => round(($stat['benar'] / $total) * 100, 1),
                'rata_skor'     => round($stat['total_skor'] / $total, 2),
            ];
        }

        return $result;
    }

    /**
     * Get analisis soal (item analysis) for a specific paket.
     * Returns: per-soal stats including difficulty index, discrimination index, distractor effectiveness.
     */
    public function getAnalisisSoal(string $paketId): array
    {
        $paket = PaketUjian::with('soal.opsiJawaban')->findOrFail($paketId);

        // Get all sesi_peserta that completed this paket
        $sesiPesertaIds = SesiPeserta::whereHas('sesi', fn ($q) => $q->where('paket_id', $paketId))
            ->whereIn('status', ['submit', 'dinilai'])
            ->pluck('id');

        if ($sesiPesertaIds->isEmpty()) {
            return ['paket' => $paket, 'analisis' => [], 'summary' => []];
        }

        $totalPeserta = $sesiPesertaIds->count();

        // Get all jawaban for these peserta
        $jawaban = JawabanPeserta::whereIn('sesi_peserta_id', $sesiPesertaIds)
            ->get()
            ->groupBy('soal_id');

        // Get all nilai_akhir for top/bottom group analysis
        $nilaiList = SesiPeserta::whereIn('id', $sesiPesertaIds)
            ->orderByDesc('nilai_akhir')
            ->pluck('nilai_akhir', 'id');

        $topCount = max(1, (int) ceil($totalPeserta * 0.27));
        $topIds = $nilaiList->take($topCount)->keys();
        $bottomIds = $nilaiList->reverse()->take($topCount)->keys();

        $analisis = [];
        $totalDifficulty = 0;
        $countAnalyzed = 0;

        foreach ($paket->soal->sortBy('urutan') as $idx => $soal) {
            $soalJawaban = $jawaban->get($soal->id, collect());
            $totalMenjawab = $soalJawaban->count();

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

            // Count benar/salah/kosong
            $benar = $soalJawaban->filter(fn ($j) => $j->is_terjawab && (($j->skor_auto ?? 0) > 0 || ($j->skor_manual ?? 0) > 0))->count();
            $salah = $soalJawaban->filter(fn ($j) => $j->is_terjawab && ($j->skor_auto ?? 0) == 0 && ($j->skor_manual ?? 0) == 0)->count();
            $kosong = $soalJawaban->filter(fn ($j) => !$j->is_terjawab)->count();

            // Tingkat kesulitan (difficulty index) = benar / total
            $difficulty = $totalMenjawab > 0 ? round($benar / $totalMenjawab, 3) : 0;

            // Daya beda (discrimination index) = (benar_atas - benar_bawah) / n_group
            $topJawaban = $soalJawaban->whereIn('sesi_peserta_id', $topIds);
            $bottomJawaban = $soalJawaban->whereIn('sesi_peserta_id', $bottomIds);
            $topBenar = $topJawaban->filter(fn ($j) => $j->is_terjawab && (($j->skor_auto ?? 0) > 0 || ($j->skor_manual ?? 0) > 0))->count();
            $bottomBenar = $bottomJawaban->filter(fn ($j) => $j->is_terjawab && (($j->skor_auto ?? 0) > 0 || ($j->skor_manual ?? 0) > 0))->count();
            $discrimination = $topCount > 0 ? round(($topBenar - $bottomBenar) / $topCount, 3) : 0;

            // Distractor analysis for PG types
            $distractors = [];
            if (in_array($soal->tipe_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'])) {
                foreach ($soal->opsiJawaban as $opsi) {
                    $chosen = $soalJawaban->filter(function ($j) use ($opsi) {
                        $pg = $j->jawaban_pg;
                        if (is_array($pg)) {
                            return in_array($opsi->label, $pg);
                        }
                        return false;
                    })->count();
                    $distractors[] = [
                        'label' => $opsi->label,
                        'teks' => mb_substr($opsi->teks ?? '', 0, 40),
                        'is_benar' => $opsi->is_benar,
                        'dipilih' => $chosen,
                        'pct' => $totalMenjawab > 0 ? round(($chosen / $totalMenjawab) * 100, 1) : 0,
                    ];
                }
            }

            // Kategorisasi
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
                'pct_benar' => $totalMenjawab > 0 ? round(($benar / $totalMenjawab) * 100, 1) : 0,
                'pct_salah' => $totalMenjawab > 0 ? round(($salah / $totalMenjawab) * 100, 1) : 0,
                'pct_kosong' => $totalMenjawab > 0 ? round(($kosong / $totalMenjawab) * 100, 1) : 0,
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
