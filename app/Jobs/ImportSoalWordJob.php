<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Soal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Utilities\DocxMathPreprocessor;
use App\Utilities\DocxImagePreprocessor;
use App\Utilities\DocxImageCropExtractor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Formula;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Style\Font;

/**
 * Import soal from Word document.
 *
 * Optimized: processes blocks in chunks with batch insert for
 * opsi_jawaban and pasangan_soal instead of individual creates.
 */

class ImportSoalWordJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    /**
     * Unique lock timeout — must exceed $timeout to prevent
     * the queue from re-dispatching while still processing.
     */
    public int $uniqueFor = 900;

    private const CHUNK_SIZE = 50;

    /**
     * Map of image paths inside DOCX => crop percentages {l, t, r, b}.
     * Populated before PHPWord loads so saveImageData() can apply crops.
     */
    private array $imageCropMap = [];

    public function __construct(
        public ImportJob $importJob,
        public ?string $imagesPath = null
    ) {
        $this->onQueue('imports');
    }

    /**
     * Unique ID to prevent duplicate job processing.
     */
    public function uniqueId(): string
    {
        return 'import-soal-' . $this->importJob->id;
    }

    public function handle(): void
    {
        $this->importJob->refresh();
        if ($this->importJob->status === 'gagal') {
            return;
        }

        // Guard against duplicate processing (e.g. queue retry_after race condition)
        if ($this->importJob->status === 'processing' || $this->importJob->status === 'selesai') {
            return;
        }

        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        $preprocessedPath = null;

        try {
            $filePath = Storage::disk('local')->path($this->importJob->filepath);

            // Pre-process DOCX to convert OMML math formulas to LaTeX text
            // before PHPWord loads it. This prevents phpoffice/math from
            // crashing on complex math (m:f, m:sSup, m:rad, etc.).
            $preprocessor = new DocxMathPreprocessor();
            $loadPath = $preprocessor->preprocess($filePath);
            if ($loadPath !== $filePath) {
                $preprocessedPath = $loadPath;
            }

            // Replace unsupported image formats (EMF, WMF, TIFF) with PNG placeholders
            // to prevent PHPWord's InvalidImageException during loading.
            $imagePreprocessor = new DocxImagePreprocessor();
            $replacedImages = $imagePreprocessor->process($loadPath);
            if (!empty($replacedImages)) {
                $this->importJob->update([
                    'catatan' => 'Gambar format tidak didukung (' . implode(', ', array_map('basename', $replacedImages)) . ') dilewati.',
                ]);
            }

            // Extract image crop metadata before PHPWord loads.
            // Word stores cropped images as full originals + crop percentages
            // in XML. PHPWord ignores crops, so we apply them via GD later.
            $cropExtractor = new DocxImageCropExtractor();
            $this->imageCropMap = $cropExtractor->extract($filePath);

            $previousUseErrors = libxml_use_internal_errors(true);
            set_error_handler(fn () => true, E_WARNING);
            try {
                $phpWord = IOFactory::load($loadPath);
            } finally {
                restore_error_handler();
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseErrors);
            }

            $sections   = $phpWord->getSections();
            $parseResult = $this->parseWordSections($sections);
            $soalBlocks = $parseResult['blocks'];
            $narasiList = $parseResult['narasi_list'];

            $this->importJob->update(['total_rows' => count($soalBlocks)]);

            // Create NarasiSoal records for each detected narasi block
            $narasiIdMap = []; // index => narasi_soal id
            if (!empty($narasiList)) {
                $kategoriId = $this->importJob->meta['kategori_soal_id'] ?? null;
                foreach ($narasiList as $idx => $narasiData) {
                    $narasi = \App\Models\NarasiSoal::create([
                        'kategori_id' => $kategoriId,
                        'sekolah_id'  => $this->importJob->sekolah_id,
                        'created_by'  => $this->importJob->created_by,
                        'judul'       => $narasiData['judul'],
                        'konten'      => $narasiData['konten'],
                        'is_active'   => true,
                    ]);
                    $narasiIdMap[$idx] = $narasi->id;
                }
            }

            // Compute urutan_dalam_narasi for each block in its narasi group
            $narasiOrderCounters = [];
            foreach ($soalBlocks as &$blk) {
                $ni = $blk['narasi_index'] ?? null;
                if ($ni !== null) {
                    if (!isset($narasiOrderCounters[$ni])) $narasiOrderCounters[$ni] = 0;
                    $narasiOrderCounters[$ni]++;
                    $blk['_urutan_narasi'] = $narasiOrderCounters[$ni];
                }
            }
            unset($blk);

            $errors  = [];
            $success = 0;

            // Process in chunks instead of one giant transaction
            $chunks = array_chunk($soalBlocks, self::CHUNK_SIZE, true);

            foreach ($chunks as $chunk) {
                $chunkSuccess = 0;
                $chunkErrors = [];

                DB::transaction(function () use ($chunk, &$chunkErrors, &$chunkSuccess, $narasiIdMap) {
                    $soalBatch     = [];
                    $opsiBatch     = [];
                    $pasanganBatch = [];

                    foreach ($chunk as $index => $block) {
                        try {
                            $result = $this->processBlock($block, $narasiIdMap);
                            $soalBatch[] = $result['soal'];
                            if (!empty($result['opsi'])) {
                                array_push($opsiBatch, ...$result['opsi']);
                            }
                            if (!empty($result['pasangan'])) {
                                array_push($pasanganBatch, ...$result['pasangan']);
                            }
                            $chunkSuccess++;
                        } catch (\Exception $e) {
                            $chunkErrors[] = "Soal " . ($index + 1) . ": " . $e->getMessage();
                        }
                    }

                    // Bulk insert soal
                    if (!empty($soalBatch)) {
                        foreach (array_chunk($soalBatch, 50) as $subChunk) {
                            Soal::insert($subChunk);
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

                $success += $chunkSuccess;
                array_push($errors, ...$chunkErrors);
                // Update progress per chunk
                $lastIndex = array_key_last($chunk);
                $this->importJob->update(['processed_rows' => $lastIndex + 1]);
            }

            // Clean up extracted images folder
            if ($this->imagesPath && is_dir($this->imagesPath)) {
                $this->cleanupDirectory($this->imagesPath);
            }

            // Clean up preprocessed temp file
            if ($preprocessedPath && file_exists($preprocessedPath)) {
                @unlink($preprocessedPath);
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
            // Clean up preprocessed temp file on error
            if ($preprocessedPath && file_exists($preprocessedPath)) {
                @unlink($preprocessedPath);
            }

            $this->importJob->update([
                'status'       => 'gagal',
                'catatan'      => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->importJob->refresh();
        if ($this->importJob->status === 'processing') {
            $this->importJob->update([
                'status'       => 'gagal',
                'catatan'      => 'Job gagal/timeout: ' . $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Parse Word document sections into structured soal blocks.
     */
    private function parseWordSections(array $sections): array
    {
        $blocks  = [];
        $current = null;
        $listCounters = [];      // [key] => current count for numbered list reconstruction
        $pendingListItems = [];  // accumulated list items (bullet/decimal-content) for HTML list wrapping
        $soalNumId = null;       // num_id of the decimal numbering used for soal numbers (auto-detected)

        // Narasi tracking
        $narasiList = [];        // [{judul, konten}] — collected narasi blocks
        $inNarasi = false;       // true while inside [NARASI]...[/NARASI]
        $currentNarasiContent = '';
        $currentNarasiIndex = null; // index into $narasiList for soal association
        $soalIndex = 0;          // global import order counter

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                $result = $this->extractElementContent($element);
                if (!$result) continue;

                $text   = $result['text'];
                $html   = $result['html'];
                $images = $result['images'];
                $isTable = $result['is_table'] ?? false;
                $listInfo = $result['list_info'] ?? null;
                $alignment = $result['alignment'] ?? null;

                // --- Narasi tag detection ---
                $plainText = trim($text);
                if (preg_match('/^\[NARASI\]\s*$/i', $plainText)) {
                    $inNarasi = true;
                    $currentNarasiContent = '';
                    continue;
                }
                if (preg_match('/^\[\/NARASI\]\s*$/i', $plainText)) {
                    if ($inNarasi && !empty(trim($currentNarasiContent))) {
                        $narasiList[] = [
                            'judul'  => 'Narasi ' . (count($narasiList) + 1),
                            'konten' => trim($currentNarasiContent),
                        ];
                        $currentNarasiIndex = count($narasiList) - 1;
                    }
                    $inNarasi = false;
                    $currentNarasiContent = '';
                    continue;
                }
                // Also handle inline: [NARASI] content text [/NARASI]
                if (preg_match('/^\[NARASI\]\s*(.+?)\s*\[\/NARASI\]\s*$/is', $plainText, $nm)) {
                    $narasiList[] = [
                        'judul'  => 'Narasi ' . (count($narasiList) + 1),
                        'konten' => trim(!empty($html) ? preg_replace('/\[NARASI\]\s*/i', '', preg_replace('/\s*\[\/NARASI\]/i', '', $html)) : e($nm[1])),
                    ];
                    $currentNarasiIndex = count($narasiList) - 1;
                    continue;
                }
                if ($inNarasi) {
                    // Accumulate narasi content as HTML
                    $contentToAdd = !empty($html) ? $html : e($text);
                    if (!empty($contentToAdd)) {
                        $currentNarasiContent .= '<p>' . $contentToAdd . '</p>';
                    }
                    continue;
                }

                // --- List numbering reconstruction ---
                // PHPWord strips numbering prefixes (1., A., bullets) from ListItem elements.
                // We auto-detect the soal-numbering num_id (first decimal list encountered)
                // and only allow THAT list to trigger soal-number detection ("N. " prefix).
                // Other decimal lists (e.g. benar/salah "1)", sub-numbering) are handled as
                // content: parenthesis-style get "N) " for benar/salah parsing, others as <ol>.
                // Letter items (upperLetter/lowerLetter) get prefix reconstruction for option detection.
                // Bullet items are accumulated as <ul><li>.

                // Determine if this list item should be accumulated (not flow through normal parsing)
                $isContentList = false;
                if ($listInfo && $listInfo['format'] !== 'unknown') {
                    if ($listInfo['format'] === 'bullet') {
                        $isContentList = true;
                    } elseif ($listInfo['format'] === 'decimal') {
                        // Auto-detect soal numbering: the first decimal list at depth 0
                        // with period format (e.g. "%1.") becomes the soal numbering source
                        if ($soalNumId === null) {
                            $textPattern = $listInfo['text'] ?? '';
                            // Soal numbering uses period: "%1." or empty (default is period)
                            $isPeriodFormat = empty($textPattern) || str_contains($textPattern, '.');
                            if ($isPeriodFormat && $listInfo['depth'] === 0) {
                                $soalNumId = $listInfo['num_id'];
                            }
                        }

                        if ($listInfo['num_id'] === $soalNumId && $soalNumId !== null) {
                            // This IS a soal number — reconstruct "N. " prefix
                            // Let it flow through to normal parsing (soal detection regex)
                            $numKey = $soalNumId . '_soal';
                            if (!isset($listCounters[$numKey])) $listCounters[$numKey] = 0;
                            $listCounters[$numKey]++;
                            $counter = $listCounters[$numKey];
                            $prefix = $counter . '. ';
                            $text = $prefix . $text;
                            $html = e($prefix) . $html;
                            // NOT accumulated — flows through to soal detection below
                        } else {
                            // Different decimal numbering — check if parenthesis style for benar/salah
                            $textPattern = $listInfo['text'] ?? '';
                            if (str_contains($textPattern, ')')) {
                                // Parenthesis format "1)" — reconstruct "N) " for benar/salah detection
                                $numKey = ($listInfo['num_id'] ?: 'x') . '_decimal_paren_' . $listInfo['depth'];
                                if (!isset($listCounters[$numKey])) $listCounters[$numKey] = 0;
                                $listCounters[$numKey]++;
                                $counter = $listCounters[$numKey];
                                $prefix = $counter . ') ';
                                $text = $prefix . $text;
                                $html = e($prefix) . $html;
                                // Flows through to benar/salah regex below
                            } else {
                                // Other decimal sub-lists — accumulate as <ol> content
                                $isContentList = true;
                            }
                        }
                    }
                }

                // Flush pending content-list items when hitting a non-content-list element
                if (!empty($pendingListItems) && !$isContentList) {
                    if ($current) {
                        $firstFormat = $pendingListItems[0]['format'] ?? 'bullet';
                        $isOrdered = ($firstFormat === 'decimal');
                        $tag = $isOrdered ? 'ol' : 'ul';
                        $style = $isOrdered
                            ? 'margin:0.3em 0;padding-left:1.5em;'
                            : 'list-style-type:disc;margin:0.3em 0;padding-left:1.5em;';
                        $listHtml = '<' . $tag . ' style="' . $style . '">';
                        foreach ($pendingListItems as $li) {
                            $listHtml .= '<li>' . ($li['html'] ?: e($li['text'])) . '</li>';
                        }
                        $listHtml .= '</' . $tag . '>';
                        if (!empty($current['opsi'])) {
                            $lastLabel = array_key_last($current['opsi']);
                            $current['opsi_html'][$lastLabel] = ($current['opsi_html'][$lastLabel] ?? '') . $listHtml;
                        } else {
                            $current['pertanyaan'] .= $listHtml;
                            $current['pertanyaan_html'] = ($current['pertanyaan_html'] ?? '') . $listHtml;
                        }
                    }
                    $pendingListItems = [];
                }

                // Accumulate bullet/decimal-content items
                if ($isContentList) {
                    if (!empty($text) || !empty($html)) {
                        $pendingListItems[] = [
                            'text' => $text,
                            'html' => !empty($html) ? $html : e($text),
                            'images' => $images,
                            'format' => $listInfo['format'],
                        ];
                    }
                    continue;
                }

                // Letter list — reconstruct prefix so option regex (A./B./C./D.) matches
                if ($listInfo && in_array($listInfo['format'], ['upperLetter', 'lowerLetter'])) {
                    $numKey = ($listInfo['num_id'] ?: 'x') . '_' . $listInfo['format'] . '_' . $listInfo['depth'];
                    if (!isset($listCounters[$numKey])) {
                        $listCounters[$numKey] = 0;
                    }
                    $listCounters[$numKey]++;
                    $counter = $listCounters[$numKey];

                    $prefix = match ($listInfo['format']) {
                        'lowerLetter' => chr(96 + min($counter, 26)) . '. ',
                        'upperLetter' => chr(64 + min($counter, 26)) . '. ',
                        default => '',
                    };

                    $text = $prefix . $text;
                    $html = e($prefix) . $html;
                }

                if (empty($text) && empty($html) && empty($images)) continue;

                // Tables are appended to current soal's pertanyaan as HTML
                if ($isTable && $current && !empty($html)) {
                    $current['pertanyaan'] .= "\n" . $html;
                    $current['pertanyaan_html'] = ($current['pertanyaan_html'] ?? '') . $html;
                    continue;
                }

                // Skip known heading-only lines
                if (preg_match('/^(PILIHAN GANDA|PG KOMPLEKS|MENJODOHKAN|ISIAN SINGKAT|ESSAY|BENAR|CATATAN|GAMBAR|Template Import)/i', $text)) continue;

                // Choose the richer representation for storage
                // Use plain text for regex matching, html for storage
                $storeText = !empty($html) ? $html : e($text);

                // New soal: starts with number.
                if (preg_match('/^(\d+)\.\s+(.+)/s', $text, $m)) {
                    if ($current) $blocks[] = $current;

                    $soalText = trim($m[2]);
                    // Extract corresponding html portion (remove the "N. " prefix)
                    $soalHtml = $html;
                    if (!empty($soalHtml)) {
                        // Remove leading "N. " from html (may be wrapped in tags)
                        $soalHtml = preg_replace('/^(?:<[^>]+>)*\d+\.\s*/', '', $soalHtml, 1);
                        $soalHtml = trim($soalHtml);
                    }
                    if (empty($soalHtml)) $soalHtml = e($soalText);

                    $jenis = 'pg';

                    // Detect tag from plain text
                    if (preg_match('/^\[(PG_KOMPLEKS|MENJODOHKAN|ISIAN|ESSAY|BENAR_SALAH)\]\s*(.+)/si', $soalText, $tagMatch)) {
                        $tag = strtoupper($tagMatch[1]);
                        $soalText = trim($tagMatch[2]);
                        // Also strip tag from html
                        $soalHtml = preg_replace('/\[(?:PG_KOMPLEKS|MENJODOHKAN|ISIAN|ESSAY|BENAR_SALAH)\]\s*/i', '', $soalHtml, 1);
                        $soalHtml = trim($soalHtml);
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
                        $soalHtml = preg_replace('/\[gambar:\s*.+?\]/i', '', $soalHtml);
                        $gambarFromText = $this->saveImageFromFolder(trim($gm[1]));
                    }

                    // Parse optional meta tags
                    $tingkat = 'sedang';
                    $bobot   = 1.0;
                    if (preg_match('/\[tingkat:\s*(mudah|sedang|sulit)\]/i', $soalText, $tm)) {
                        $tingkat = strtolower(trim($tm[1]));
                        $soalText = trim(preg_replace('/\[tingkat:\s*(mudah|sedang|sulit)\]/i', '', $soalText));
                        $soalHtml = preg_replace('/\[tingkat:\s*(?:mudah|sedang|sulit)\]/i', '', $soalHtml);
                    }
                    if (preg_match('/\[bobot:\s*([\d.,]+)\]/i', $soalText, $bm)) {
                        $bobot = (float) str_replace(',', '.', trim($bm[1]));
                        $soalText = trim(preg_replace('/\[bobot:\s*[\d.,]+\]/i', '', $soalText));
                        $soalHtml = preg_replace('/\[bobot:\s*[\d.,]+\]/i', '', $soalHtml);
                    }

                    // If images are already inlined in the HTML (as <img> tags),
                    // don't also set gambar_soal — this prevents double rendering.
                    $hasInlineImage = !empty($images) && str_contains($soalHtml, '<img ');
                    $gambarSoal = $hasInlineImage ? null : (!empty($images) ? $this->saveImageData($images[0]) : $gambarFromText);

                    $current = [
                        'pertanyaan'      => trim($soalHtml),
                        'pertanyaan_html' => trim($soalHtml),
                        'jenis'           => $jenis,
                        'opsi'            => [],
                        'opsi_html'       => [],
                        'opsi_gambar'     => [],
                        'kunci'           => null,
                        'gambar_soal'     => $gambarSoal,
                        'pasangan'        => [],
                        'pernyataan_bs'   => [],
                        'tingkat'         => $tingkat,
                        'bobot'           => $bobot,
                        'narasi_index'    => $currentNarasiIndex,
                        'nomor_urut_import' => $soalIndex,
                    ];
                    $soalIndex++;

                    // Reset non-soal list counters (options, benar/salah) per new soal
                    // Keep soal counter intact
                    foreach (array_keys($listCounters) as $key) {
                        if (!str_ends_with($key, '_soal')) {
                            unset($listCounters[$key]);
                        }
                    }

                // Benar/Salah pernyataan lines
                } elseif ($current && $current['jenis'] === 'benar_salah' && preg_match('/^(\d+)\)\s*(.+?)\s*\((BENAR|SALAH)\)\s*$/i', $text, $m)) {
                    // Extract html version of pernyataan text
                    $bsHtml = $html;
                    // Remove leading "N) " prefix (may be wrapped in tags)
                    $bsHtml = preg_replace('/^(?:<[^>]+>)*\d+\)\s*/', '', $bsHtml, 1);
                    // Remove trailing (BENAR) or (SALAH) — may be wrapped in bold/italic tags
                    $bsHtml = preg_replace('/\s*(?:<[^>]*>)*\s*\((?:BENAR|SALAH)\)\s*(?:<[^>]*>)*\s*$/i', '', $bsHtml);
                    $bsHtml = trim($bsHtml);
                    if (empty($bsHtml)) $bsHtml = e(trim($m[2]));

                    $current['pernyataan_bs'][] = [
                        'teks'  => $bsHtml,
                        'benar' => strtoupper($m[3]) === 'BENAR',
                    ];

                // Option lines: a. / b. / c. / d. / e.
                } elseif ($current && preg_match('/^([a-eA-E])\.\s*(.*)/s', $text, $m)) {
                    $label    = strtoupper($m[1]);
                    $opsiText = trim($m[2]);
                    // Extract html version (strip "A. " prefix)
                    $opsiHtml = $html;
                    $opsiHtml = preg_replace('/^(?:<[^>]+>)*[a-eA-E]\.\s*/', '', $opsiHtml, 1);
                    $opsiHtml = trim($opsiHtml);
                    if (empty($opsiHtml)) $opsiHtml = e($opsiText);

                    // Parse text-based image reference
                    if (preg_match('/^(.*?)\s*\|\s*gambar:\s*(.+)$/i', $opsiText, $gm)) {
                        $opsiText = trim($gm[1]);
                        $opsiHtml = preg_replace('/\s*\|\s*gambar:\s*.+$/i', '', $opsiHtml);
                        $imgFile  = trim($gm[2]);
                        $savedPath = $this->saveImageFromFolder($imgFile);
                        if ($savedPath) {
                            $current['opsi_gambar'][$label] = $savedPath;
                        }
                    } elseif (preg_match('/^gambar:\s*(.+)$/i', $opsiText, $gm)) {
                        $opsiText = '';
                        $opsiHtml = '';
                        $imgFile  = trim($gm[1]);
                        $savedPath = $this->saveImageFromFolder($imgFile);
                        if ($savedPath) {
                            $current['opsi_gambar'][$label] = $savedPath;
                        }
                    }

                    $current['opsi'][$label] = $opsiText;
                    $current['opsi_html'][$label] = $opsiHtml;

                    // Embedded image in this option paragraph
                    if (!empty($images) && empty($current['opsi_gambar'][$label])) {
                        $current['opsi_gambar'][$label] = $this->saveImageData($images[0]);
                    }

                    // Ensure opsi entry exists even if text is empty
                    if (empty($opsiText) && !empty($current['opsi_gambar'][$label])) {
                        $current['opsi'][$label] = '';
                        $current['opsi_html'][$label] = '';
                    }

                // Menjodohkan: "kiri = kanan"
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

                // Meta tag lines
                } elseif ($current && preg_match('/^\[tingkat:\s*(mudah|sedang|sulit)\]/i', $text, $tm)) {
                    $current['tingkat'] = strtolower(trim($tm[1]));
                } elseif ($current && preg_match('/^\[bobot:\s*([\d.,]+)\]/i', $text, $bm)) {
                    $current['bobot'] = (float) str_replace(',', '.', trim($bm[1]));

                // Standalone image following a soal
                } elseif ($current && empty($text) && !empty($images)) {
                    if (!empty($current['opsi'])) {
                        $lastLabel = array_key_last($current['opsi']);
                        if (empty($current['opsi_gambar'][$lastLabel] ?? null)) {
                            // Assign image to last option if it doesn't have one yet
                            $current['opsi_gambar'][$lastLabel] = $this->saveImageData($images[0]);
                        } else {
                            // Last option already has image — create new option with image
                            $nextLabel = chr(ord($lastLabel) + 1);
                            if ($nextLabel >= 'A' && $nextLabel <= 'Z') {
                                $current['opsi'][$nextLabel] = '';
                                $current['opsi_html'][$nextLabel] = '';
                                $current['opsi_gambar'][$nextLabel] = $this->saveImageData($images[0]);
                            }
                        }
                    } elseif (!$current['gambar_soal'] && !str_contains($current['pertanyaan'], '<img ')) {
                        $current['gambar_soal'] = $this->saveImageData($images[0]);
                    } elseif (!empty($html)) {
                        // gambar_soal already set or pertanyaan has inline images — append as inline
                        $pStyle = '';
                        if ($alignment === 'center') {
                            $pStyle = ' style="text-align:center"';
                        }
                        $imgBlock = '<p' . $pStyle . '>' . $html . '</p>';
                        $current['pertanyaan'] .= $imgBlock;
                        $current['pertanyaan_html'] = ($current['pertanyaan_html'] ?? '') . $imgBlock;
                    }

                // Continuation text (no structural prefix)
                } elseif ($current && !empty($html) && !empty($text)) {
                    if (!empty($current['opsi'])) {
                        $lastLabel = array_key_last($current['opsi']);
                        if (empty($current['opsi'][$lastLabel]) && empty($current['opsi_gambar'][$lastLabel] ?? null)) {
                            // Last option label was parsed but has no content yet
                            // (block-level formula in separate paragraph) — fill it
                            $current['opsi'][$lastLabel] = trim($text);
                            $current['opsi_html'][$lastLabel] = $html;
                        } else {
                            // Last option already has content — create a new option
                            // with the next sequential label (handles formula-only
                            // paragraphs that lost their option label in Word)
                            $nextLabel = chr(ord($lastLabel) + 1);
                            if ($nextLabel >= 'A' && $nextLabel <= 'Z') {
                                $current['opsi'][$nextLabel] = trim($text);
                                $current['opsi_html'][$nextLabel] = $html;
                            }
                        }
                    } elseif (!empty($current['pernyataan_bs'])) {
                        $lastIdx = count($current['pernyataan_bs']) - 1;
                        if (empty($current['pernyataan_bs'][$lastIdx]['teks'])) {
                            $current['pernyataan_bs'][$lastIdx]['teks'] = $html;
                        } else {
                            $current['pernyataan_bs'][$lastIdx]['teks'] .= ' ' . $html;
                        }
                    } else {
                        // No options yet — append to pertanyaan as new paragraph
                        // Use <p> tags with alignment instead of <br> for proper paragraph breaks
                        $pStyle = '';
                        if ($alignment && !in_array($alignment, ['start', 'left'])) {
                            $cssAlign = match ($alignment) {
                                'center' => 'center',
                                'end', 'right' => 'right',
                                'both', 'justify' => 'justify',
                                default => '',
                            };
                            if ($cssAlign) $pStyle = ' style="text-align:' . $cssAlign . '"';
                        }
                        $wrappedHtml = '<p' . $pStyle . '>' . $html . '</p>';
                        $current['pertanyaan'] .= $wrappedHtml;
                        $current['pertanyaan_html'] = ($current['pertanyaan_html'] ?? '') . $wrappedHtml;
                    }
                }
            }
        }

        // Flush any remaining pending list items (bullets/decimals)
        if (!empty($pendingListItems) && $current) {
            $firstFormat = $pendingListItems[0]['format'] ?? 'bullet';
            $isOrdered = ($firstFormat === 'decimal');
            $tag = $isOrdered ? 'ol' : 'ul';
            $style = $isOrdered
                ? 'margin:0.3em 0;padding-left:1.5em;'
                : 'list-style-type:disc;margin:0.3em 0;padding-left:1.5em;';
            $listHtml = '<' . $tag . ' style="' . $style . '">';
            foreach ($pendingListItems as $li) {
                $listHtml .= '<li>' . ($li['html'] ?: e($li['text'])) . '</li>';
            }
            $listHtml .= '</' . $tag . '>';
            if (!empty($current['opsi'])) {
                $lastLabel = array_key_last($current['opsi']);
                $current['opsi_html'][$lastLabel] = ($current['opsi_html'][$lastLabel] ?? '') . $listHtml;
            } else {
                $current['pertanyaan'] .= $listHtml;
                $current['pertanyaan_html'] = ($current['pertanyaan_html'] ?? '') . $listHtml;
            }
        }

        // Reset narasi association when a standalone soal (no narasi tag before it) is encountered
        // This happens when soal don't have a preceding [NARASI] block.
        // The $currentNarasiIndex persists until a new [NARASI]...[/NARASI] block or end of file.

        if ($current) $blocks[] = $current;

        // Auto-detect jenis for blocks without tags
        foreach ($blocks as &$block) {
            if ($block['jenis'] === 'pg' && empty($block['opsi']) && empty($block['pasangan'])) {
                $block['jenis'] = 'essay';
            }
        }

        return ['blocks' => $blocks, 'narasi_list' => $narasiList];
    }

    /**
     * Extract text, rich HTML, and images from a Word element.
     *
     * Returns ['text' => plain, 'html' => rich HTML, 'images' => Image[]]
     * - 'text': plain text for structural regex matching (soal number, option label, etc.)
     * - 'html': rich HTML with bold/italic/color/underline/inline-images for storage
     * - 'images': standalone Image objects (for gambar_soal fallback)
     */
    private function extractElementContent($element): ?array
    {
        $text   = '';
        $html   = '';
        $images = [];
        $listInfo = null;
        $alignment = null;

        if ($element instanceof Table) {
            $tableHtml = $this->tableToHtml($element);
            return ['text' => '', 'html' => $tableHtml, 'images' => [], 'is_table' => true];
        }

        // Extract paragraph alignment (center, right, justify, etc.)
        if (method_exists($element, 'getParagraphStyle')) {
            $pStyle = $element->getParagraphStyle();
            if ($pStyle && is_object($pStyle) && method_exists($pStyle, 'getAlignment')) {
                $align = $pStyle->getAlignment();
                if ($align && !in_array($align, ['left', 'start', ''])) {
                    $alignment = match ($align) {
                        'center' => 'center',
                        'right', 'end' => 'right',
                        'both', 'justify' => 'justify',
                        default => null,
                    };
                }
            }
        }

        // IMPORTANT: ListItemRun extends TextRun, so check ListItem/ListItemRun FIRST
        if ($element instanceof ListItem || $element instanceof ListItemRun) {
            $listInfo = $this->getListInfo($element);
            if ($element instanceof ListItemRun) {
                foreach ($element->getElements() as $child) {
                    if ($child instanceof Text) {
                        $childText = $child->getText() ?? '';
                        $text .= $childText;
                        $html .= $this->textToHtml($childText, $child->getFontStyle());
                    } elseif ($child instanceof Image) {
                        $images[] = $child;
                        $savedPath = $this->saveImageData($child);
                        if ($savedPath) {
                            $url = Storage::disk('public')->url($savedPath);
                            $html .= '<img src="' . e($url) . '" alt="gambar" style="max-width:100%;vertical-align:middle;">';
                        }
                    } elseif ($child instanceof Formula) {
                        $latex = $this->formulaToLatex($child);
                        $text .= $latex;
                        $html .= e($latex);
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $text = $element->getText() ?? '';
                $html = e($text);
            }
        } elseif ($element instanceof TextRun) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $childText = $child->getText() ?? '';
                    $text .= $childText;
                    $html .= $this->textToHtml($childText, $child->getFontStyle());
                } elseif ($child instanceof Image) {
                    $images[] = $child;
                    $savedPath = $this->saveImageData($child);
                    if ($savedPath) {
                        $url = Storage::disk('public')->url($savedPath);
                        $imgStyle = 'max-width:100%;vertical-align:middle;';
                        if ($alignment === 'center') {
                            $imgStyle .= 'display:block;margin:0 auto;';
                        }
                        $html .= '<img src="' . e($url) . '" alt="gambar" style="' . $imgStyle . '">';
                    }
                } elseif ($child instanceof Formula) {
                    $latex = $this->formulaToLatex($child);
                    $text .= $latex;
                    $html .= e($latex);
                }
            }
        } elseif ($element instanceof Text) {
            $text = $element->getText() ?? '';
            $html = $this->textToHtml($text, $element->getFontStyle());
        } elseif ($element instanceof Formula) {
            $latex = $this->formulaToLatex($element);
            $text = $latex;
            $html = e($latex);
        } elseif ($element instanceof Image) {
            $images[] = $element;
            $savedPath = $this->saveImageData($element);
            if ($savedPath) {
                $url = Storage::disk('public')->url($savedPath);
                $imgStyle = 'max-width:100%;';
                if ($alignment === 'center') {
                    $imgStyle .= 'display:block;margin:0 auto;';
                }
                $html = '<img src="' . e($url) . '" alt="gambar" style="' . $imgStyle . '">';
            }
        } elseif (method_exists($element, 'getText')) {
            $text = $element->getText() ?? '';
            $html = e($text);
        }

        $text = html_entity_decode(trim((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = trim($html);

        return ['text' => $text, 'html' => $html, 'images' => $images, 'is_table' => false, 'list_info' => $listInfo, 'alignment' => $alignment];
    }

    /**
     * Extract list metadata (depth, format, numId) from a ListItem/ListItemRun element.
     *
     * Used to reconstruct numbering prefixes that PHPWord strips from list items.
     * Word stores numbering as style metadata, not inline text.
     */
    private function getListInfo($element): ?array
    {
        if (!($element instanceof ListItem) && !($element instanceof ListItemRun)) {
            return null;
        }

        $depth = $element->getDepth();
        $style = $element->getStyle();

        if (!$style) {
            return ['depth' => $depth, 'format' => 'unknown', 'num_id' => 0, 'text' => ''];
        }

        $numId = $style->getNumId() ?? 0;
        $listType = $style->getListType();

        // Bullet types: 1 (square filled), 3 (bullet filled), 5 (bullet empty)
        if (in_array($listType, [1, 3, 5])) {
            return ['depth' => $depth, 'format' => 'bullet', 'num_id' => $numId, 'text' => ''];
        }

        // Numbered types: 7, 8, 9 — try to resolve specific format
        $format = 'decimal';
        $textPattern = '';
        try {
            $numStyleName = $style->getNumStyle();
            if ($numStyleName) {
                $numbering = \PhpOffice\PhpWord\Style::getStyle($numStyleName);
                if ($numbering && method_exists($numbering, 'getLevels')) {
                    $levels = $numbering->getLevels();
                    if (isset($levels[$depth])) {
                        $level = $levels[$depth];
                        $fmt = $level->getFormat();
                        if ($fmt) $format = $fmt;
                        // getText() returns pattern like "%1.", "%1)", "%1. " etc.
                        if (method_exists($level, 'getText')) {
                            $textPattern = $level->getText() ?? '';
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep default 'decimal'
        }

        // NumberingLevel format 'bullet' means it's actually a bullet
        if ($format === 'bullet') {
            return ['depth' => $depth, 'format' => 'bullet', 'num_id' => $numId, 'text' => ''];
        }

        return ['depth' => $depth, 'format' => $format, 'num_id' => $numId, 'text' => $textPattern];
    }

    /**
     * Convert plain text to HTML, applying font styles (bold, italic, color, etc.)
     */
    private function textToHtml(string $text, $fontStyle): string
    {
        if (empty($text)) return '';

        $escaped = e($text);

        if (!($fontStyle instanceof Font)) {
            return $escaped;
        }

        $styles = [];
        $wrappers = [];

        // Color
        $color = $fontStyle->getColor();
        if ($color && $color !== '000000' && strtolower($color) !== 'auto') {
            $styles[] = 'color:#' . $color;
        }

        // Background/highlight color
        $bgColor = $fontStyle->getBgColor();
        if ($bgColor) {
            $styles[] = 'background-color:#' . $bgColor;
        }

        // Font size (convert half-points to pt)
        $size = $fontStyle->getSize();
        if ($size && $size != 11) { // Only add if not default size
            $styles[] = 'font-size:' . $size . 'pt';
        }

        // Font name
        $name = $fontStyle->getName();
        if ($name && !in_array(strtolower($name), ['calibri', 'times new roman', 'arial'])) {
            $styles[] = 'font-family:' . e($name);
        }

        // Wrap with style span if we have styles
        if (!empty($styles)) {
            $escaped = '<span style="' . implode(';', $styles) . '">' . $escaped . '</span>';
        }

        // Bold
        if ($fontStyle->isBold()) {
            $escaped = '<strong>' . $escaped . '</strong>';
        }

        // Italic
        if ($fontStyle->isItalic()) {
            $escaped = '<em>' . $escaped . '</em>';
        }

        // Underline
        $underline = $fontStyle->getUnderline();
        if ($underline && $underline !== 'none' && $underline !== Font::UNDERLINE_NONE) {
            $escaped = '<u>' . $escaped . '</u>';
        }

        // Strikethrough
        if ($fontStyle->isStrikethrough()) {
            $escaped = '<s>' . $escaped . '</s>';
        }

        // Superscript
        if ($fontStyle->isSuperScript()) {
            $escaped = '<sup>' . $escaped . '</sup>';
        }

        // Subscript
        if ($fontStyle->isSubScript()) {
            $escaped = '<sub>' . $escaped . '</sub>';
        }

        return $escaped;
    }

    /**
     * Convert a PHPWord Table element to an HTML <table>.
     */
    private function tableToHtml(Table $table): string
    {
        $html = '<table style="border-collapse:collapse;width:100%;margin:0.5em 0;border:1px solid #999;">';

        $isFirstRow = true;
        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $tag = $isFirstRow ? 'th' : 'td';
                $width = $cell->getWidth();
                $widthStyle = $width ? 'width:' . round($width / 15.1) . 'px;' : '';
                $cellStyle = $widthStyle . 'border:1px solid #999;padding:4px 8px;text-align:left;';
                if ($isFirstRow) {
                    $cellStyle .= 'font-weight:bold;background:#f3f4f6;';
                }
                $html .= '<' . $tag . ' style="' . $cellStyle . '">';

                $cellParts = [];
                foreach ($cell->getElements() as $cellElement) {
                    $result = $this->extractElementContent($cellElement);
                    if ($result) {
                        $content = !empty($result['html']) ? $result['html'] : e($result['text']);
                        if (!empty($content)) {
                            $cellParts[] = $content;
                        }
                    }
                }
                $html .= implode('<br>', $cellParts);

                $html .= '</' . $tag . '>';
            }
            $html .= '</tr>';
            $isFirstRow = false;
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Convert a PHPWord Formula element to inline LaTeX string.
     * Fallback for any Formula that survives the preprocessor.
     */
    private function formulaToLatex(Formula $formula): string
    {
        try {
            $math = $formula->getMath();
            $writer = new \PhpOffice\Math\Writer\MathML();
            $mathml = $writer->write($math);
            // Simple fallback: extract text content from the Math object
            return '$ ' . strip_tags($mathml) . ' $';
        } catch (\Throwable $e) {
            return '';
        }
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

            // Check if this image has crop metadata from the DOCX
            $imageString = $this->applyCropIfNeeded($image, $imageString);

            Storage::disk('public')->put($dest, $imageString);

            return $dest;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Apply crop to image binary data if DOCX crop metadata exists.
     *
     * Word stores cropped images as full originals + a:srcRect percentages.
     * PHPWord returns the full uncropped image, so we apply the crop via GD.
     */
    private function applyCropIfNeeded(Image $image, string $imageString): string
    {
        if (empty($this->imageCropMap)) {
            return $imageString;
        }

        // Resolve the image's source path inside the DOCX ZIP
        $source = $image->getSource();
        $imagePath = null;

        // Source format: "zip:///path/to/file.docx#word/media/imageN.ext"
        if (str_contains($source, '#')) {
            $imagePath = substr($source, strpos($source, '#') + 1);
        }

        if (!$imagePath || !isset($this->imageCropMap[$imagePath])) {
            return $imageString;
        }

        $crop = $this->imageCropMap[$imagePath];

        // Skip if crop is negligible
        if ($crop['l'] < 0.1 && $crop['t'] < 0.1 && $crop['r'] < 0.1 && $crop['b'] < 0.1) {
            return $imageString;
        }

        return $this->cropImageWithGd($imageString, $crop);
    }

    /**
     * Crop image binary using GD library.
     *
     * @param array{l: float, t: float, r: float, b: float} $crop Percentages to crop
     */
    private function cropImageWithGd(string $imageString, array $crop): string
    {
        $srcImage = @imagecreatefromstring($imageString);
        if (!$srcImage) {
            return $imageString; // GD can't handle this format, return original
        }

        $origWidth  = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Calculate pixel offsets from percentages
        $cropLeft   = (int) round($origWidth * $crop['l'] / 100);
        $cropTop    = (int) round($origHeight * $crop['t'] / 100);
        $cropRight  = (int) round($origWidth * $crop['r'] / 100);
        $cropBottom = (int) round($origHeight * $crop['b'] / 100);

        $newWidth  = $origWidth - $cropLeft - $cropRight;
        $newHeight = $origHeight - $cropTop - $cropBottom;

        // Sanity check
        if ($newWidth <= 0 || $newHeight <= 0) {
            imagedestroy($srcImage);
            return $imageString;
        }

        $croppedImage = imagecrop($srcImage, [
            'x'      => $cropLeft,
            'y'      => $cropTop,
            'width'  => $newWidth,
            'height' => $newHeight,
        ]);

        imagedestroy($srcImage);

        if (!$croppedImage) {
            return $imageString;
        }

        // Preserve alpha for PNG
        imagesavealpha($croppedImage, true);

        // Output to string
        ob_start();
        imagepng($croppedImage);
        $result = ob_get_clean();
        imagedestroy($croppedImage);

        return $result ?: $imageString;
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

    private function processBlock(array $block, array $narasiIdMap = []): array
    {
        if (empty($block['pertanyaan'])) {
            throw new \Exception('Pertanyaan tidak ditemukan');
        }

        $kategoriId = $this->importJob->meta['kategori_soal_id'] ?? null;
        $now = now();
        $soalId = Str::orderedUuid()->toString();

        // Resolve narasi_id from block's narasi_index
        $narasiId = null;
        $urutanDalamNarasi = 0;
        if (isset($block['narasi_index']) && $block['narasi_index'] !== null && isset($narasiIdMap[$block['narasi_index']])) {
            $narasiId = $narasiIdMap[$block['narasi_index']];
            // Count how many previous blocks share same narasi_index to determine order
            $urutanDalamNarasi = ($block['_urutan_narasi'] ?? 1);
        }

        $soalData = [
            'id'                  => $soalId,
            'kategori_id'        => $kategoriId,
            'sekolah_id'         => $this->importJob->sekolah_id,
            'created_by'         => $this->importJob->created_by,
            'tipe_soal'          => $block['jenis'],
            'pertanyaan'         => $block['pertanyaan'],
            'gambar_soal'        => $block['gambar_soal'],
            'posisi_gambar'      => $block['gambar_soal'] ? 'bawah' : null,
            'tingkat_kesulitan'  => $block['tingkat'] ?? 'sedang',
            'bobot'              => $block['bobot'] ?? 1.0,
            'narasi_id'          => $narasiId,
            'urutan_dalam_narasi'  => $urutanDalamNarasi,
            'nomor_urut_import'  => $block['nomor_urut_import'] ?? null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        $result = ['soal' => $soalData, 'opsi' => [], 'pasangan' => []];

        match ($block['jenis']) {
            'pg', 'pg_kompleks' => $result['opsi'] = $this->buildOpsiBatch($soalId, $block, $now),
            'benar_salah'       => $result['opsi'] = $this->buildBenarSalahBatch($soalId, $block, $now),
            'menjodohkan'       => $result['pasangan'] = $this->buildPasanganBatch($soalId, $block, $now),
            'isian', 'essay'    => $result['opsi'] = $this->buildIsianBatch($soalId, $block, $now),
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
            // Use rich HTML if available, fallback to plain text
            $richTeks = $block['opsi_html'][$label] ?? $teks;

            $batch[] = [
                'id'         => Str::orderedUuid()->toString(),
                'soal_id'    => $soalId,
                'label'      => $label,
                'teks'       => $richTeks ?: null,
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
