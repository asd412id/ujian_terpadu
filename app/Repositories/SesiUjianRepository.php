<?php

namespace App\Repositories;

use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use App\Models\LogAktivitasUjian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
        return User::where('role', 'pengawas')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * Get eligible peserta IDs for a paket filter.
     */
    public function getEligiblePesertaIds(string $jenjang = null, ?string $sekolahId = null): \Illuminate\Support\Collection
    {
        return $this->eligiblePesertaQuery($jenjang, $sekolahId)
            ->orderBy('nama')
            ->pluck('id');
    }

    public function getAvailablePesertaIds(
        SesiUjian $sesi,
        ?string $jenjang,
        ?string $paketSekolahId,
        ?string $filterSekolahId,
        ?string $search
    ): \Illuminate\Support\Collection {
        return $this->eligiblePesertaQuery($jenjang, $paketSekolahId, $filterSekolahId, $search, $sesi)
            ->orderBy('nama')
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
        return $this->eligiblePesertaQuery($jenjang, $paketSekolahId, $filterSekolahId, $search, $sesi)
            ->with('sekolah')
            ->orderBy('nama')
            ->paginate($perPage, ['*'], 'available_page');
    }

    /**
     * Count available peserta (not enrolled in sesi).
     */
    public function countAvailablePeserta(SesiUjian $sesi, ?string $jenjang, ?string $paketSekolahId): int
    {
        return $this->eligiblePesertaQuery($jenjang, $paketSekolahId, null, null, $sesi)
            ->count();
    }

    private function eligiblePesertaQuery(
        ?string $jenjang = null,
        ?string $paketSekolahId = null,
        ?string $filterSekolahId = null,
        ?string $search = null,
        ?SesiUjian $excludeSesi = null,
    ): Builder {
        return Peserta::query()
            ->where('is_active', true)
            ->when($excludeSesi, fn (Builder $query) => $query->whereNotIn('id', $excludeSesi->sesiPeserta()->pluck('peserta_id')))
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
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('kelas', 'like', "%{$search}%")
                        ->orWhere('jurusan', 'like', "%{$search}%")
                        ->orWhereHas('sekolah', fn (Builder $sekolahQuery) => $sekolahQuery->where('nama', 'like', "%{$search}%"));
                });
            });
    }

    public function lockSesi(string $sesiId): SesiUjian
    {
        return SesiUjian::with('paket')
            ->lockForUpdate()
            ->findOrFail($sesiId);
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

        return SesiPeserta::query()->insertOrIgnore($records);
    }

    /**
     * Atomically transition sesi peserta to 'mengerjakan' with pessimistic lock.
     * Prevents race condition on concurrent start requests.
     */
    public function startSesiPesertaWithLock(string $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $locked = SesiPeserta::lockForUpdate()->findOrFail($id);
            if (!in_array($locked->status, ['terdaftar', 'belum_login', 'login'])) {
                return;
            }
            $locked->update($data);
        });
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
        return SesiPeserta::with(['sesi.paket.paketSoal', 'jawaban.soal'])->findOrFail($id);
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
     * Only returns sesi where the parent paket is published (aktif).
     */
    public function getAvailableSesiForPeserta(string $pesertaId): \Illuminate\Database\Eloquent\Collection
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['terdaftar', 'belum_login', 'login', 'mengerjakan'])
            ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung')
                ->whereHas('paket', fn ($p) => $p->where('status', 'aktif'))
            )
            ->get();
    }

    /**
     * Get completed sesi for a peserta (history).
     * Only returns sesi where the parent paket is published (aktif).
     */
    public function getCompletedSesiForPeserta(string $pesertaId): \Illuminate\Database\Eloquent\Collection
    {
        return SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $pesertaId)
            ->whereIn('status', ['submit', 'dinilai'])
            ->whereHas('sesi', fn ($q) => $q->whereHas('paket', fn ($p) => $p->where('status', 'aktif')))
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
