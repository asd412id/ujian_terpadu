<?php

namespace App\Jobs;

use App\Models\DinasPendidikan;
use App\Models\ImportJob;
use App\Models\Sekolah;
use App\Models\User;
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

class ImportSekolahJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries   = 1;
    public string $queue = 'imports';

    private const VALID_JENJANG = ['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI'];
    private const EXPECTED_HEADERS = ['nama', 'npsn', 'jenjang', 'alamat', 'kota', 'telepon', 'email', 'kepala_sekolah'];
    private const CHUNK_SIZE = 200;

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        // Guard: skip if job was manually cancelled while waiting in queue
        $this->importJob->refresh();
        if ($this->importJob->status === 'gagal') {
            return;
        }

        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $mode  = $this->importJob->meta['mode'] ?? 'update';
            $path  = Storage::disk('local')->path($this->importJob->filepath);
            $rows  = Excel::toArray([], $path)[0] ?? [];

            $headerRow = array_shift($rows);
            $this->validateHeaders($headerRow);
            $this->importJob->update(['total_rows' => count($rows)]);

            if ($mode === 'replace_all') {
                foreach (Sekolah::cursor() as $s) {
                    $s->delete();
                }
            }

            $dinasId = DinasPendidikan::value('id');

            // Pre-load existing data to avoid per-row queries
            $existingNpsn      = Sekolah::whereNotNull('npsn')->pluck('id', 'npsn')->toArray();
            $existingOperators = User::where('role', User::ROLE_ADMIN_SEKOLAH)
                ->whereNotNull('sekolah_id')
                ->pluck('sekolah_id')
                ->flip()
                ->toArray();
            $existingEmails    = User::pluck('id', 'email')->toArray();

            $emailDomain = config('app.ops_email_domain', 'sekolah.local');

            $errors  = [];
            $success = 0;

            $chunks = array_chunk($rows, self::CHUNK_SIZE, true);

            foreach ($chunks as $chunk) {
                $sekolahBatch   = [];
                $operatorBatch  = [];
                $chunkErrors    = [];

                foreach ($chunk as $index => $row) {
                    try {
                        $parsed = $this->parseRow($row, $index + 2);

                        if (empty($parsed['jenjang']) || !in_array($parsed['jenjang'], self::VALID_JENJANG)) {
                            throw new \Exception("Jenjang tidak valid. Gunakan: " . implode(', ', self::VALID_JENJANG));
                        }

                        $parsed['data']['dinas_id'] = $dinasId;
                        $sekolahBatch[$index] = $parsed;
                    } catch (\Exception $e) {
                        $chunkErrors[] = ['baris' => $index + 2, 'pesan' => $e->getMessage()];
                    }
                }

                // Bulk upsert sekolah in a single transaction
                DB::transaction(function () use ($sekolahBatch, $mode, &$existingNpsn, &$existingOperators, &$existingEmails, $emailDomain, &$success, &$chunkErrors) {
                    foreach ($sekolahBatch as $index => $parsed) {
                        try {
                            $data = $parsed['data'];
                            $npsn = $data['npsn'] ?? null;

                            if ($mode === 'update') {
                                if (empty($npsn)) {
                                    throw new \Exception("NPSN kosong, baris dilewati untuk mode update");
                                }

                                if (isset($existingNpsn[$npsn])) {
                                    Sekolah::where('id', $existingNpsn[$npsn])->update($data);
                                    $sekolahId = $existingNpsn[$npsn];
                                } else {
                                    $sekolah = Sekolah::create($data);
                                    $sekolahId = $sekolah->id;
                                    $existingNpsn[$npsn] = $sekolahId;
                                }
                            } else {
                                $sekolah = Sekolah::create($data);
                                $sekolahId = $sekolah->id;
                                if ($npsn) $existingNpsn[$npsn] = $sekolahId;
                            }

                            // Create operator if not exists — using in-memory cache
                            if (!isset($existingOperators[$sekolahId])) {
                                $identifier = $npsn ?: Str::slug($data['nama']);
                                $email      = "{$identifier}@{$emailDomain}";

                                $counter = 1;
                                $emailBase = $email;
                                while (isset($existingEmails[$email])) {
                                    $email = str_replace("@{$emailDomain}", "_{$counter}@{$emailDomain}", $emailBase);
                                    $counter++;
                                }

                                User::create([
                                    'name'       => "Operator {$data['nama']}",
                                    'email'      => $email,
                                    'password'   => Hash::make($npsn ?: 'password', ['rounds' => 10]),
                                    'role'       => User::ROLE_ADMIN_SEKOLAH,
                                    'sekolah_id' => $sekolahId,
                                    'is_active'  => true,
                                ]);

                                $existingEmails[$email]       = true;
                                $existingOperators[$sekolahId] = true;
                            }

                            $success++;
                        } catch (\Exception $e) {
                            $chunkErrors[] = ['baris' => $index + 2, 'pesan' => $e->getMessage()];
                        }
                    }
                });

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
                'status'       => 'gagal',
                'catatan'      => $e->getMessage(),
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

    private function validateHeaders(?array $headerRow): void
    {
        if (empty($headerRow)) {
            throw new \Exception("File Excel kosong atau tidak memiliki header.");
        }

        $actualHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $actualHeaders = array_slice($actualHeaders, 0, count(self::EXPECTED_HEADERS));

        foreach (self::EXPECTED_HEADERS as $i => $expected) {
            $actual = $actualHeaders[$i] ?? '';
            if ($actual !== $expected) {
                throw new \Exception(
                    "Template tidak sesuai. Kolom " . chr(65 + $i) . " seharusnya \"$expected\", bukan \"$actual\". " .
                    "Pastikan Anda menggunakan template import SEKOLAH, bukan template lain."
                );
            }
        }
    }

    private function parseRow(array $row, int $baris): array
    {
        [$nama, $npsn, $jenjang, $alamat, $kota, $telepon, $email, $kepalaSekolah] = array_pad($row, 8, null);

        $nama    = $nama    ? trim((string) $nama)    : null;
        $npsn    = $npsn    ? trim((string) $npsn)    : null;
        $jenjang = $jenjang ? strtoupper(trim((string) $jenjang)) : null;

        if (empty($nama)) {
            throw new \Exception("Nama sekolah tidak boleh kosong");
        }

        return [
            'jenjang' => $jenjang,
            'data'    => [
                'nama'           => $nama,
                'npsn'           => $npsn ?: null,
                'jenjang'        => $jenjang,
                'alamat'         => $alamat        ? trim((string) $alamat)        : null,
                'kota'           => $kota          ? trim((string) $kota)          : null,
                'telepon'        => $telepon       ? trim((string) $telepon)       : null,
                'email'          => $email         ? trim((string) $email)         : null,
                'kepala_sekolah' => $kepalaSekolah ? trim((string) $kepalaSekolah) : null,
                'is_active'      => true,
            ],
        ];
    }
}
