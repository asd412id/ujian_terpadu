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
use Mews\Purifier\Facades\Purifier;

class SoalService
{
    private array $jenisMap = [
        'pilihan_ganda'          => 'pg',
        'pilihan_ganda_kompleks' => 'pg_kompleks',
        'benar_salah'            => 'benar_salah',
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
                'pertanyaan'        => $this->normalizeEditorContent($validated['pertanyaan']),
                'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
                'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
                'bobot'             => $validated['bobot'],
                'pembahasan'        => $this->normalizeEditorContent($validated['pembahasan'] ?? null),
                'sumber'            => $validated['sumber'] ?? null,
                'tahun_soal'        => $validated['tahun_soal'] ?? null,
                'narasi_id'             => $validated['narasi_id'] ?? null,
                'urutan_dalam_narasi'   => !empty($validated['narasi_id']) ? ($validated['urutan_dalam_narasi'] ?? 1) : 0,
            ];

            if ($request->hasFile('gambar_pertanyaan')) {
                $data['gambar_soal'] = $request->file('gambar_pertanyaan')
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
            } elseif ($data['tipe_soal'] === 'benar_salah') {
                $this->saveOpsiBenarSalah($soal, $request);
            } elseif ($data['tipe_soal'] === 'menjodohkan') {
                $this->savePasangan($soal, $request);
            } elseif (in_array($data['tipe_soal'], ['isian', 'essay'])) {
                $this->saveKunciJawaban($soal, $request);
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
            $soal->load(['opsiJawaban', 'pasangan']);

            $data = [
                'kategori_id'       => $validated['kategori_soal_id'],
                'tipe_soal'         => $this->jenisMap[$validated['jenis_soal']],
                'pertanyaan'        => $this->normalizeEditorContent($validated['pertanyaan']),
                'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
                'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
                'bobot'             => $validated['bobot'],
                'pembahasan'        => $this->normalizeEditorContent($validated['pembahasan'] ?? null),
                'narasi_id'             => $validated['narasi_id'] ?? null,
                'urutan_dalam_narasi'   => !empty($validated['narasi_id']) ? ($validated['urutan_dalam_narasi'] ?? 1) : 0,
            ];

            if ($request->hasFile('gambar_pertanyaan')) {
                if ($soal->gambar_soal) {
                    Storage::disk('public')->delete($soal->gambar_soal);
                }
                $data['gambar_soal'] = $request->file('gambar_pertanyaan')
                    ->store('soal/gambar', 'public');
            } elseif ($request->boolean('hapus_gambar_pertanyaan')) {
                if ($soal->gambar_soal) {
                    Storage::disk('public')->delete($soal->gambar_soal);
                }
                $data['gambar_soal'] = null;
            }

            $this->repository->update($soal, $data);

            // Clean up old images from opsi/pasangan before re-saving
            $this->deleteOpsiAndPasanganImages($soal);

            // Clear existing and re-save
            $this->repository->deleteOpsiJawaban($soal);
            $this->repository->deletePasangan($soal);

            if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
                $this->saveOpsi($soal, $request);
            } elseif ($data['tipe_soal'] === 'benar_salah') {
                $this->saveOpsiBenarSalah($soal, $request);
            } elseif ($data['tipe_soal'] === 'menjodohkan') {
                $this->savePasangan($soal, $request);
            } elseif (in_array($data['tipe_soal'], ['isian', 'essay'])) {
                $this->saveKunciJawaban($soal, $request);
            }

            return $soal->fresh(['opsiJawaban', 'pasangan', 'kategori']);
        });
    }

    /**
     * Delete a soal and its related data.
     */
    public function deleteSoal(Soal $soal): bool
    {
        return DB::transaction(function () use ($soal) {
            $soal->load(['opsiJawaban', 'pasangan']);
            $this->deleteAllSoalImages($soal);

            return (bool) $this->repository->delete($soal);
        });
    }

    private function deleteAllSoalImages(Soal $soal): void
    {
        $disk = Storage::disk('public');

        if ($soal->gambar_soal) {
            $disk->delete($soal->gambar_soal);
        }

        // Delete inline images embedded in HTML content (pertanyaan, pembahasan)
        foreach ($this->extractStoragePaths($soal->pertanyaan) as $path) {
            $disk->delete($path);
        }
        foreach ($this->extractStoragePaths($soal->pembahasan) as $path) {
            $disk->delete($path);
        }

        $this->deleteOpsiAndPasanganImages($soal);
    }

    /**
     * Normalize Tiptap editor HTML content.
     * Sanitizes HTML via HTMLPurifier to prevent XSS.
     * Returns null if content is empty or contains only empty HTML tags.
     * Preserves content that has images even without text.
     */
    private function normalizeEditorContent(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Sanitize HTML to prevent stored XSS
        $html = Purifier::clean($html, 'tiptap');

        // Check for text content or embedded images
        $textOnly = trim(strip_tags($html));
        if ($textOnly === '' && !str_contains($html, '<img')) {
            return null;
        }

        return trim($html);
    }

    private function deleteOpsiAndPasanganImages(Soal $soal): void
    {
        $disk = Storage::disk('public');

        foreach ($soal->opsiJawaban as $opsi) {
            if ($opsi->gambar) {
                $disk->delete($opsi->gambar);
            }
            // Delete inline images in opsi teks HTML
            foreach ($this->extractStoragePaths($opsi->teks) as $path) {
                $disk->delete($path);
            }
        }

        foreach ($soal->pasangan as $pas) {
            if ($pas->kiri_gambar) {
                $disk->delete($pas->kiri_gambar);
            }
            if ($pas->kanan_gambar) {
                $disk->delete($pas->kanan_gambar);
            }
        }
    }

    /**
     * Extract storage file paths from inline <img> tags in HTML content.
     *
     * Matches src attributes pointing to /storage/... and converts them
     * to relative paths suitable for Storage::disk('public')->delete().
     *
     * @return string[]
     */
    private function extractStoragePaths(?string $html): array
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return [];
        }

        $paths = [];

        // Match src="...storage/..." patterns
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                // Extract relative path from /storage/xxx or full URL with /storage/xxx
                if (preg_match('#/storage/(.+)$#', $src, $m)) {
                    $path = urldecode($m[1]);
                    // Sanitize: only allow paths within expected directories
                    if (str_starts_with($path, 'soal/') || str_starts_with($path, 'import/')) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Delete all soal (soft-delete) and remove associated images.
     */
    public function deleteAllSoal(): void
    {
        DB::transaction(function () {
            $disk = Storage::disk('public');

            $this->repository->chunkWithRelations(100, function ($soals) use ($disk) {
                foreach ($soals as $soal) {
                    $this->deleteAllSoalImages($soal);
                }
            });

            $this->repository->deleteAll();
        });
    }

    /**
     * Delete soal by kategori (soft-delete) and remove associated images.
     */
    public function deleteSoalByKategori(string $kategoriId): void
    {
        DB::transaction(function () use ($kategoriId) {
            $this->repository->chunkByKategoriWithRelations($kategoriId, 100, function ($soals) {
                foreach ($soals as $soal) {
                    $this->deleteAllSoalImages($soal);
                }
            });

            $this->repository->deleteByKategori($kategoriId);
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
            $teks   = $this->normalizeEditorContent($opsi['teks'] ?? null);
            $file   = $request->file("opsi.$i.gambar");
            $gambar = null;

            if ($file) {
                $gambar = $file->store('soal/opsi', 'public');
            } elseif (!empty($opsi['gambar_existing'])) {
                $gambar = $opsi['gambar_existing'];
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
     * Save pernyataan for Benar/Salah type.
     */
    private function saveOpsiBenarSalah(Soal $soal, Request $request): void
    {
        $pernyataanData = $request->input('pernyataan_bs', []);

        $opsiRecords = [];
        foreach ($pernyataanData as $i => $item) {
            $teks = $this->normalizeEditorContent($item['teks'] ?? null);
            $file = $request->file("pernyataan_bs.$i.gambar");
            $gambar = null;

            if ($file) {
                $gambar = $file->store('soal/opsi', 'public');
            } elseif (!empty($item['gambar_existing'])) {
                $gambar = $item['gambar_existing'];
            }

            if ($teks || $gambar) {
                $opsiRecords[] = [
                    'label'    => (string) ($i + 1),
                    'teks'     => $teks,
                    'gambar'   => $gambar,
                    'is_benar' => !empty($item['benar']) && $item['benar'] !== '0',
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
        $pasanganData = $request->input('pasangan', []);

        $pasanganRecords = [];
        foreach ($pasanganData as $i => $pair) {
            $kiri  = $pair['kiri'] ?? null;
            $kanan = $pair['kanan'] ?? null;

            $kiriGambar  = null;
            $kananGambar = null;

            $kiriFile  = $request->file("pasangan.$i.kiri_gambar");
            $kananFile = $request->file("pasangan.$i.kanan_gambar");

            if ($kiriFile) {
                $kiriGambar = $kiriFile->store('soal/pasangan', 'public');
            } elseif (!empty($pair['kiri_gambar_existing'])) {
                $kiriGambar = $pair['kiri_gambar_existing'];
            }

            if ($kananFile) {
                $kananGambar = $kananFile->store('soal/pasangan', 'public');
            } elseif (!empty($pair['kanan_gambar_existing'])) {
                $kananGambar = $pair['kanan_gambar_existing'];
            }

            if ($kiri || $kanan || $kiriGambar || $kananGambar) {
                $pasanganRecords[] = [
                    'kiri_teks'    => $kiri,
                    'kiri_gambar'  => $kiriGambar,
                    'kanan_teks'   => $kanan,
                    'kanan_gambar' => $kananGambar,
                    'urutan'       => $i,
                ];
            }
        }

        if (!empty($pasanganRecords)) {
            $this->repository->savePasangan($soal, $pasanganRecords);
        }
    }

    /**
     * Save kunci jawaban for isian / essay types.
     */
    private function saveKunciJawaban(Soal $soal, Request $request): void
    {
        $kunci = $this->normalizeEditorContent($request->input('kunci_jawaban'));
        if ($kunci) {
            $this->repository->saveOpsiJawaban($soal, [[
                'label'    => 'KUNCI',
                'teks'     => $kunci,
                'is_benar' => true,
                'urutan'   => 0,
            ]]);
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
                        'pertanyaan'   => $this->normalizeEditorContent($row['pertanyaan'] ?? $row['teks_soal'] ?? null),
                        'tipe_soal'    => $row['tipe_soal'] ?? 'pg',
                        'bobot'        => $row['bobot'] ?? 1,
                        'kategori_id'  => $row['kategori_id'] ?? $meta['kategori_id'] ?? null,
                        'created_by'   => $meta['created_by'] ?? null,
                    ], $meta);

                    if (empty($soalData['pertanyaan'])) {
                        $skipped++;
                        $errors[] = "Baris " . ($index + 1) . ": Teks soal kosong";
                        continue;
                    }

                    unset($soalData['opsi_jawaban'], $soalData['pasangan']);
                    $soal = $this->repository->create($soalData);

                    $opsiLabels = ['A', 'B', 'C', 'D', 'E'];
                    foreach ($opsiLabels as $label) {
                        $key = strtolower($label);
                        if (!empty($row[$key]) || !empty($row["opsi_{$key}"])) {
                            $teksOpsi = $this->normalizeEditorContent($row[$key] ?? $row["opsi_{$key}"] ?? null);
                            $isBenar = false;

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

            if ($imported > 0 && empty($errors)) {
                DB::commit();
            } elseif ($imported > 0 && !empty($errors)) {
                DB::rollBack();
                $imported = 0;
                $skipped = count($rows);
                array_unshift($errors, "Import dibatalkan: {$skipped} baris gagal, seluruh data di-rollback untuk konsistensi.");
            } else {
                DB::rollBack();
            }
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
        return $this->repository->createImportJob($data);
    }

    /**
     * Get import jobs for the current user (Dinas).
     */
    public function getImportJobsByUser(string $userId, int $limit = 10): mixed
    {
        return $this->repository->getImportJobsByUser($userId, $limit);
    }
}
