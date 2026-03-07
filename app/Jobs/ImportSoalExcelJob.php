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
use Maatwebsite\Excel\Facades\Excel;

class ImportSoalExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::disk('local')->path($this->importJob->filepath);
            $rows = Excel::toArray([], $path)[0] ?? [];
            array_shift($rows); // skip header

            $this->importJob->update(['total_rows' => count($rows)]);

            $errors  = [];
            $success = 0;

            foreach ($rows as $index => $row) {
                try {
                    $this->processRow($row);
                    $success++;
                } catch (\Exception $e) {
                    $errors[] = ['baris' => $index + 2, 'pesan' => $e->getMessage()];
                }

                if (($index + 1) % 50 === 0) {
                    $this->importJob->update(['processed_rows' => $index + 1]);
                }
            }

            $this->importJob->update([
                'status'         => 'selesai',
                'processed_rows' => count($rows),
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

    private function processRow(array $row): void
    {
        // Kolom Excel: kategori_kode, tipe_soal, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci, kesulitan, bobot, pembahasan
        [$kodeKategori, $tipe, $pertanyaan, $a, $b, $c, $d, $e, $kunci, $kesulitan, $bobot, $pembahasan] = array_pad($row, 12, null);

        if (empty(trim((string) $pertanyaan))) return;

        $kategori = KategoriSoal::where('kode', trim((string) $kodeKategori))->first()
                 ?? KategoriSoal::where('nama', trim((string) $kodeKategori))->first();

        if (! $kategori) {
            throw new \Exception("Kategori '$kodeKategori' tidak ditemukan");
        }

        $tipeClean = strtolower(trim((string) $tipe)) ?: 'pg';
        if (! in_array($tipeClean, ['pg', 'pg_kompleks', 'isian', 'essay'])) $tipeClean = 'pg';

        $soal = Soal::create([
            'kategori_id'       => $kategori->id,
            'sekolah_id'        => $this->importJob->sekolah_id,
            'created_by'        => $this->importJob->created_by,
            'tipe_soal'         => $tipeClean,
            'pertanyaan'        => trim((string) $pertanyaan),
            'tingkat_kesulitan' => in_array(strtolower((string)$kesulitan), ['mudah','sedang','sulit'])
                                   ? strtolower((string) $kesulitan) : 'sedang',
            'bobot'             => is_numeric($bobot) ? (float) $bobot : 1.0,
            'pembahasan'        => $pembahasan ? trim((string) $pembahasan) : null,
        ]);

        // Simpan opsi
        $opsiData = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e];
        $kunciArr = array_map('strtoupper', array_map('trim', explode(',', (string) $kunci)));

        foreach ($opsiData as $label => $teks) {
            if ($teks && trim((string) $teks)) {
                OpsiJawaban::create([
                    'soal_id'  => $soal->id,
                    'label'    => $label,
                    'teks'     => trim((string) $teks),
                    'is_benar' => in_array($label, $kunciArr),
                    'urutan'   => array_search($label, ['A','B','C','D','E']),
                ]);
            }
        }
    }
}
