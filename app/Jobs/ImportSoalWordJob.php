<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Soal;
use App\Models\OpsiJawaban;
use App\Models\KategoriSoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;

class ImportSoalWordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;

    // Regex untuk parse soal dari Word (format baku)
    // Contoh format Word:
    // 1. Pertanyaan soal ...
    //    A. Opsi A
    //    B. Opsi B
    //    C. Opsi C
    //    D. Opsi D
    //    Kunci: A
    //    Kategori: MTK

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path     = Storage::disk('local')->path($this->importJob->filepath);
            $phpWord  = IOFactory::load($path);
            $sections = $phpWord->getSections();

            $soalBlocks = $this->parseWordSections($sections);
            $this->importJob->update(['total_rows' => count($soalBlocks)]);

            $errors  = [];
            $success = 0;

            foreach ($soalBlocks as $index => $block) {
                try {
                    $this->processBlock($block);
                    $success++;
                } catch (\Exception $e) {
                    $errors[] = ['soal' => $index + 1, 'pesan' => $e->getMessage()];
                }
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
            $this->importJob->update(['status' => 'gagal', 'catatan' => $e->getMessage()]);
            throw $e;
        }
    }

    private function parseWordSections(array $sections): array
    {
        $blocks    = [];
        $current   = null;
        $inOptions = false;

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                if (! method_exists($element, 'getText')) continue;
                $text = trim($element->getText());
                if (empty($text)) continue;

                // Detect soal baru: dimulai dengan angka. atau SOAL
                if (preg_match('/^(\d+)\.\s+(.+)/', $text, $m)) {
                    if ($current) $blocks[] = $current;
                    $current   = ['pertanyaan' => $m[2], 'opsi' => [], 'kunci' => null, 'kategori' => null];
                    $inOptions = false;

                } elseif ($current && preg_match('/^([A-E])\.\s+(.+)/i', $text, $m)) {
                    $current['opsi'][strtoupper($m[1])] = $m[2];

                } elseif ($current && preg_match('/^Kunci\s*:\s*([A-E,]+)/i', $text, $m)) {
                    $current['kunci'] = strtoupper(str_replace(' ', '', $m[1]));

                } elseif ($current && preg_match('/^Kategori\s*:\s*(.+)/i', $text, $m)) {
                    $current['kategori'] = trim($m[1]);

                } elseif ($current && preg_match('/^Kesulitan\s*:\s*(mudah|sedang|sulit)/i', $text, $m)) {
                    $current['kesulitan'] = strtolower($m[1]);
                }
            }
        }

        if ($current) $blocks[] = $current;
        return $blocks;
    }

    private function processBlock(array $block): void
    {
        if (empty($block['pertanyaan'])) {
            throw new \Exception("Pertanyaan tidak ditemukan");
        }

        $kategori = null;
        if ($block['kategori']) {
            $kategori = KategoriSoal::where('kode', $block['kategori'])->first()
                     ?? KategoriSoal::where('nama', $block['kategori'])->first();
        }
        if (! $kategori) {
            $kategori = KategoriSoal::first();
        }

        $soal = Soal::create([
            'kategori_id'       => $kategori->id,
            'sekolah_id'        => $this->importJob->sekolah_id,
            'created_by'        => $this->importJob->created_by,
            'tipe_soal'         => 'pg',
            'pertanyaan'        => $block['pertanyaan'],
            'tingkat_kesulitan' => $block['kesulitan'] ?? 'sedang',
            'bobot'             => 1.0,
        ]);

        $kunciArr = $block['kunci'] ? explode(',', $block['kunci']) : [];

        foreach ($block['opsi'] as $label => $teks) {
            OpsiJawaban::create([
                'soal_id'  => $soal->id,
                'label'    => $label,
                'teks'     => $teks,
                'is_benar' => in_array($label, $kunciArr),
                'urutan'   => array_search($label, ['A','B','C','D','E']),
            ]);
        }
    }
}
