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

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = Storage::disk('local')->path($this->importJob->filepath);
            $rows = Excel::toArray([], $path)[0] ?? [];

            // Skip header row
            $headers = array_shift($rows);
            $this->importJob->update(['total_rows' => count($rows)]);

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
