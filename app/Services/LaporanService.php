<?php

namespace App\Services;

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
        $sekolahList = $this->repository->getSekolahList();
        $paketList = $this->repository->getPaketList();
        $data = $this->repository->getHasilUjianFiltered($filters);
        $rekap = $this->repository->buildRekap($filters);

        return compact('sekolahList', 'paketList', 'data', 'rekap');
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

        return $this->repository->getGlobalStatistik();
    }

        // Removed: getRekapNilai was dead code (0 callers)

    /**
     * Export hasil ujian data for Excel generation (memory-efficient).
     */
    public function exportHasil(array $filters = []): array
    {
        $hasilData = [];
        $maxRows = 10000;

        $this->repository->chunkHasilForExport($filters, 500, function ($chunk) use (&$hasilData, $maxRows) {
            foreach ($chunk as $sp) {
                if (count($hasilData) >= $maxRows) {
                    return false;
                }
                $hasilData[] = [
                    'nama_peserta'   => $sp->peserta->nama ?? '-',
                    'nis'            => $sp->peserta->nis ?? '-',
                    'nisn'           => $sp->peserta->nisn ?? '-',
                    'kelas'          => $sp->peserta->kelas ?? '-',
                    'jurusan'        => $sp->peserta->jurusan ?? '-',
                    'sekolah'        => $sp->peserta->sekolah->nama ?? '-',
                    'paket'          => $sp->sesi->paket->nama ?? '-',
                    'sesi'           => $sp->sesi->nama_sesi ?? '-',
                    'nilai_akhir'    => round($sp->nilai_akhir ?? 0, 2),
                    'jumlah_benar'   => (int) ($sp->jumlah_benar ?? 0),
                    'jumlah_salah'   => (int) ($sp->jumlah_salah ?? 0),
                    'jumlah_kosong'  => (int) ($sp->jumlah_kosong ?? 0),
                    'total_soal'     => (int) ($sp->jumlah_benar ?? 0) + (int) ($sp->jumlah_salah ?? 0) + (int) ($sp->jumlah_kosong ?? 0),
                    'durasi_menit'   => $sp->durasi_aktual_detik ? round(abs($sp->durasi_aktual_detik) / 60, 1) : 0,
                    'status'         => ucfirst($sp->status),
                    'keterangan'     => ($sp->nilai_akhir ?? 0) >= 70 ? 'Lulus' : 'Tidak Lulus',
                    'mulai_at'       => $sp->mulai_at?->format('Y-m-d H:i:s') ?? '-',
                    'submit_at'      => $sp->submit_at?->format('Y-m-d H:i:s') ?? '-',
                ];
            }
            // Free Eloquent models after mapping to plain arrays
            unset($chunk);
        });

        $filterNames = [
            'paket_nama'   => !empty($filters['paket_id']) ? $this->repository->findPaketName($filters['paket_id']) : null,
            'sekolah_nama' => !empty($filters['sekolah_id']) ? $this->repository->findSekolahName($filters['sekolah_id']) : null,
            'status'       => $filters['status'] ?? '',
        ];

        // Only build perSoal if data is small enough (< 2000 rows)
        $perSoalData = [];
        if (count($hasilData) < 2000) {
            $sesiPesertaIds = $this->repository->getExportSesiPesertaIds($filters);
            $perSoalData = $this->repository->buildPerSoalAnalysis($sesiPesertaIds);
        }

        return [
            'hasil'      => $hasilData,
            'perSoal'    => $perSoalData,
            'rekap'      => $this->repository->buildRekap($filters),
            'filters'    => $filterNames,
        ];
    }

    /**
     * Get analisis soal (item analysis) for a specific paket.
     */
    public function getAnalisisSoal(string $paketId): array
    {
        $paket = $this->repository->findPaketWithSoal($paketId);
        $sesiPesertaIds = $this->repository->getSubmittedSesiPesertaIds($paketId);

        if ($sesiPesertaIds->isEmpty()) {
            return ['paket' => $paket, 'analisis' => [], 'summary' => []];
        }

        $totalPeserta = $sesiPesertaIds->count();
        $soalAggregates = $this->repository->getSoalAggregates($sesiPesertaIds);

        $topCount = max(1, (int) ceil($totalPeserta * 0.27));
        [$topIds, $bottomIds] = $this->repository->getTopBottomIds($sesiPesertaIds, $topCount);

        $topBenarBySoal = $this->repository->getBenarCountsBySoal($topIds);
        $bottomBenarBySoal = $this->repository->getBenarCountsBySoal($bottomIds);

        $pgSoalIds = $paket->soal->whereIn('tipe_soal', ['pilihan_ganda', 'pilihan_ganda_kompleks'])->pluck('id');
        $distractorCounts = $this->repository->getDistractorCounts($sesiPesertaIds, $pgSoalIds);

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
        $sp = $this->repository->findSesiPesertaWithDetail($sesiPesertaId);

        // Build label remap: display label → original label per soal
        $labelRemap = $this->buildLabelRemap($sp);

        $jawabanMap = $sp->jawaban->keyBy('soal_id');
        $detail = [];

        foreach ($sp->sesi->paket->soal->sortBy('urutan') as $idx => $soal) {
            $j = $jawabanMap->get($soal->id);
            $remap = $labelRemap[$soal->id] ?? [];

            // Build kunci jawaban
            $kunci = $this->buildKunci($soal);

            // Build jawaban peserta (remapped to original labels)
            $jawaban = '-';
            $jawabanDisplay = '-';
            if ($j) {
                $raw = $j->jawaban_pg ?? $j->jawaban_teks ?? $j->jawaban_pasangan ?? null;
                [$jawaban, $jawabanDisplay] = $this->formatJawaban($soal, $j, $remap);
            }

            $detail[] = [
                'nomor' => $idx + 1,
                'tipe' => $soal->tipe_soal,
                'pertanyaan' => mb_substr(strip_tags($soal->pertanyaan ?? ''), 0, 120),
                'kunci' => $kunci,
                'jawaban' => $jawaban,
                'jawaban_display' => $jawabanDisplay,
                'opsi' => $this->buildOpsiList($soal, $remap),
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

    private function buildLabelRemap(\App\Models\SesiPeserta $sp): array
    {
        $urutanOpsi = $sp->urutan_opsi ?? [];
        if (empty($urutanOpsi)) return [];

        $labels = range('A', 'Z');
        $remap  = [];

        $opsiBysoal = [];
        foreach ($sp->sesi->paket->soal as $soal) {
            $opsiBysoal[$soal->id] = $soal->opsiJawaban->keyBy('id');
        }

        foreach ($urutanOpsi as $soalId => $opsiIds) {
            if (!is_array($opsiIds) || !isset($opsiBysoal[$soalId])) continue;
            $opsiMap = $opsiBysoal[$soalId];
            $soalRemap = [];
            foreach ($opsiIds as $i => $opsiId) {
                $opsi = $opsiMap[$opsiId] ?? null;
                if ($opsi) {
                    $displayLabel = $labels[$i] ?? chr(65 + $i);
                    $soalRemap[$displayLabel] = $opsi->label;
                }
            }
            $remap[$soalId] = $soalRemap;
        }

        return $remap;
    }

    private function buildKunci(\App\Models\Soal $soal): string
    {
        return match ($soal->tipe_soal) {
            'pg' => $soal->opsiJawaban->where('is_benar', true)->pluck('label')->first() ?? '-',
            'pg_kompleks' => $soal->opsiJawaban->where('is_benar', true)->pluck('label')->sort()->implode(', '),
            'benar_salah' => $soal->opsiJawaban->map(fn($o) => $o->label . ':' . ($o->is_benar ? 'B' : 'S'))->implode(', '),
            'isian' => $soal->opsiJawaban->where('is_benar', true)->pluck('teks')->first() ?? '-',
            'menjodohkan' => $soal->pasangan->count() . ' pasangan',
            'essay' => 'Manual',
            default => '-',
        };
    }

    private function formatJawaban(\App\Models\Soal $soal, \App\Models\JawabanPeserta $j, array $remap): array
    {
        if (!$j->is_terjawab) return ['-', '-'];

        $remapFn = fn(string $label) => $remap[$label] ?? $label;

        return match ($soal->tipe_soal) {
            'pg' => (function () use ($j, $remapFn) {
                $display = $j->jawaban_pg[0] ?? '-';
                $original = $remapFn($display);
                return [$original, $display !== $original ? "{$display}→{$original}" : $display];
            })(),
            'pg_kompleks' => (function () use ($j, $remapFn) {
                $answers = $j->jawaban_pg ?? [];
                $remapped = array_map($remapFn, $answers);
                sort($remapped);
                $displayStr = implode(',', $answers);
                $origStr = implode(',', $remapped);
                return [$origStr, $displayStr !== $origStr ? "{$displayStr}→{$origStr}" : $origStr];
            })(),
            'benar_salah' => (function () use ($j, $remapFn, $soal) {
                $answers = $j->jawaban_pg ?? [];
                $parts = [];
                $remappedParts = [];
                foreach ($answers as $dl => $val) {
                    $ol = $remapFn((string) $dl);
                    $short = $val === 'benar' ? 'B' : 'S';
                    $parts[] = "{$dl}:{$short}";
                    $remappedParts[] = "{$ol}:{$short}";
                }
                return [implode(', ', $remappedParts), implode(', ', $parts)];
            })(),
            'isian' => [$j->jawaban_teks ?? '-', $j->jawaban_teks ?? '-'],
            'essay' => [mb_substr($j->jawaban_teks ?? '-', 0, 60), mb_substr($j->jawaban_teks ?? '-', 0, 60)],
            'menjodohkan' => (function () use ($j) {
                $pairs = $j->jawaban_pasangan ?? [];
                return [count($pairs) . ' pasangan', count($pairs) . ' pasangan'];
            })(),
            default => ['-', '-'],
        };
    }

    private function buildOpsiList(\App\Models\Soal $soal, array $remap): array
    {
        if (!in_array($soal->tipe_soal, ['pg', 'pg_kompleks', 'benar_salah'])) return [];

        return $soal->opsiJawaban->sortBy('urutan')->map(function ($opsi) use ($remap) {
            $displayLabel = null;
            if (!empty($remap)) {
                $displayLabel = array_search($opsi->label, $remap);
            }
            return [
                'label' => $opsi->label,
                'display_label' => $displayLabel ?: $opsi->label,
                'teks' => mb_substr(strip_tags($opsi->teks ?? ''), 0, 80),
                'is_benar' => (bool) $opsi->is_benar,
            ];
        })->values()->toArray();
    }

    /**
     * Get detail nilai for a specific peserta.
     */
    public function getDetailNilaiPeserta(string $pesertaId): mixed
    {
        return $this->repository->getDetailNilaiPeserta($pesertaId);
    }
}
