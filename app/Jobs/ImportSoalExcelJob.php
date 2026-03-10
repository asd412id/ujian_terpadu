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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

class ImportSoalExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;

    private array $jenisMap = [
        'pilihan_ganda'          => 'pg',
        'pilihan_ganda_kompleks' => 'pg_kompleks',
        'menjodohkan'            => 'menjodohkan',
        'isian'                  => 'isian',
        'essay'                  => 'essay',
    ];

    /** @var array<string, string> cell coordinate => stored image path */
    private array $imageMap = [];

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $filePath = Storage::disk('local')->path($this->importJob->filepath);

            $spreadsheet = IOFactory::load($filePath);

            $errors    = [];
            $success   = 0;
            $totalRows = 0;
            $processed = 0;

            // Count total rows first
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $rows = $sheet->toArray();
                if (empty($rows)) continue;
                $headerRow = $rows[0];
                $headers = array_map(fn ($h) => strtolower(trim((string) ($h ?? ''))), $headerRow);
                if (!in_array('pertanyaan', $headers)) continue;
                $totalRows += count($rows) - 1; // minus header
            }

            $this->importJob->update(['total_rows' => $totalRows]);

            foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
                // Extract embedded images for this sheet
                $this->imageMap = $this->extractSheetImages($sheet);

                $rows = $sheet->toArray();
                if (empty($rows)) continue;

                $headerRow = array_shift($rows);
                $headers = array_map(fn ($h) => strtolower(trim((string) ($h ?? ''))), $headerRow);

                if (!in_array('pertanyaan', $headers)) continue;

                foreach ($rows as $rowIndex => $rawRow) {
                    $excelRow = $rowIndex + 2; // 1-based, +1 for header
                    $row = $this->mapRowToAssoc($headers, $rawRow, $excelRow);
                    $processed++;

                    try {
                        $this->processRow($row);
                        $success++;
                    } catch (\Exception $e) {
                        $errors[] = "Sheet " . ($sheetIndex + 1) . " baris {$excelRow}: " . $e->getMessage();
                    }

                    if ($processed % 50 === 0) {
                        $this->importJob->update(['processed_rows' => $processed]);
                    }
                }
            }

            $this->importJob->update([
                'status'         => 'completed',
                'processed_rows' => $processed,
                'success_rows'   => $success,
                'error_rows'     => count($errors),
                'errors'         => $errors,
                'completed_at'   => now(),
            ]);

        } catch (\Exception $e) {
            $this->importJob->update([
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract all embedded images from a worksheet, mapped by cell coordinate.
     *
     * @return array<string, string> e.g. ['L2' => 'soal/gambar/uuid.png', 'N3' => 'soal/gambar/uuid.jpg']
     */
    private function extractSheetImages($sheet): array
    {
        $map = [];

        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinate = $drawing->getCoordinates(); // e.g. 'L2'
            $storedPath = $this->saveDrawing($drawing);

            if ($storedPath) {
                $map[$coordinate] = $storedPath;
            }
        }

        return $map;
    }

    /**
     * Save a Drawing/MemoryDrawing to public storage and return the path.
     */
    private function saveDrawing($drawing): ?string
    {
        $uuid = Str::uuid();

        if ($drawing instanceof MemoryDrawing) {
            $imageResource = $drawing->getImageResource();
            if (!$imageResource) return null;

            $ext = match ($drawing->getMimeType()) {
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/bmp'  => 'bmp',
                default      => 'jpg',
            };

            ob_start();
            match ($drawing->getMimeType()) {
                'image/png'  => imagepng($imageResource),
                'image/gif'  => imagegif($imageResource),
                'image/bmp'  => imagebmp($imageResource),
                default      => imagejpeg($imageResource),
            };
            $imageData = ob_get_clean();

            if (empty($imageData)) return null;

            $dest = "soal/gambar/{$uuid}.{$ext}";
            Storage::disk('public')->put($dest, $imageData);
            return $dest;

        } elseif ($drawing instanceof Drawing) {
            $path = $drawing->getPath();
            if (!$path || !file_exists($path)) return null;

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
            $dest = "soal/gambar/{$uuid}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($path));
            return $dest;
        }

        return null;
    }

    /**
     * Map raw row array to associative array with headers.
     * Also checks for embedded images in the cell positions.
     */
    private function mapRowToAssoc(array $headers, array $row, int $excelRow): array
    {
        $assoc = [];
        foreach ($headers as $colIndex => $header) {
            if ($header !== '') {
                $assoc[$header] = $row[$colIndex] ?? null;

                // Check if there's an embedded image at this cell
                $cellCoord = chr(65 + $colIndex) . $excelRow;
                if (isset($this->imageMap[$cellCoord])) {
                    $assoc['_image_' . $header] = $this->imageMap[$cellCoord];
                }
            }
        }
        return $assoc;
    }

    private function processRow(array $row): void
    {
        $pertanyaan = trim((string) ($row['pertanyaan'] ?? ''));
        if (empty($pertanyaan)) return;

        $jenisSoal = strtolower(trim((string) ($row['jenis_soal'] ?? 'pilihan_ganda')));
        $tipeDb = $this->jenisMap[$jenisSoal] ?? 'pg';

        $kategoriId = $this->importJob->meta['kategori_soal_id'] ?? null;
        $kesulitan  = strtolower(trim((string) ($row['tingkat_kesulitan'] ?? 'sedang')));
        if (!in_array($kesulitan, ['mudah', 'sedang', 'sulit'])) $kesulitan = 'sedang';

        $bobot      = is_numeric($row['bobot'] ?? null) ? (float) $row['bobot'] : 1.0;
        $pembahasan = isset($row['pembahasan']) ? trim((string) $row['pembahasan']) : null;

        // Image: check embedded image first, then filename reference
        $gambarSoal   = $row['_image_gambar_soal'] ?? $row['_image_pertanyaan'] ?? null;
        $posisiGambar = null;
        if ($gambarSoal) {
            $posisi = strtolower(trim((string) ($row['posisi_gambar'] ?? 'bawah')));
            $posisiGambar = in_array($posisi, ['atas', 'bawah', 'kiri', 'kanan']) ? $posisi : 'bawah';
        }

        $soal = Soal::create([
            'kategori_id'       => $kategoriId,
            'sekolah_id'        => $this->importJob->sekolah_id,
            'created_by'        => $this->importJob->created_by,
            'tipe_soal'         => $tipeDb,
            'pertanyaan'        => $pertanyaan,
            'gambar_soal'       => $gambarSoal,
            'posisi_gambar'     => $posisiGambar,
            'tingkat_kesulitan' => $kesulitan,
            'bobot'             => $bobot,
            'pembahasan'        => $pembahasan ?: null,
        ]);

        match ($tipeDb) {
            'pg', 'pg_kompleks' => $this->saveOpsiJawaban($soal, $row),
            'menjodohkan'       => $this->savePasangan($soal, $row),
            'isian'             => $this->saveIsianKunci($soal, $row),
            default             => null,
        };
    }

    private function saveOpsiJawaban(Soal $soal, array $row): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $keys   = ['pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'pilihan_e'];

        $kunci = strtoupper(trim((string) ($row['kunci_jawaban'] ?? '')));
        $kunciArr = array_map('trim', explode(',', $kunci));

        foreach ($labels as $i => $label) {
            $teks   = isset($row[$keys[$i]]) ? trim((string) $row[$keys[$i]]) : null;
            // Embedded image for this option cell
            $gambar = $row['_image_' . $keys[$i]] ?? null;

            if ($teks || $gambar) {
                OpsiJawaban::create([
                    'soal_id'  => $soal->id,
                    'label'    => $label,
                    'teks'     => $teks,
                    'gambar'   => $gambar,
                    'is_benar' => in_array($label, $kunciArr),
                    'urutan'   => $i,
                ]);
            }
        }
    }

    private function savePasangan(Soal $soal, array $row): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $kiri  = isset($row["pasangan_kiri_$i"]) ? trim((string) $row["pasangan_kiri_$i"]) : null;
            $kanan = isset($row["pasangan_kanan_$i"]) ? trim((string) $row["pasangan_kanan_$i"]) : null;

            if ($kiri && $kanan) {
                PasanganSoal::create([
                    'soal_id' => $soal->id,
                    'kiri'    => $kiri,
                    'kanan'   => $kanan,
                    'urutan'  => $i - 1,
                ]);
            }
        }
    }

    private function saveIsianKunci(Soal $soal, array $row): void
    {
        $kunci = isset($row['kunci_jawaban']) ? trim((string) $row['kunci_jawaban']) : null;
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
}
