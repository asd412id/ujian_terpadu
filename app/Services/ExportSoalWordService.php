<?php

namespace App\Services;

use App\Models\NarasiSoal;
use App\Models\Soal;
use App\Support\HtmlDisplay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\Section;

class ExportSoalWordService
{
    private array $normalStyle;
    private array $boldStyle;

    /**
     * Generate a PhpWord document from a collection of Soal.
     * The output matches the import template format exactly,
     * so the resulting .docx can be re-imported without modification.
     */
    public function generate(Collection $soalList): PhpWord
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $this->normalStyle = ['size' => 11];
        $this->boldStyle = ['bold' => true, 'size' => 11];
        $section = $phpWord->addSection();

        if ($soalList->isEmpty()) {
            $section->addText('Tidak ada soal untuk diekspor.', ['italic' => true, 'size' => 11, 'color' => '6b7280']);
            return $phpWord;
        }

        $segments = $this->buildExportSegments($soalList);
        $nomor = 1;

        foreach ($segments as $segment) {
            match ($segment['type']) {
                'narasi_start' => $this->writeNarasiStart($section, $segment['narasi']),
                'narasi_end'   => $this->writeNarasiEnd($section),
                'soal'         => $this->writeSoal($section, $segment['soal'], $nomor++),
            };
        }

        return $phpWord;
    }

    /**
     * Build ordered export segments from a soal collection.
     *
     * Groups soal by narasi, inserts narasi_start/narasi_end markers,
     * and preserves original ordering for roundtrip compatibility.
     *
     * Strategy: iterate soal in original order, track current narasi_id.
     * When narasi_id changes, emit boundary markers.
     */
    public function buildExportSegments(Collection $soalList): array
    {
        // First: group soal by narasi to ensure narasi soal are contiguous
        $standalone = [];
        $narasiGroups = []; // narasi_id => ['narasi' => NarasiSoal, 'soalList' => [...]]

        foreach ($soalList as $soal) {
            if ($soal->narasi_id && $soal->narasi) {
                if (!isset($narasiGroups[$soal->narasi_id])) {
                    $narasiGroups[$soal->narasi_id] = [
                        'narasi'   => $soal->narasi,
                        'soalList' => [],
                        'first_order' => $soal->nomor_urut_import ?? PHP_INT_MAX,
                    ];
                }
                $narasiGroups[$soal->narasi_id]['soalList'][] = $soal;
                // Track earliest soal in group for ordering
                $order = $soal->nomor_urut_import ?? PHP_INT_MAX;
                if ($order < $narasiGroups[$soal->narasi_id]['first_order']) {
                    $narasiGroups[$soal->narasi_id]['first_order'] = $order;
                }
            } else {
                $standalone[] = [
                    'soal'  => $soal,
                    'order' => $soal->nomor_urut_import ?? PHP_INT_MAX,
                ];
            }
        }

        // Sort narasi soal within each group by urutan_dalam_narasi
        foreach ($narasiGroups as &$group) {
            usort($group['soalList'], function (Soal $a, Soal $b) {
                return ($a->urutan_dalam_narasi ?? 0) <=> ($b->urutan_dalam_narasi ?? 0);
            });
        }
        unset($group);

        // Merge standalone + narasi groups, ordered by first appearance
        $allItems = [];

        foreach ($standalone as $item) {
            $allItems[] = [
                'type'  => 'standalone',
                'order' => $item['order'],
                'data'  => $item['soal'],
            ];
        }

        foreach ($narasiGroups as $narasiId => $group) {
            $allItems[] = [
                'type'  => 'narasi_group',
                'order' => $group['first_order'],
                'data'  => $group,
            ];
        }

        // Sort by original order
        usort($allItems, fn ($a, $b) => $a['order'] <=> $b['order']);

        // Build flat segment list
        $segments = [];

        foreach ($allItems as $item) {
            if ($item['type'] === 'standalone') {
                $segments[] = ['type' => 'soal', 'soal' => $item['data']];
            } else {
                // Narasi group
                $group = $item['data'];
                $segments[] = ['type' => 'narasi_start', 'narasi' => $group['narasi']];

                foreach ($group['soalList'] as $soal) {
                    $segments[] = ['type' => 'soal', 'soal' => $soal];
                }

                $segments[] = ['type' => 'narasi_end'];
            }
        }

        return $segments;
    }

    /**
     * Write [NARASI] block with narasi content.
     */
    private function writeNarasiStart(Section $section, NarasiSoal $narasi): void
    {
        $section->addText('[NARASI]', $this->boldStyle);

        $konten = $narasi->konten;
        if ($konten) {
            $this->writeHtmlContent($section, $konten);
        }

        $section->addText('[/NARASI]', $this->boldStyle);
        $section->addTextBreak(0);
    }

    /**
     * Write [/NARASI_SOAL] end marker.
     */
    private function writeNarasiEnd(Section $section): void
    {
        $section->addTextBreak(0);
        $section->addText('[/NARASI_SOAL]', $this->boldStyle);
        $section->addTextBreak(0);
    }

    /**
     * Write a single soal in import-compatible format.
     */
    private function writeSoal(Section $section, Soal $soal, int $nomor): void
    {
        // Build the soal header line: "N. [TAG] [meta] pertanyaan"
        $prefix = $nomor . '. ';

        // Type tag (PG has no tag)
        $typeTag = match ($soal->tipe_soal) {
            'pg_kompleks' => '[PG_KOMPLEKS] ',
            'benar_salah' => '[BENAR_SALAH] ',
            'menjodohkan' => '[MENJODOHKAN] ',
            'isian'       => '[ISIAN] ',
            'essay'       => '[ESSAY] ',
            default       => '',
        };

        // Meta tags (only non-defaults)
        $metaTags = $this->buildMetaTags($soal);

        $pertanyaanPlain = $this->htmlToPlainText($soal->pertanyaan);
        $headerLine = $prefix . $typeTag . $metaTags . $pertanyaanPlain;

        $section->addText($headerLine, $this->boldStyle);

        // Embed pertanyaan images
        $this->embedStorageImages($section, $soal->pertanyaan);

        // Also embed gambar_soal if present and not already inline
        if ($soal->gambar_soal && !str_contains($soal->pertanyaan ?? '', '<img')) {
            $this->embedImageFromStorage($section, $soal->gambar_soal);
        }

        // Type-specific content
        match ($soal->tipe_soal) {
            'pg', 'pg_kompleks'           => $this->writeOpsiPG($section, $soal),
            'benar_salah'                 => $this->writeBenarSalah($section, $soal),
            'menjodohkan'                 => $this->writeMenjodohkan($section, $soal),
            'isian', 'essay'              => $this->writeIsianEssay($section, $soal),
            default                       => null,
        };

        $section->addTextBreak(0);
    }

    /**
     * Write PG / PG Kompleks options and answer line.
     */
    private function writeOpsiPG(Section $section, Soal $soal): void
    {
        $opsiList = $soal->opsiJawaban->sortBy('urutan');
        $kunciLabels = [];

        foreach ($opsiList as $opsi) {
            $label = strtolower($opsi->label);
            $teks = $this->htmlToPlainText($opsi->teks);

            $line = '    ' . $label . '. ' . $teks;
            $section->addText($line, $this->normalStyle);

            // Embed opsi images
            $this->embedStorageImages($section, $opsi->teks, '    ');

            // Embed separate gambar field
            if ($opsi->gambar) {
                $this->embedImageFromStorage($section, $opsi->gambar, '    ');
            }

            if ($opsi->is_benar) {
                $kunciLabels[] = strtoupper($opsi->label);
            }
        }

        if (!empty($kunciLabels)) {
            $jawabanStr = implode(', ', $kunciLabels);
            $section->addText('    Jawaban: ' . $jawabanStr, $this->normalStyle);
        }
    }

    /**
     * Write Benar/Salah pernyataan lines.
     */
    private function writeBenarSalah(Section $section, Soal $soal): void
    {
        $opsiList = $soal->opsiJawaban->sortBy('urutan')->values();

        foreach ($opsiList as $i => $opsi) {
            $nomorBS = $i + 1;
            $teks = $this->htmlToPlainText($opsi->teks);
            $benarSalah = $opsi->is_benar ? 'BENAR' : 'SALAH';

            $line = '    ' . $nomorBS . ') ' . $teks . ' (' . $benarSalah . ')';
            $section->addText($line, $this->normalStyle);
        }
    }

    /**
     * Write Menjodohkan pairs.
     */
    private function writeMenjodohkan(Section $section, Soal $soal): void
    {
        $pasanganList = $soal->pasangan->sortBy('urutan');

        foreach ($pasanganList as $pasangan) {
            $kiri = $this->htmlToPlainText($pasangan->kiri_teks);
            $kanan = $this->htmlToPlainText($pasangan->kanan_teks);

            $line = '    ' . $kiri . ' = ' . $kanan;
            $section->addText($line, $this->normalStyle);
        }
    }

    /**
     * Write Isian/Essay answer line.
     */
    private function writeIsianEssay(Section $section, Soal $soal): void
    {
        $kunci = $soal->kunci_jawaban;
        if ($kunci) {
            $kunciPlain = $this->htmlToPlainText($kunci);
            $section->addText('    Jawaban: ' . $kunciPlain, $this->normalStyle);
        } else {
            $section->addText('    Jawaban:', $this->normalStyle);
        }
    }

    /**
     * Build meta tags string for non-default values.
     */
    private function buildMetaTags(Soal $soal): string
    {
        $tags = '';

        if ($soal->tingkat_kesulitan && $soal->tingkat_kesulitan !== 'sedang') {
            $tags .= '[tingkat: ' . $soal->tingkat_kesulitan . '] ';
        }

        $bobot = (float) $soal->bobot;
        if ($bobot !== 0.0 && $bobot !== 1.0) {
            // Format: integer if whole number, else up to 2 decimals
            $bobotStr = ($bobot == (int) $bobot) ? (string) (int) $bobot : rtrim(rtrim(number_format($bobot, 2, '.', ''), '0'), '.');
            $tags .= '[bobot: ' . $bobotStr . '] ';
        }

        return $tags;
    }

    /**
     * Convert HTML content to plain text suitable for Word export.
     * Strips tags, decodes entities, collapses whitespace.
     */
    private function htmlToPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return HtmlDisplay::plainText($html);
    }

    /**
     * Write HTML content as plain text paragraphs to Word section.
     * Splits on <p> tags to create separate paragraphs.
     */
    private function writeHtmlContent(Section $section, string $html): void
    {
        // Write plain text paragraphs first, then embed images after
        $plainText = $this->htmlToPlainText($html);
        if (!empty($plainText)) {
            $paragraphs = preg_split('/\n+/', $plainText, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($paragraphs as $para) {
                $trimmed = trim($para);
                if ($trimmed !== '') {
                    $section->addText($trimmed, $this->normalStyle);
                }
            }
        }

        // Embed inline images after text content
        $this->embedStorageImages($section, $html);
    }

    /**
     * Extract <img> tags from HTML and embed them in the Word document.
     * Only processes images stored in local storage (/storage/...).
     */
    private function embedStorageImages(Section $section, ?string $html, string $indent = ''): void
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return;
        }

        $paths = $this->extractStoragePaths($html);

        foreach ($paths as $path) {
            $this->embedImageFromStorage($section, $path, $indent);
        }
    }

    /**
     * Embed a single image from storage into the Word section.
     */
    private function embedImageFromStorage(Section $section, string $storagePath, string $indent = ''): void
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($storagePath)) {
            return;
        }

        try {
            $fullPath = $disk->path($storagePath);

            // Get image dimensions, cap at reasonable max for Word
            $imageSize = @getimagesize($fullPath);
            $style = ['wrappingStyle' => 'inline'];

            if ($imageSize) {
                $maxWidth = 450; // ~6 inches at 72dpi
                $maxHeight = 300;
                $width = $imageSize[0];
                $height = $imageSize[1];

                if ($width > $maxWidth) {
                    $ratio = $maxWidth / $width;
                    $width = $maxWidth;
                    $height = (int) ($height * $ratio);
                }
                if ($height > $maxHeight) {
                    $ratio = $maxHeight / $height;
                    $height = $maxHeight;
                    $width = (int) ($width * $ratio);
                }

                $style['width'] = $width;
                $style['height'] = $height;
            }

            $section->addImage($fullPath, $style);
        } catch (\Throwable $e) {
            Log::warning('ExportSoalWord: failed to embed image', [
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract storage file paths from inline <img> tags in HTML content.
     * Matches src attributes pointing to /storage/...
     *
     * @return string[]
     */
    private function extractStoragePaths(?string $html): array
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return [];
        }

        $paths = [];

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                if (preg_match('#/storage/(.+)$#', $src, $m)) {
                    $path = urldecode($m[1]);
                    if (str_starts_with($path, 'soal/') || str_starts_with($path, 'import/')) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }
}
