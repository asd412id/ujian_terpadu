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

class ImportSoalWordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

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

            DB::transaction(function () use ($soalBlocks, &$errors, &$success) {
                foreach ($soalBlocks as $index => $block) {
                    try {
                        $this->processBlock($block);
                        $success++;
                    } catch (\Exception $e) {
                        $errors[] = "Soal " . ($index + 1) . ": " . $e->getMessage();
                    }

                    if (($index + 1) % 50 === 0) {
                        $this->importJob->update(['processed_rows' => $index + 1]);
                    }
                }
            });

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

                    $current = [
                        'pertanyaan'  => $soalText,
                        'jenis'       => $jenis,
                        'opsi'        => [],
                        'opsi_gambar' => [],
                        'kunci'       => null,
                        'gambar_soal' => !empty($images) ? $this->saveImageData($images[0]) : $gambarFromText,
                        'pasangan'    => [],
                        'pernyataan_bs' => [],
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

                // Menjodohkan: "kiri = kanan"
                } elseif ($current && $current['jenis'] === 'menjodohkan' && preg_match('/^(.+?)\s*=\s*(.+)$/', $text, $m)) {
                    $current['pasangan'][] = [
                        'kiri'  => trim($m[1]),
                        'kanan' => trim($m[2]),
                    ];

                // Jawaban line
                } elseif ($current && preg_match('/^(Jawaban|Kunci)\s*:\s*(.*)/i', $text, $m)) {
                    $jawaban = trim($m[2]);
                    if ($jawaban && !preg_match('/^\(.*\)$/', $jawaban)) {
                        $current['kunci'] = $jawaban;
                    }

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

    private function processBlock(array $block): void
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
            'tingkat_kesulitan' => 'sedang',
            'bobot'             => 1.0,
        ]);

        match ($block['jenis']) {
            'pg', 'pg_kompleks' => $this->saveOpsi($soal, $block),
            'benar_salah'       => $this->saveBenarSalah($soal, $block),
            'menjodohkan'       => $this->savePasangan($soal, $block),
            'isian', 'essay'    => $this->saveIsian($soal, $block),
            default             => null,
        };
    }

    private function saveOpsi(Soal $soal, array $block): void
    {
        $kunciStr = strtoupper(str_replace(' ', '', (string) ($block['kunci'] ?? '')));
        $kunciArr = $kunciStr ? explode(',', $kunciStr) : [];

        $i = 0;
        foreach ($block['opsi'] as $label => $teks) {
            $gambar = $block['opsi_gambar'][$label] ?? null;

            OpsiJawaban::create([
                'soal_id'  => $soal->id,
                'label'    => $label,
                'teks'     => $teks ?: null,
                'gambar'   => $gambar,
                'is_benar' => in_array($label, $kunciArr),
                'urutan'   => $i++,
            ]);
        }
    }

    private function savePasangan(Soal $soal, array $block): void
    {
        foreach ($block['pasangan'] as $i => $pair) {
            PasanganSoal::create([
                'soal_id'    => $soal->id,
                'kiri_teks'  => $pair['kiri'],
                'kanan_teks' => $pair['kanan'],
                'urutan'     => $i,
            ]);
        }
    }

    private function saveBenarSalah(Soal $soal, array $block): void
    {
        $pernyataanList = $block['pernyataan_bs'] ?? [];
        foreach ($pernyataanList as $i => $item) {
            OpsiJawaban::create([
                'soal_id'  => $soal->id,
                'label'    => (string) ($i + 1),
                'teks'     => $item['teks'],
                'is_benar' => $item['benar'],
                'urutan'   => $i,
            ]);
        }
    }

    private function saveIsian(Soal $soal, array $block): void
    {
        $kunci = $block['kunci'] ?? null;
        if ($kunci) {
            OpsiJawaban::create([
                'soal_id'  => $soal->id,
                'label'    => 'KUNCI',
                'teks'     => $kunci,
                'is_benar' => true,
                'urutan'   => 0,
            ]);
        }
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
