<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Peserta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportPesertaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout  = 600;
    public int $tries    = 3;

    /**
     * Header yang WAJIB ada di baris pertama template peserta (urutan kolom A–G).
     * Digunakan untuk mencegah upload template sekolah ke import peserta.
     */
    private const EXPECTED_HEADERS = ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $mode = $this->importJob->meta['mode'] ?? 'update';
            $path = Storage::disk('local')->path($this->importJob->filepath);
            $rows = Excel::toArray([], $path)[0] ?? [];

            // Ambil header row dan validasi — cegah upload template sekolah ke import peserta
            $headers = array_shift($rows);
            $this->validateHeaders($headers);
            $this->importJob->update(['total_rows' => count($rows)]);

            // Mode replace_all: hapus semua peserta sekolah ini terlebih dahulu
            if ($mode === 'replace_all' && $this->importJob->sekolah_id) {
                Peserta::where('sekolah_id', $this->importJob->sekolah_id)->delete();
            }

            $errors = [];
            $success = 0;

            foreach ($rows as $index => $row) {
                try {
                    $this->processRow($row, $index + 2);
                    $success++;
                } catch (\Exception $e) {
                    $errors[] = ['baris' => $index + 2, 'pesan' => $e->getMessage()];
                }

                // Update progress setiap 50 baris
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
            $this->importJob->update([
                'status'  => 'gagal',
                'catatan' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validasi header baris pertama. Melempar exception jika header tidak cocok
     * dengan template peserta, sehingga mencegah upload template sekolah ke sini.
     */
    private function validateHeaders(?array $headerRow): void
    {
        if (empty($headerRow)) {
            throw new \Exception("File Excel kosong atau tidak memiliki header.");
        }

        // Normalisasi header dari file
        $actualHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $actualHeaders = array_slice($actualHeaders, 0, count(self::EXPECTED_HEADERS));

        foreach (self::EXPECTED_HEADERS as $i => $expected) {
            $actual = $actualHeaders[$i] ?? '';
            if ($actual !== $expected) {
                throw new \Exception(
                    "Template tidak sesuai. Kolom " . chr(65 + $i) . " seharusnya \"$expected\", bukan \"$actual\". " .
                    "Pastikan Anda menggunakan template import PESERTA, bukan template lain."
                );
            }
        }
    }

    private function processRow(array $row, int $baris): void
    {
        // Kolom: nama, nis, nisn, kelas, jurusan, jenis_kelamin, tanggal_lahir
        [$nama, $nis, $nisn, $kelas, $jurusan, $jk, $tgl_lahir] = array_pad($row, 7, null);

        if (empty(trim((string) $nama))) {
            throw new \Exception("Nama tidak boleh kosong");
        }

        $nisStr  = $nis  ? (string) trim($nis)  : null;
        $nisnStr = $nisn ? (string) trim($nisn) : null;

        // Cek duplikat NIS/NISN
        if ($nisStr && Peserta::where('nis', $nisStr)->where('sekolah_id', $this->importJob->sekolah_id)->exists()) {
            throw new \Exception("NIS $nisStr sudah terdaftar");
        }

        $password = Peserta::generatePassword();
        $username = Peserta::generateUsername($nisStr, $nisnStr, $this->importJob->sekolah_id);

        Peserta::create([
            'sekolah_id'    => $this->importJob->sekolah_id,
            'nama'          => trim((string) $nama),
            'nis'           => $nisStr,
            'nisn'          => $nisnStr,
            'kelas'         => $kelas  ? trim((string) $kelas)  : null,
            'jurusan'       => $jurusan ? trim((string) $jurusan) : null,
            'jenis_kelamin' => in_array(strtoupper((string)$jk), ['L', 'P']) ? strtoupper((string) $jk) : null,
            'tanggal_lahir' => $tgl_lahir ? date('Y-m-d', strtotime((string) $tgl_lahir)) : null,
            'username_ujian'=> $username,
            'password_ujian'=> Hash::make($password),
            'password_plain'=> encrypt($password),
        ]);
    }
}
