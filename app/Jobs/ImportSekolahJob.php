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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportSekolahJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;

    private const VALID_JENJANG = ['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI'];

    /**
     * Header yang WAJIB ada di baris pertama template sekolah (urutan kolom A–H).
     * Digunakan untuk mencegah upload template peserta ke import sekolah.
     */
    private const EXPECTED_HEADERS = ['nama', 'npsn', 'jenjang', 'alamat', 'kota', 'telepon', 'email', 'kepala_sekolah'];

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $this->importJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $mode  = $this->importJob->meta['mode'] ?? 'update';
            $path  = Storage::disk('local')->path($this->importJob->filepath);
            $rows  = Excel::toArray([], $path)[0] ?? [];

            // Validasi header — cegah upload template peserta ke import sekolah
            $headerRow = array_shift($rows);
            $this->validateHeaders($headerRow);
            $this->importJob->update(['total_rows' => count($rows)]);

            // Mode replace_all: hapus semua sekolah (cascade hapus peserta, sesi, jawaban + user operator via model event)
            if ($mode === 'replace_all') {
                foreach (Sekolah::cursor() as $s) {
                    $s->delete();
                }
            }

            $dinasId = DinasPendidikan::value('id');

            $errors  = [];
            $success = 0;

            foreach ($rows as $index => $row) {
                try {
                    $this->processRow($row, $index + 2, $mode, $dinasId);
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
     * dengan template sekolah, sehingga mencegah upload template peserta ke sini.
     */
    private function validateHeaders(?array $headerRow): void
    {
        if (empty($headerRow)) {
            throw new \Exception("File Excel kosong atau tidak memiliki header.");
        }

        // Normalisasi header dari file
        $actualHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        // Ambil sejumlah expected headers saja
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

    private function processRow(array $row, int $baris, string $mode, ?string $dinasId): void
    {
        // Kolom: nama, npsn, jenjang, alamat, kota, telepon, email, kepala_sekolah
        [$nama, $npsn, $jenjang, $alamat, $kota, $telepon, $email, $kepalaSekolah] = array_pad($row, 8, null);

        $nama    = $nama    ? trim((string) $nama)    : null;
        $npsn    = $npsn    ? trim((string) $npsn)    : null;
        $jenjang = $jenjang ? strtoupper(trim((string) $jenjang)) : null;

        if (empty($nama)) {
            throw new \Exception("Nama sekolah tidak boleh kosong");
        }

        if (empty($jenjang) || ! in_array($jenjang, self::VALID_JENJANG)) {
            throw new \Exception("Jenjang tidak valid. Gunakan: " . implode(', ', self::VALID_JENJANG));
        }

        $data = [
            'dinas_id'       => $dinasId,
            'nama'           => $nama,
            'npsn'           => $npsn ?: null,
            'jenjang'        => $jenjang,
            'alamat'         => $alamat        ? trim((string) $alamat)        : null,
            'kota'           => $kota          ? trim((string) $kota)          : null,
            'telepon'        => $telepon       ? trim((string) $telepon)       : null,
            'email'          => $email         ? trim((string) $email)         : null,
            'kepala_sekolah' => $kepalaSekolah ? trim((string) $kepalaSekolah) : null,
            'is_active'      => true,
        ];

        if ($mode === 'update') {
            if (empty($npsn)) {
                throw new \Exception("NPSN kosong, baris dilewati untuk mode update");
            }

            $sekolah = Sekolah::updateOrCreate(
                ['npsn' => $npsn],
                $data
            );
        } else {
            // replace_all: selalu buat baru
            $sekolah = Sekolah::create($data);
        }

        // Auto-create operator user untuk sekolah ini
        $this->createOperatorUser($sekolah);
    }

    /**
     * Buat user operator (admin_sekolah) otomatis untuk sekolah yang diimport.
     * Jika user dengan sekolah_id ini sudah ada, skip.
     * Email: {npsn}@{OPS_EMAIL_DOMAIN dari .env}, password default: npsn.
     */
    private function createOperatorUser(Sekolah $sekolah): void
    {
        // Jika sudah ada user admin_sekolah untuk sekolah ini, skip
        $existing = User::where('sekolah_id', $sekolah->id)
                        ->where('role', User::ROLE_ADMIN_SEKOLAH)
                        ->first();

        if ($existing) {
            return;
        }

        $identifier  = $sekolah->npsn ?: \Illuminate\Support\Str::slug($sekolah->nama);
        $emailDomain = config('app.ops_email_domain', 'sekolah.local');
        $email       = "{$identifier}@{$emailDomain}";

        // Cek duplikat email, tambah suffix jika perlu
        $emailBase = $email;
        $counter   = 1;
        while (User::where('email', $email)->exists()) {
            $email = str_replace("@{$emailDomain}", "_{$counter}@{$emailDomain}", $emailBase);
            $counter++;
        }

        User::create([
            'name'        => "Operator {$sekolah->nama}",
            'email'       => $email,
            'password'    => Hash::make($sekolah->npsn ?: 'password'),
            'role'        => User::ROLE_ADMIN_SEKOLAH,
            'sekolah_id'  => $sekolah->id,
            'is_active'   => true,
        ]);
    }
}
