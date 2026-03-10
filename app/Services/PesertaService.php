<?php

namespace App\Services;

use App\Repositories\PesertaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PesertaService
{
    public function __construct(
        protected PesertaRepository $repository
    ) {}

    /**
     * Get all peserta with optional filters and pagination.
     */
    public function getAll(array $filters = []): mixed
    {
        return $this->repository->getFiltered($filters);
    }

    /**
     * Get all peserta for dinas admin view (cross-school, with optional filters).
     */
    public function getAllForDinas(array $filters = []): mixed
    {
        return $this->repository->getAllFiltered(
            $filters['sekolah_id'] ?? null,
            $filters['q'] ?? null,
            $filters['kelas'] ?? null
        );
    }

    /**
     * Get peserta by sekolah with optional filters.
     */
    public function getBySekolah(string $sekolahId, array $filters = []): mixed
    {
        return $this->repository->getFiltered(
            $sekolahId,
            $filters['q'] ?? null,
            $filters['kelas'] ?? null,
            $filters['jurusan'] ?? null
        );
    }

    /**
     * Get a single peserta by ID with relations.
     */
    public function getById(string $id): mixed
    {
        return $this->repository->findWithRelations($id, ['sekolah']);
    }

    /**
     * Create a new peserta.
     */
    public function create(array $data): mixed
    {
        // Generate username_ujian if not provided
        if (empty($data['username_ujian'])) {
            $data['username_ujian'] = $data['nis'] ?? $data['nisn'] ?? Str::random(8);
        }

        // Generate and hash password
        if (empty($data['password_ujian'])) {
            $plainPassword = Str::random(6);
            $data['password_ujian'] = Hash::make($plainPassword);
            $data['password_plain'] = encrypt($plainPassword);
        } else {
            $plainPassword = $data['password_ujian'];
            $data['password_ujian'] = Hash::make($plainPassword);
            $data['password_plain'] = encrypt($plainPassword);
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return $this->repository->create($data);
    }

    /**
     * Update an existing peserta.
     */
    public function update(string $id, array $data): mixed
    {
        $peserta = $this->repository->findById($id);

        // Hash password if provided
        if (!empty($data['password_ujian'])) {
            $plainPassword = $data['password_ujian'];
            $data['password_ujian'] = Hash::make($plainPassword);
            $data['password_plain'] = encrypt($plainPassword);
        } else {
            unset($data['password_ujian'], $data['password_plain']);
        }

        $this->repository->update($peserta, $data);
        return $peserta->fresh();
    }

    /**
     * Delete a peserta.
     *
     * @throws ValidationException
     */
    public function delete(string $id): bool
    {
        $peserta = $this->repository->findById($id);
        $peserta->load('sesiPeserta');

        // Prevent deletion if peserta has active sessions
        $activeSessions = $peserta->sesiPeserta
            ->whereIn('status', ['login', 'mengerjakan'])
            ->count();

        if ($activeSessions > 0) {
            throw ValidationException::withMessages([
                'peserta' => 'Peserta tidak dapat dihapus karena sedang mengerjakan ujian.',
            ]);
        }

        return $this->repository->delete($peserta);
    }

    /**
     * Import peserta from parsed data (e.g., Excel rows).
     *
     * @param  array  $rows  Parsed rows
     * @param  array  $meta  Additional metadata (sekolah_id, etc.)
     * @return array  Import summary
     */
    public function importPeserta(array $rows, array $meta = []): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $nama = $row['nama'] ?? $row['nama_peserta'] ?? null;
                    if (empty($nama)) {
                        $skipped++;
                        $errors[] = "Baris " . ($index + 1) . ": Nama peserta kosong";
                        continue;
                    }

                    $nis = $row['nis'] ?? null;
                    $nisn = $row['nisn'] ?? null;

                    // Check duplicate by NIS or NISN within the same school
                    $sekolahId = $meta['sekolah_id'] ?? $row['sekolah_id'] ?? null;
                    if ($nis && $sekolahId) {
                        $existing = $this->repository->findByNisAndSekolah($nis, $sekolahId);
                        if ($existing) {
                            $skipped++;
                            $errors[] = "Baris " . ($index + 1) . ": NIS {$nis} sudah terdaftar";
                            continue;
                        }
                    }

                    $plainPassword = $row['password'] ?? Str::random(6);

                    $pesertaData = [
                        'nama'           => $nama,
                        'nis'            => $nis,
                        'nisn'           => $nisn,
                        'kelas'          => $row['kelas'] ?? null,
                        'jenis_kelamin'  => $row['jenis_kelamin'] ?? $row['jk'] ?? null,
                        'sekolah_id'     => $sekolahId,
                        'username_ujian' => $row['username'] ?? $nis ?? Str::random(8),
                        'password_ujian' => Hash::make($plainPassword),
                        'password_plain' => encrypt($plainPassword),
                        'is_active'      => true,
                    ];

                    $this->repository->create($pesertaData);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Baris " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'total'    => count($rows),
        ];
    }

    /**
     * Generate a new login token for a peserta.
     */
    public function generateToken(string $pesertaId): array
    {
        $peserta = $this->repository->findById($pesertaId);

        $plainPassword = Str::random(6);
        $this->repository->update($peserta, [
            'password_ujian' => Hash::make($plainPassword),
            'password_plain' => encrypt($plainPassword),
        ]);

        return [
            'peserta'  => $peserta->fresh(),
            'password' => $plainPassword,
        ];
    }

    /**
     * Create a new peserta for a specific sekolah (with credential generation).
     */
    public function createForSekolah(array $data, string $sekolahId, ?string $plainPassword = null): mixed
    {
        $data['sekolah_id'] = $sekolahId;

        $password = $plainPassword ?: Str::random(6);
        $data['username_ujian'] = \App\Models\Peserta::generateUsername(
            $data['nis'] ?? null,
            $data['nisn'] ?? null,
            $sekolahId
        );
        $data['password_ujian'] = Hash::make($password);
        $data['password_plain'] = encrypt($password);
        $data['is_active'] = $data['is_active'] ?? true;

        return $this->repository->create($data);
    }

    /**
     * Update peserta for a sekolah context (handles username/password regeneration).
     */
    public function updateForSekolah(string $id, array $data, ?string $plainPassword = null): mixed
    {
        $peserta = $this->repository->findById($id);

        // Update username_ujian if NIS changed
        if (isset($data['nis']) && $data['nis'] !== $peserta->nis) {
            $data['username_ujian'] = \App\Models\Peserta::generateUsername(
                $data['nis'],
                $data['nisn'] ?? null,
                $peserta->sekolah_id
            );
        }

        // Update password if provided
        if ($plainPassword) {
            $data['password_ujian'] = Hash::make($plainPassword);
            $data['password_plain'] = encrypt($plainPassword);
        }

        $this->repository->update($peserta, $data);
        return $peserta->fresh();
    }

    /**
     * Get distinct kelas list for a sekolah.
     */
    public function getKelasList(string $sekolahId): mixed
    {
        return $this->repository->getKelasBySekolah($sekolahId);
    }

    /**
     * Create an import job for peserta and dispatch the import.
     */
    public function createImportJob(array $data): \App\Models\ImportJob
    {
        $job = $this->repository->createImportJob($data);
        dispatch(new \App\Jobs\ImportPesertaJob($job));
        return $job;
    }
}
