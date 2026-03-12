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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportPesertaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout  = 1200;
    public int $tries    = 1;

    private const SEKOLAH_HEADERS = ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];
    private const DINAS_HEADERS = ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];
    private const CHUNK_SIZE = 200;

    /** In-memory set of existing username_ujian for uniqueness check */
    private array $usedUsernames = [];

    public function __construct(public ImportJob $importJob)
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        // Guard: skip if job was manually cancelled while waiting in queue
        $this->importJob->refresh();
        if ($this->importJob->status === 'gagal') {
            return;
        }

        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $mode     = $this->importJob->meta['mode'] ?? 'update';
            $source   = $this->importJob->meta['source'] ?? 'sekolah';
            $isDinas  = $source === 'dinas';
            $path     = Storage::disk('local')->path($this->importJob->filepath);
            $rows     = Excel::toArray([], $path)[0] ?? [];

            // Auto-detect header row: scan the first 10 rows to find the expected headers
            // This handles files with title/merged rows before the actual header
            $expected = $isDinas ? self::DINAS_HEADERS : self::SEKOLAH_HEADERS;
            $headerRowIndex = $this->detectHeaderRow($rows, $expected);

            if ($headerRowIndex === null) {
                // No header row found — give a helpful error message
                $firstCell = strtolower(trim((string) ($rows[0][0] ?? '')));
                $templateName = $isDinas ? 'import peserta DINAS' : 'import peserta SEKOLAH';
                throw new \Exception(
                    "Template tidak sesuai. Kolom A seharusnya \"{$expected[0]}\", bukan \"{$firstCell}\". " .
                    "Pastikan Anda menggunakan template {$templateName}."
                );
            }

            // Remove everything up to and including the header row
            $rows = array_slice($rows, $headerRowIndex + 1);
            $this->importJob->update(['total_rows' => count($rows)]);

            // Pre-load caches
            $npsnCache = [];
            if ($isDinas) {
                $npsnCache = Sekolah::whereNotNull('npsn')->pluck('id', 'npsn')->toArray();
            }

            // Mode replace_all
            if ($mode === 'replace_all') {
                if ($isDinas) {
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

            // Pre-load existing usernames scoped to relevant sekolah for O(1) uniqueness checks
            $relevantSekolahIds = $isDinas
                ? array_values($npsnCache)
                : [$this->importJob->sekolah_id];

            $this->usedUsernames = Peserta::whereIn('sekolah_id', $relevantSekolahIds)
                ->pluck('username_ujian')
                ->flip()
                ->toArray();

            // Pre-load existing NIS per sekolah for duplicate checking

            $existingNis = Peserta::whereIn('sekolah_id', $relevantSekolahIds)
                ->whereNotNull('nis')
                ->select('nis', 'sekolah_id')
                ->get()
                ->groupBy('sekolah_id')
                ->map(fn ($items) => $items->pluck('nis')->flip()->toArray())
                ->toArray();

            $errors  = [];
            $success = 0;

            $chunks = array_chunk($rows, self::CHUNK_SIZE, true);

            foreach ($chunks as $chunk) {
                $insertBatch = [];
                $chunkErrors = [];
                $now = now();

                foreach ($chunk as $index => $row) {
                    try {
                        $parsed = $isDinas
                            ? $this->parseDinasRow($row, $index + 2, $npsnCache)
                            : $this->parseSekolahRow($row, $index + 2);

                        $sekolahId = $parsed['sekolah_id'];
                        $nisStr    = $parsed['nis'];

                        // Duplicate NIS check using in-memory cache
                        if ($nisStr) {
                            if (isset($existingNis[$sekolahId][$nisStr])) {
                                $errMsg = $isDinas
                                    ? "NIS $nisStr sudah terdaftar di sekolah NPSN {$parsed['npsn']}"
                                    : "NIS $nisStr sudah terdaftar";
                                throw new \Exception($errMsg);
                            }
                            // Mark as used for subsequent rows in this batch
                            $existingNis[$sekolahId][$nisStr] = true;
                        }

                        // Generate unique username using in-memory set (no DB query)
                        $username = $this->generateUsernameFromMemory($parsed['nisn'], $nisStr);

                        // Generate password
                        $password = Peserta::generatePassword();

                        $insertBatch[] = [
                            'id'             => Str::orderedUuid()->toString(),
                            'sekolah_id'     => $sekolahId,
                            'nama'           => $parsed['nama'],
                            'nis'            => $nisStr,
                            'nisn'           => $parsed['nisn'],
                            'kelas'          => $parsed['kelas'],
                            'jurusan'        => $parsed['jurusan'],
                            'jenis_kelamin'  => $parsed['jenis_kelamin'],
                            'tanggal_lahir'  => $parsed['tanggal_lahir'],
                            'username_ujian' => $username,
                            'password_ujian' => Hash::make($password, ['rounds' => 10]),
                            'password_plain' => encrypt($password),
                            'is_active'      => true,
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ];

                        $success++;
                    } catch (\Exception $e) {
                        $chunkErrors[] = ['baris' => $index + 2, 'pesan' => $e->getMessage()];
                    }
                }

                // Bulk insert with per-row fallback on duplicate
                if (!empty($insertBatch)) {
                    foreach (array_chunk($insertBatch, 50) as $subChunk) {
                        try {
                            Peserta::insert($subChunk);
                        } catch (\Illuminate\Database\QueryException $qe) {
                            // Bulk failed (likely duplicate) — insert one by one
                            foreach ($subChunk as $row) {
                                try {
                                    Peserta::insert([$row]);
                                } catch (\Illuminate\Database\QueryException $rowEx) {
                                    if (str_contains($rowEx->getMessage(), 'Duplicate entry')) {
                                        // Try with suffixed username
                                        $row['username_ujian'] = $row['username_ujian'] . '_' . substr(md5($row['id']), 0, 4);
                                        try {
                                            Peserta::insert([$row]);
                                        } catch (\Exception $e2) {
                                            $success--;
                                            $chunkErrors[] = ['baris' => 0, 'pesan' => "Duplikat username: {$row['nama']} ({$row['nis']})"];
                                        }
                                    } else {
                                        $success--;
                                        $chunkErrors[] = ['baris' => 0, 'pesan' => $rowEx->getMessage()];
                                    }
                                }
                            }
                        }
                    }
                }

                $errors = array_merge($errors, $chunkErrors);

                // Update progress per chunk
                $lastIndex = array_key_last($chunk);
                $this->importJob->update(['processed_rows' => $lastIndex + 1]);
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
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure (called by Laravel when job fails or times out).
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
     * Generate a unique username using in-memory set — zero DB queries.
     * Priority: NISN > NIS > random
     */
    private function generateUsernameFromMemory(?string $nisn, ?string $nis): string
    {
        if ($nisn && $nisn !== '') {
            $base = preg_replace('/\s+/', '', $nisn);
        } elseif ($nis && $nis !== '') {
            $base = preg_replace('/\s+/', '', $nis);
        } else {
            $base = strtoupper(Str::random(8));
        }

        $username = $base;
        $counter  = 1;
        while (isset($this->usedUsernames[$username])) {
            $username = $base . $counter;
            $counter++;
        }

        // Reserve this username for subsequent rows
        $this->usedUsernames[$username] = true;

        return $username;
    }

    /**
     * Scan the first rows to find the header row index.
     * Returns null if no matching header row is found within the first 10 rows.
     */
    private function detectHeaderRow(array $rows, array $expected): ?int
    {
        $maxScan = min(10, count($rows));
        $firstExpected = $expected[0];

        for ($i = 0; $i < $maxScan; $i++) {
            $row = $rows[$i] ?? [];
            $firstCell = strtolower(trim((string) ($row[0] ?? '')));

            if ($firstCell === $firstExpected) {
                $actualHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                $actualHeaders = array_slice($actualHeaders, 0, count($expected));

                if ($actualHeaders === $expected) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function parseDinasRow(array $row, int $baris, array $npsnCache): array
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

        return [
            'sekolah_id'    => $sekolahId,
            'npsn'          => $npsnStr,
            'nama'          => trim((string) $nama),
            'nis'           => $nis  ? preg_replace('/\s+/', '', trim((string) $nis))  : null,
            'nisn'          => $nisn ? preg_replace('/\s+/', '', trim((string) $nisn)) : null,
            'kelas'         => $kelas   ? trim((string) $kelas)   : null,
            'jurusan'       => $jurusan ? trim((string) $jurusan) : null,
            'jenis_kelamin' => in_array(strtoupper((string) $jk), ['L', 'P']) ? strtoupper((string) $jk) : null,
            'tanggal_lahir' => $tgl_lahir ? date('Y-m-d', strtotime((string) $tgl_lahir)) : null,
        ];
    }

    private function parseSekolahRow(array $row, int $baris): array
    {
        [$nama, $nis, $nisn, $kelas, $jurusan, $jk, $tgl_lahir] = array_pad($row, 7, null);

        if (empty(trim((string) $nama))) {
            throw new \Exception("Nama tidak boleh kosong");
        }

        return [
            'sekolah_id'    => $this->importJob->sekolah_id,
            'npsn'          => null,
            'nama'          => trim((string) $nama),
            'nis'           => $nis  ? preg_replace('/\s+/', '', trim((string) $nis))  : null,
            'nisn'          => $nisn ? preg_replace('/\s+/', '', trim((string) $nisn)) : null,
            'kelas'         => $kelas   ? trim((string) $kelas)   : null,
            'jurusan'       => $jurusan ? trim((string) $jurusan) : null,
            'jenis_kelamin' => in_array(strtoupper((string) $jk), ['L', 'P']) ? strtoupper((string) $jk) : null,
            'tanggal_lahir' => $tgl_lahir ? date('Y-m-d', strtotime((string) $tgl_lahir)) : null,
        ];
    }
}
