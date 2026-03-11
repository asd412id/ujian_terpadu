<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Peserta;
use App\Models\Sekolah;
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
     * Header template peserta untuk akun SEKOLAH (tanpa NPSN).
     */
    private const SEKOLAH_HEADERS = ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];

    /**
     * Header template peserta untuk akun DINAS (dengan NPSN sebagai kolom pertama).
     */
    private const DINAS_HEADERS = ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $mode     = $this->importJob->meta['mode'] ?? 'update';
            $source   = $this->importJob->meta['source'] ?? 'sekolah';
            $isDinas  = $source === 'dinas';
            $path     = Storage::disk('local')->path($this->importJob->filepath);
            $rows     = Excel::toArray([], $path)[0] ?? [];

            // Ambil header row dan validasi
            $headers = array_shift($rows);
            $this->validateHeaders($headers, $isDinas);
            $this->importJob->update(['total_rows' => count($rows)]);

            // Cache NPSN -> sekolah_id (hanya untuk dinas)
            $npsnCache = [];
            if ($isDinas) {
                $npsnCache = Sekolah::whereNotNull('npsn')
                    ->pluck('id', 'npsn')
                    ->toArray();
            }

            // Mode replace_all
            if ($mode === 'replace_all') {
                if ($isDinas) {
                    // Kumpulkan semua NPSN unik dari file, lalu hapus peserta per sekolah
                    $npsnList = collect($rows)
                        ->map(fn ($row) => trim((string) ($row[0] ?? '')))
                        ->filter()
                        ->unique()
                        ->values();

                    foreach ($npsnList as $npsn) {
                        $sekolahId = $npsnCache[$npsn] ?? null;
                        if ($sekolahId) {
                            Peserta::where('sekolah_id', $sekolahId)->delete();
                        }
                    }
                } elseif ($this->importJob->sekolah_id) {
                    Peserta::where('sekolah_id', $this->importJob->sekolah_id)->delete();
                }
            }

            $errors  = [];
            $success = 0;

            foreach ($rows as $index => $row) {
                try {
                    if ($isDinas) {
                        $this->processDinasRow($row, $index + 2, $npsnCache);
                    } else {
                        $this->processSekolahRow($row, $index + 2);
                    }
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
            $this->importJob->update([
                'status'  => 'gagal',
                'catatan' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validasi header baris pertama sesuai sumber import.
     */
    private function validateHeaders(?array $headerRow, bool $isDinas): void
    {
        if (empty($headerRow)) {
            throw new \Exception("File Excel kosong atau tidak memiliki header.");
        }

        $expected = $isDinas ? self::DINAS_HEADERS : self::SEKOLAH_HEADERS;
        $actualHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $actualHeaders = array_slice($actualHeaders, 0, count($expected));

        foreach ($expected as $i => $exp) {
            $actual = $actualHeaders[$i] ?? '';
            if ($actual !== $exp) {
                $templateName = $isDinas ? 'import peserta DINAS' : 'import peserta SEKOLAH';
                throw new \Exception(
                    "Template tidak sesuai. Kolom " . chr(65 + $i) . " seharusnya \"$exp\", bukan \"$actual\". " .
                    "Pastikan Anda menggunakan template $templateName."
                );
            }
        }
    }

    /**
     * Proses satu baris untuk import DINAS (kolom: npsn, nama, nis, nisn, kelas, jurusan, jk, tgl_lahir).
     */
    private function processDinasRow(array $row, int $baris, array $npsnCache): void
    {
        [$npsn, $nama, $nis, $nisn, $kelas, $jurusan, $jk, $tgl_lahir] = array_pad($row, 8, null);

        $npsnStr = trim((string) ($npsn ?? ''));
        if (empty($npsnStr)) {
            throw new \Exception("NPSN tidak boleh kosong");
        }

        $sekolahId = $npsnCache[$npsnStr] ?? null;
        if (!$sekolahId) {
            throw new \Exception("NPSN $npsnStr tidak ditemukan di database");
        }

        if (empty(trim((string) $nama))) {
            throw new \Exception("Nama tidak boleh kosong");
        }

        $nisStr  = $nis  ? (string) trim($nis)  : null;
        $nisnStr = $nisn ? (string) trim($nisn) : null;

        if ($nisStr && Peserta::where('nis', $nisStr)->where('sekolah_id', $sekolahId)->exists()) {
            throw new \Exception("NIS $nisStr sudah terdaftar di sekolah NPSN $npsnStr");
        }

        $password = Peserta::generatePassword();
        $username = Peserta::generateUsername($nisStr, $nisnStr, $sekolahId);

        Peserta::create([
            'sekolah_id'    => $sekolahId,
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

    /**
     * Proses satu baris untuk import SEKOLAH (kolom: nama, nis, nisn, kelas, jurusan, jk, tgl_lahir).
     */
    private function processSekolahRow(array $row, int $baris): void
    {
        [$nama, $nis, $nisn, $kelas, $jurusan, $jk, $tgl_lahir] = array_pad($row, 7, null);

        if (empty(trim((string) $nama))) {
            throw new \Exception("Nama tidak boleh kosong");
        }

        $nisStr  = $nis  ? (string) trim($nis)  : null;
        $nisnStr = $nisn ? (string) trim($nisn) : null;

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
