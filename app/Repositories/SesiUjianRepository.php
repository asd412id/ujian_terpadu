<?php

namespace App\Repositories;

use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SesiUjianRepository
{
    public function __construct(
        protected SesiPeserta $model
    ) {}

    /**
     * Create a sesi ujian record.
     */
    public function createSesi(array $data): SesiUjian
    {
        return SesiUjian::create($data);
    }

    /**
     * Get list of pengawas users for dropdown.
     */
    public function getPengawasList(): Collection
    {
        return User::where('role', 'pengawas')->orderBy('name')->get();
    }

    /**
     * Get eligible peserta IDs for a paket filter.
     */
    public function getEligiblePesertaIds(string $jenjang = null, ?string $sekolahId = null): \Illuminate\Support\Collection
    {
        return Peserta::where('is_active', true)
            ->whereHas('sekolah', function ($q) use ($jenjang, $sekolahId) {
                if ($jenjang && strtoupper($jenjang) !== 'SEMUA') {
                    $q->where('jenjang', $jenjang);
                }
                if ($sekolahId) {
                    $q->where('id', $sekolahId);
                }
            })
            ->pluck('id');
    }

    /**
     * Get available peserta (not enrolled in sesi), paginated.
     */
    public function getAvailablePeserta(
        SesiUjian $sesi,
        ?string $jenjang,
        ?string $paketSekolahId,
        ?string $filterSekolahId,
        ?string $search,
        int $perPage = 50
    ) {
        $existingIds = $sesi->sesiPeserta()->pluck('peserta_id');

        return Peserta::where('is_active', true)
            ->whereNotIn('id', $existingIds)
            ->whereHas('sekolah', function ($q) use ($jenjang, $paketSekolahId, $filterSekolahId) {
                if ($jenjang && strtoupper($jenjang) !== 'SEMUA') {
                    $q->where('jenjang', $jenjang);
                }
                if ($paketSekolahId) {
                    $q->where('id', $paketSekolahId);
                }
                if ($filterSekolahId) {
                    $q->where('id', $filterSekolahId);
                }
            })
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            }))
            ->with('sekolah')
            ->orderBy('nama')
            ->paginate($perPage, ['*'], 'available_page');
    }

    /**
     * Count available peserta (not enrolled in sesi).
     */
    public function countAvailablePeserta(SesiUjian $sesi, ?string $jenjang, ?string $paketSekolahId): int
    {
        $existingIds = $sesi->sesiPeserta()->pluck('peserta_id');

        return Peserta::where('is_active', true)
            ->whereNotIn('id', $existingIds)
            ->whereHas('sekolah', function ($q) use ($jenjang, $paketSekolahId) {
                if ($jenjang && strtoupper($jenjang) !== 'SEMUA') {
                    $q->where('jenjang', $jenjang);
                }
                if ($paketSekolahId) {
                    $q->where('id', $paketSekolahId);
                }
            })
            ->count();
    }

    /**
     * Insert sesi peserta records in bulk.
     */
    public function insertSesiPeserta(string $sesiId, \Illuminate\Support\Collection $pesertaIds): int
    {
        if ($pesertaIds->isEmpty()) {
            return 0;
        }

        $records = $pesertaIds->map(fn($id) => [
            'id'         => (string) Str::uuid(),
            'sesi_id'    => $sesiId,
            'peserta_id' => $id,
            'status'     => 'terdaftar',
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        SesiPeserta::insert($records);

        return count($records);
    }

    /**
     * Find sesi peserta with paket relation (for ujian).
     */
    public function findSesiPesertaWithPaket(string $id): SesiPeserta
    {
        return SesiPeserta::with(['sesi.paket'])->findOrFail($id);
    }

    /**
     * Find sesi peserta with jawaban and soal (for hasil).
     */
    public function findSesiPesertaWithJawaban(string $id): SesiPeserta
    {
        return SesiPeserta::with(['sesi.paket', 'jawaban.soal'])->findOrFail($id);
    }

    /**
     * Log aktivitas ujian.
     */
    public function logAktivitas(array $data): LogAktivitasUjian
    {
        return LogAktivitasUjian::create($data);
    }

    /**
     * Get available (active) sesi for a peserta.
     */
    public function getAvailableSesiForPeserta(string $pesertaId): \Illuminate\Database\Eloquent\Collection
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['terdaftar', 'belum_login', 'login', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))
            ->get();
    }

    /**
     * Get completed sesi for a peserta (history).
     */
    public function getCompletedSesiForPeserta(string $pesertaId): \Illuminate\Database\Eloquent\Collection
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->where('status', 'submit')
            ->latest('submit_at')
            ->get();
    }

    /**
     * Find sesi with paket, sekolah, and sesiPeserta.peserta (for kartu login).
     */
    public function findSesiWithPeserta(string $sesiId): SesiUjian
    {
        return SesiUjian::with(['paket.sekolah', 'sesiPeserta.peserta'])->findOrFail($sesiId);
    }
}
