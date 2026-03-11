<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Soal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;

/**
 * Import soal from Word document.
 *
 * Optimized: processes blocks in chunks with batch insert for
 * opsi_jawaban and pasangan_soal instead of individual creates.
 */

class ImportSoalWordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    private const CHUNK_SIZE = 50;

    public function __construct(
        public ImportJob $importJob,
        public ?string $imagesPath = null
    ) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $filePath = Storage::disk('local')->path($this->importJob->filepath);

            $phpWord    = IOFactory::load($filePath);
            $sections   = $phpWord->getSections();
            $soalBlocks = $this->parseWordSections($sections);

            $this->importJob->update(['total_rows' => count($soalBlocks)]);

            $errors  = [];
            $success = 0;

            // Process in chunks instead of one giant transaction
            $chunks = array_chunk($soalBlocks, self::CHUNK_SIZE, true);

            foreach ($chunks as $chunk) {
                DB::transaction(function () use ($chunk, &$errors, &$success) {
                    $opsiBatch     = [];
                    $pasanganBatch = [];

                    foreach ($chunk as $index => $block) {
                        try {
                            $result = $this->processBlock($block);
                            if (!empty($result['opsi'])) {
                                array_push($opsiBatch, ...$result['opsi']);
                            }
                            if (!empty($result['pasangan'])) {
                                array_push($pasanganBatch, ...$result['pasangan']);
                            }
                            $success++;
                        } catch (\Exception $e) {
                            $errors[] = "Soal " . ($index + 1) . ": " . $e->getMessage();
                        }
                    }

                    // Bulk insert all opsi and pasangan for this chunk
                    if (!empty($opsiBatch)) {
                        foreach (array_chunk($opsiBatch, 100) as $subChunk) {
                            OpsiJawaban::insert($subChunk);
                        }
                    }
                    if (!empty($pasanganBatch)) {
                        foreach (array_chunk($pasanganBatch, 100) as $subChunk) {
                            PasanganSoal::insert($subChunk);
                        }
                    }
                });

                // Update progress per chunk
                $lastIndex = array_key_last($chunk);
                $this->importJob->update(['processed_rows' => $lastIndex + 1]);
            }

            // Clean up extracted images folder
            if ($this->imagesPath && is_dir($this->imagesPath)) {
                $this->cleanupDirectory($this->imagesPath);
            }

            $this->importJob->update([
                'status'         => 'selesai',
                'processed_rows' => count($soalBlocks),
                'success_rows'   => $success,
                'error_rows'     => count($errors),
                'errors'         => $errors,
                'completed_at'   => now(),
            ]);

        } catch (\Exception $e) {
            $this->importJob->update([
                'status'  => 'gagal',
                'catatan' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse Word document sections into structured soal blocks.
     */
    private function parseWordSections(array $sections): array
    {
        $blocks  = [];
        $current = null;

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                $result = $this->extractElementContent($element);
                if (!$result) continue;

                $text   = $result['text'];
                $images = $result['images'];

                if (empty($text) && empty($images)) continue;

                // Skip known heading-only lines
                if (preg_match('/^(PILIHAN GANDA|PG KOMPLEKS|MENJODOHKAN|ISIAN SINGKAT|ESSAY|BENAR|CATATAN|GAMBAR|Template Import)/i', $text)) continue;

                // New soal: starts with number.
                if (preg_match('/^(\d+)\.\s+(.+)/s', $text, $m)) {
                    if ($current) $blocks[] = $current;

                    $soalText = trim($m[2]);
                    $jenis    = 'pg';

                    // Detect tag
                    if (preg_match('/^\[(PG_KOMPLEKS|MENJODOHKAN|ISIAN|ESSAY|BENAR_SALAH)\]\s*(.+)/si', $soalText, $tagMatch)) {
                        $tag = strtoupper($tagMatch[1]);
                        $soalText = trim($tagMatch[2]);
                        $jenis = match ($tag) {
                            'PG_KOMPLEKS'  => 'pg_kompleks',
                            'MENJODOHKAN'  => 'menjodohkan',
                            'ISIAN'        => 'isian',
                            'ESSAY'        => 'essay',
                            'BENAR_SALAH'  => 'benar_salah',
                        };
                    }

                    // Parse gambar reference from pertanyaan text
                    $gambarFromText = null;
                    if (preg_match('/\[gambar:\s*(.+?)\]/i', $soalText, $gm)) {
                        $soalText = trim(preg_replace('/\[gambar:\s*.+?\]/i', '', $soalText));
                        $gambarFromText = $this->saveImageFromFolder(trim($gm[1]));
                    }

                    // Parse optional meta tags: [tingkat: mudah/sedang/sulit] [bobot: angka]
                    $tingkat = 'sedang';
                    $bobot   = 1.0;
                    if (preg_match('/\[tingkat:\s*(mudah|sedang|sulit)\]/i', $soalText, $tm)) {
                        $tingkat = strtolower(trim($tm[1]));
                        $soalText = trim(preg_replace('/\[tingkat:\s*(mudah|sedang|sulit)\]/i', '', $soalText));
                    }
                    if (preg_match('/\[bobot:\s*([\d.,]+)\]/i', $soalText, $bm)) {
                        $bobot = (float) str_replace(',', '.', trim($bm[1]));
                        $soalText = trim(preg_replace('/\[bobot:\s*[\d.,]+\]/i', '', $soalText));
                    }

                    $current = [
                        'pertanyaan'    => $soalText,
                        'jenis'         => $jenis,
                        'opsi'          => [],
                        'opsi_gambar'   => [],
                        'kunci'         => null,
                        'gambar_soal'   => !empty($images) ? $this->saveImageData($images[0]) : $gambarFromText,
                        'pasangan'      => [],
                        'pernyataan_bs' => [],
                        'tingkat'       => $tingkat,
                        'bobot'         => $bobot,
                    ];

                // Benar/Salah pernyataan lines: 1) Pernyataan text (BENAR) or 1) Pernyataan text (SALAH)
                } elseif ($current && $current['jenis'] === 'benar_salah' && preg_match('/^(\d+)\)\s*(.+?)\s*\((BENAR|SALAH)\)\s*$/i', $text, $m)) {
                    $current['pernyataan_bs'][] = [
                        'teks'  => trim($m[2]),
                        'benar' => strtoupper($m[3]) === 'BENAR',
                    ];

                // Option lines: a. / b. / c. / d. / e. (with or without text — may have only embedded image)
                } elseif ($current && preg_match('/^([a-eA-E])\.\s*(.*)/s', $text, $m)) {
                    $label    = strtoupper($m[1]);
                    $opsiText = trim($m[2]);

                    // Parse text-based image reference: "teks | gambar: filename.png" or "| gambar: filename.png" or "gambar: filename.png"
                    if (preg_match('/^(.*?)\s*\|\s*gambar:\s*(.+)$/i', $opsiText, $gm)) {
                        $opsiText = trim($gm[1]);
                        $imgFile  = trim($gm[2]);
                        $savedPath = $this->saveImageFromFolder($imgFile);
                        if ($savedPath) {
                            $current['opsi_gambar'][$label] = $savedPath;
                        }
                    } elseif (preg_match('/^gambar:\s*(.+)$/i', $opsiText, $gm)) {
                        $opsiText = '';
                        $imgFile  = trim($gm[1]);
                        $savedPath = $this->saveImageFromFolder($imgFile);
                        if ($savedPath) {
                            $current['opsi_gambar'][$label] = $savedPath;
                        }
                    }

                    $current['opsi'][$label] = $opsiText;

                    // Embedded image in this option paragraph (image-only or image+text)
                    if (!empty($images) && empty($current['opsi_gambar'][$label])) {
                        $current['opsi_gambar'][$label] = $this->saveImageData($images[0]);
                    }

                    // Ensure opsi entry exists even if text is empty (image-only option)
                    if (empty($opsiText) && !empty($current['opsi_gambar'][$label])) {
                        $current['opsi'][$label] = '';
                    }

                // Menjodohkan: "kiri = kanan" with optional image refs: "kiri | gambar: f.png = kanan | gambar: g.png"
                } elseif ($current && $current['jenis'] === 'menjodohkan' && preg_match('/^(.+?)\s*=\s*(.+)$/', $text, $m)) {
                    $kiriRaw  = trim($m[1]);
                    $kananRaw = trim($m[2]);
                    $kiriGambar  = null;
                    $kananGambar = null;

                    if (preg_match('/^(.*?)\s*\|\s*gambar:\s*(.+)$/i', $kiriRaw, $kg)) {
                        $kiriRaw = trim($kg[1]);
                        $kiriGambar = $this->saveImageFromFolder(trim($kg[2]));
                    }
                    if (preg_match('/^(.*?)\s*\|\s*gambar:\s*(.+)$/i', $kananRaw, $kg)) {
                        $kananRaw = trim($kg[1]);
                        $kananGambar = $this->saveImageFromFolder(trim($kg[2]));
                    }

                    $current['pasangan'][] = [
                        'kiri'         => $kiriRaw,
                        'kiri_gambar'  => $kiriGambar,
                        'kanan'        => $kananRaw,
                        'kanan_gambar' => $kananGambar,
                    ];

                // Jawaban line
                } elseif ($current && preg_match('/^(Jawaban|Kunci)\s*:\s*(.*)/i', $text, $m)) {
                    $jawaban = trim($m[2]);
                    if ($jawaban && !preg_match('/^\(.*\)$/', $jawaban)) {
                        $current['kunci'] = $jawaban;
                    }

                // Meta tag lines: [tingkat: ...] or [bobot: ...] as standalone line
                } elseif ($current && preg_match('/^\[tingkat:\s*(mudah|sedang|sulit)\]/i', $text, $tm)) {
                    $current['tingkat'] = strtolower(trim($tm[1]));
                } elseif ($current && preg_match('/^\[bobot:\s*([\d.,]+)\]/i', $text, $bm)) {
                    $current['bobot'] = (float) str_replace(',', '.', trim($bm[1]));

                // Standalone image following a soal (no text, just image)
                } elseif ($current && empty($text) && !empty($images) && !$current['gambar_soal']) {
                    $current['gambar_soal'] = $this->saveImageData($images[0]);
                }
            }
        }

        if ($current) $blocks[] = $current;

        // Auto-detect jenis for blocks without tags
        foreach ($blocks as &$block) {
            if ($block['jenis'] === 'pg' && empty($block['opsi']) && empty($block['pasangan'])) {
                $block['jenis'] = 'essay';
            }
        }

        return $blocks;
    }

    /**
     * Extract text and images from a Word element (TextRun, Text, ListItem, etc.)
     */
    private function extractElementContent($element): ?array
    {
        $text   = '';
        $images = [];

        if ($element instanceof TextRun) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $text .= $child->getText();
                } elseif ($child instanceof Image) {
                    $images[] = $child;
                }
            }
        } elseif ($element instanceof Text) {
            $text = $element->getText();
        } elseif ($element instanceof ListItem || $element instanceof ListItemRun) {
            if (method_exists($element, 'getText')) {
                $text = $element->getText();
            }
        } elseif ($element instanceof Image) {
            $images[] = $element;
        } elseif (method_exists($element, 'getText')) {
            $text = $element->getText();
        }

        $text = trim((string) $text);

        return ['text' => $text, 'images' => $images];
    }

    /**
     * Save a PhpWord Image element to storage and return the path.
     */
    private function saveImageData(Image $image): ?string
    {
        try {
            $imageString = $image->getImageString();
            if (empty($imageString)) return null;

            $ext  = $image->getImageExtension() ?: 'png';
            $uuid = Str::uuid();
            $dest = "soal/gambar/{$uuid}.{$ext}";

            Storage::disk('public')->put($dest, $imageString);

            return $dest;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save image from the ZIP images folder to storage.
     */
    private function saveImageFromFolder(string $filename): ?string
    {
        if (!$this->imagesPath) return null;

        $sourcePath = rtrim($this->imagesPath, '/\\') . '/' . $filename;

        if (!file_exists($sourcePath)) return null;

        $ext  = pathinfo($filename, PATHINFO_EXTENSION) ?: 'png';
        $name = 'soal/gambar/' . Str::uuid() . '.' . $ext;

        Storage::disk('public')->put($name, file_get_contents($sourcePath));

        return $name;
    }

    private function processBlock(array $block): array
    {
        if (empty($block['pertanyaan'])) {
            throw new \Exception('Pertanyaan tidak ditemukan');
        }

        $kategoriId = $this->importJob->meta['kategori_soal_id'] ?? null;

        $soal = Soal::create([
            'kategori_id'       => $kategoriId,
            'sekolah_id'        => $this->importJob->sekolah_id,
            'created_by'        => $this->importJob->created_by,
            'tipe_soal'         => $block['jenis'],
            'pertanyaan'        => $block['pertanyaan'],
            'gambar_soal'       => $block['gambar_soal'],
            'posisi_gambar'     => $block['gambar_soal'] ? 'bawah' : null,
            'tingkat_kesulitan' => $block['tingkat'] ?? 'sedang',
            'bobot'             => $block['bobot'] ?? 1.0,
        ]);

        $now = now();
        $result = ['opsi' => [], 'pasangan' => []];

        match ($block['jenis']) {
            'pg', 'pg_kompleks' => $result['opsi'] = $this->buildOpsiBatch($soal->id, $block, $now),
            'benar_salah'       => $result['opsi'] = $this->buildBenarSalahBatch($soal->id, $block, $now),
            'menjodohkan'       => $result['pasangan'] = $this->buildPasanganBatch($soal->id, $block, $now),
            'isian', 'essay'    => $result['opsi'] = $this->buildIsianBatch($soal->id, $block, $now),
            default             => null,
        };

        return $result;
    }

    private function buildOpsiBatch(string $soalId, array $block, $now): array
    {
        $kunciStr = strtoupper(str_replace(' ', '', (string) ($block['kunci'] ?? '')));
        $kunciArr = $kunciStr ? explode(',', $kunciStr) : [];

        $batch = [];
        $i = 0;
        foreach ($block['opsi'] as $label => $teks) {
            $gambar = $block['opsi_gambar'][$label] ?? null;

            $batch[] = [
                'id'         => Str::orderedUuid()->toString(),
                'soal_id'    => $soalId,
                'label'      => $label,
                'teks'       => $teks ?: null,
                'gambar'     => $gambar,
                'is_benar'   => in_array($label, $kunciArr),
                'urutan'     => $i++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $batch;
    }

    private function buildPasanganBatch(string $soalId, array $block, $now): array
    {
        $batch = [];
        foreach ($block['pasangan'] as $i => $pair) {
            $batch[] = [
                'id'            => Str::orderedUuid()->toString(),
                'soal_id'       => $soalId,
                'kiri_teks'     => $pair['kiri'],
                'kiri_gambar'   => $pair['kiri_gambar'] ?? null,
                'kanan_teks'    => $pair['kanan'],
                'kanan_gambar'  => $pair['kanan_gambar'] ?? null,
                'urutan'        => $i,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        return $batch;
    }

    private function buildBenarSalahBatch(string $soalId, array $block, $now): array
    {
        $batch = [];
        $pernyataanList = $block['pernyataan_bs'] ?? [];
        foreach ($pernyataanList as $i => $item) {
            $batch[] = [
                'id'         => Str::orderedUuid()->toString(),
                'soal_id'    => $soalId,
                'label'      => (string) ($i + 1),
                'teks'       => $item['teks'],
                'gambar'     => null,
                'is_benar'   => $item['benar'],
                'urutan'     => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $batch;
    }

    private function buildIsianBatch(string $soalId, array $block, $now): array
    {
        $kunci = $block['kunci'] ?? null;
        if (!$kunci) return [];

        return [[
            'id'         => Str::orderedUuid()->toString(),
            'soal_id'    => $soalId,
            'label'      => 'KUNCI',
            'teks'       => $kunci,
            'gambar'     => null,
            'is_benar'   => true,
            'urutan'     => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]];
    }

    private function cleanupDirectory(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
