<?php

namespace App\Services;

use App\Models\Soal;
use App\Repositories\SoalRepository;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SoalService
{
    private array $jenisMap = [
        'pilihan_ganda'          => 'pg',
        'pilihan_ganda_kompleks' => 'pg_kompleks',
        'menjodohkan'            => 'menjodohkan',
        'isian'                  => 'isian',
        'essay'                  => 'essay',
    ];

    public function __construct(
        protected SoalRepository $repository,
        protected KategoriSoalRepository $kategoriRepository
    ) {}

    /**
     * Get filtered list of soal with pagination (Dinas view).
     */
    public function getFilteredSoal(
        ?string $kategoriId = null,
        ?string $tipe = null,
        ?string $kesulitan = null,
        ?string $search = null,
        int $perPage = 20
    ): mixed {
        return $this->repository->getFilteredSoal($kategoriId, $tipe, $kesulitan, $search, $perPage);
    }

    /**
     * Get active kategori soal list.
     */
    public function getActiveKategori(): mixed
    {
        return $this->kategoriRepository->getActive();
    }

    /**
     * Get a single soal by ID with relations.
     */
    public function getSoalById(string $id): ?Soal
    {
        return $this->repository->findWithRelations($id, ['opsiJawaban', 'pasangan', 'kategori']);
    }

    /**
     * Create a new soal with opsi jawaban / pasangan from request data.
     */
    public function createSoal(array $validated, Request $request): Soal
    {
        return DB::transaction(function () use ($validated, $request) {
            $data = [
                'kategori_id'       => $validated['kategori_soal_id'],
                'tipe_soal'         => $this->jenisMap[$validated['jenis_soal']],
                'pertanyaan'        => $validated['pertanyaan'],
                'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
                'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
                'bobot'             => $validated['bobot'],
                'pembahasan'        => $validated['pembahasan'] ?? null,
                'sumber'            => $validated['sumber'] ?? null,
                'tahun_soal'        => $validated['tahun_soal'] ?? null,
            ];

            if ($request->hasFile('gambar_pertanyaan')) {
                $data['gambar_pertanyaan'] = $request->file('gambar_pertanyaan')
                    ->store('soal/gambar', 'public');
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $data['created_by'] = $user->id;
            $data['sekolah_id'] = $user->sekolah_id;

            $soal = $this->repository->create($data);

            // Simpan opsi jawaban
            if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
                $this->saveOpsi($soal, $request);
            } elseif ($data['tipe_soal'] === 'menjodohkan') {
                $this->savePasangan($soal, $request);
            }

            return $soal;
        });
    }

    /**
     * Update an existing soal with opsi jawaban / pasangan from request data.
     */
    public function updateSoal(Soal $soal, array $validated, Request $request): Soal
    {
        return DB::transaction(function () use ($soal, $validated, $request) {
            $data = [
                'kategori_id'       => $validated['kategori_soal_id'],
                'tipe_soal'         => $this->jenisMap[$validated['jenis_soal']],
                'pertanyaan'        => $validated['pertanyaan'],
                'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
                'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
                'bobot'             => $validated['bobot'],
                'pembahasan'        => $validated['pembahasan'] ?? null,
            ];

            if ($request->hasFile('gambar_pertanyaan')) {
                if ($soal->gambar_pertanyaan) {
                    Storage::disk('public')->delete($soal->gambar_pertanyaan);
                }
                $data['gambar_pertanyaan'] = $request->file('gambar_pertanyaan')
                    ->store('soal/gambar', 'public');
            }

            $this->repository->update($soal, $data);

            // Clear existing and re-save
            $this->repository->deleteOpsiJawaban($soal);
            $this->repository->deletePasangan($soal);

            if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
                $this->saveOpsi($soal, $request);
            } elseif ($data['tipe_soal'] === 'menjodohkan') {
                $this->savePasangan($soal, $request);
            }

            return $soal;
        });
    }

    /**
     * Delete a soal and its related data.
     */
    public function deleteSoal(Soal $soal): bool
    {
        return DB::transaction(function () use ($soal) {
            // Delete associated image
            if ($soal->gambar_pertanyaan) {
                Storage::disk('public')->delete($soal->gambar_pertanyaan);
            }

            return (bool) $this->repository->delete($soal);
        });
    }

    /**
     * Save opsi jawaban for PG / PG Kompleks types.
     */
    private function saveOpsi(Soal $soal, Request $request): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $opsiData = $request->input('opsi', []);

        $opsiRecords = [];
        foreach ($opsiData as $i => $opsi) {
            $teks   = $opsi['teks'] ?? null;
            $file   = $request->file("opsi.$i.gambar");
            $gambar = null;

            if ($file) {
                $gambar = $file->store('soal/opsi', 'public');
            }

            if ($teks || $gambar) {
                $opsiRecords[] = [
                    'label'    => $labels[$i] ?? (string) $i,
                    'teks'     => $teks,
                    'gambar'   => $gambar,
                    'is_benar' => !empty($opsi['benar']) && $opsi['benar'] !== '0',
                    'urutan'   => $i,
                ];
            }
        }

        if (!empty($opsiRecords)) {
            $this->repository->saveOpsiJawaban($soal, $opsiRecords);
        }
    }

    /**
     * Save pasangan for menjodohkan type.
     */
    private function savePasangan(Soal $soal, Request $request): void
    {
        $kiriTeks  = $request->input('pasangan_kiri_teks', []);
        $kananTeks = $request->input('pasangan_kanan_teks', []);

        $pasanganRecords = [];
        foreach ($kiriTeks as $i => $kiri) {
            $pasanganRecords[] = [
                'kiri_teks'  => $kiri,
                'kanan_teks' => $kananTeks[$i] ?? null,
                'urutan'     => $i,
            ];
        }

        if (!empty($pasanganRecords)) {
            $this->repository->savePasangan($soal, $pasanganRecords);
        }
    }

    /**
     * Import soal from Excel file data.
     *
     * @param  array  $rows  Parsed rows from Excel
     * @param  array  $meta  Additional metadata (kategori_id, created_by, etc.)
     * @return array  Import summary with counts
     */
    public function importSoal(array $rows, array $meta = []): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $soalData = array_merge([
                        'teks_soal'    => $row['teks_soal'] ?? $row['pertanyaan'] ?? null,
                        'tipe_soal'    => $row['tipe_soal'] ?? 'pg',
                        'bobot'        => $row['bobot'] ?? 1,
                        'kategori_id'  => $row['kategori_id'] ?? $meta['kategori_id'] ?? null,
                        'created_by'   => $meta['created_by'] ?? null,
                    ], $meta);

                    if (empty($soalData['teks_soal'])) {
                        $skipped++;
                        $errors[] = "Baris " . ($index + 1) . ": Teks soal kosong";
                        continue;
                    }

                    unset($soalData['opsi_jawaban'], $soalData['pasangan']);
                    $soal = $this->repository->create($soalData);

                    // Import opsi jawaban from columns (A, B, C, D, E, jawaban_benar)
                    $opsiLabels = ['A', 'B', 'C', 'D', 'E'];
                    foreach ($opsiLabels as $label) {
                        $key = strtolower($label);
                        if (!empty($row[$key]) || !empty($row["opsi_{$key}"])) {
                            $teksOpsi = $row[$key] ?? $row["opsi_{$key}"];
                            $isBenar = false;

                            // Check if this option is the correct answer
                            $jawabanBenar = $row['jawaban_benar'] ?? $row['kunci'] ?? '';
                            if (strtoupper(trim($jawabanBenar)) === $label) {
                                $isBenar = true;
                            }

                            $soal->opsiJawaban()->create([
                                'label'    => $label,
                                'teks'     => $teksOpsi,
                                'is_benar' => $isBenar,
                            ]);
                        }
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Baris " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'total'    => count($rows),
        ];
    }

    /**
     * Get soal filtered by sekolah with optional filters.
     */
    public function getBySekolah(string $sekolahId, array $filters = []): mixed
    {
        return $this->repository->getFilteredBySekolah(
            $sekolahId,
            $filters['q'] ?? null,
            $filters['kategori'] ?? null,
            $filters['jenis'] ?? null
        );
    }

    /**
     * Get soal associated with a paket ujian.
     */
    public function getSoalByPaketUjian(string $paketId, array $excludeSoalIds = [], int $perPage = 10): mixed
    {
        return $this->repository->getByPaketUjian($paketId, $excludeSoalIds, $perPage);
    }

    /**
     * Create an import job for soal (Excel or Word).
     */
    public function createImportJob(array $data): \App\Models\ImportJob
    {
        $job = \App\Models\ImportJob::create($data);

        if ($data['tipe'] === 'soal_excel') {
            dispatch(new \App\Jobs\ImportSoalExcelJob($job));
        } elseif ($data['tipe'] === 'soal_word') {
            dispatch(new \App\Jobs\ImportSoalWordJob($job));
        }

        return $job;
    }

    /**
     * Get import jobs for a sekolah.
     */
    public function getImportJobs(string $sekolahId, int $limit = 10): mixed
    {
        return \App\Models\ImportJob::where('sekolah_id', $sekolahId)
            ->whereIn('tipe', ['soal_excel', 'soal_word'])
            ->latest()
            ->take($limit)
            ->get();
    }
}
