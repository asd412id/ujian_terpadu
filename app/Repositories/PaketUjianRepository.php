<?php

namespace App\Repositories;

use App\Models\PaketUjian;
use App\Models\PaketSoal;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaketUjianRepository
{
    public function __construct(
        protected PaketUjian $model
    ) {}

    /**
     * Get all paket ujian with counts (Dinas view).
     */
    public function getAll(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['sekolah', 'pembuat'])
            ->withCount(['paketSoal', 'sesi'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get filtered paket ujian for a sekolah (active only, includes shared/null sekolah).
     * Global paket (null sekolah_id) are further filtered by jenjang to avoid showing
     * e.g. SD paket to SMP schools.
     */
    public function getForSekolah(string $sekolahId, ?string $jenjang = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with([
                'sesi' => fn ($q) => $q->with([
                    'sesiPeserta' => fn ($q) => $q->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $sekolahId)),
                ]),
                'paketSoal',
            ])
            ->where(function ($q) use ($sekolahId, $jenjang) {
                // Paket milik sekolah ini langsung
                $q->where('sekolah_id', $sekolahId);

                // Paket global (null sekolah_id) — filter by jenjang if known
                $q->orWhere(function ($q2) use ($jenjang) {
                    $q2->whereNull('sekolah_id');
                    if ($jenjang) {
                        // Tampilkan global paket yang jenjangnya cocok ATAU 'SEMUA'
                        $q2->where(fn ($q3) => $q3->where('jenjang', $jenjang)->orWhere('jenjang', 'SEMUA'));
                    }
                });
            })
            ->where('status', 'aktif')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find paket by ID.
     */
    public function findById(string $id): ?PaketUjian
    {
        return $this->model->find($id);
    }

    /**
     * Find paket with full relations for detail view.
     */
    public function findWithRelations(string $id): ?PaketUjian
    {
        return $this->model
            ->with(['paketSoal.soal.kategori', 'sesi.sesiPeserta', 'sekolah'])
            ->find($id);
    }

    /**
     * Find paket with sesi and peserta (sekolah view).
     * If sekolahId is provided, only load sesiPeserta belonging to that school.
     */
    public function findWithSesiPeserta(string $id, ?string $sekolahId = null): ?PaketUjian
    {
        return $this->model
            ->with([
                'sesi' => fn ($q) => $q->with([
                    'sesiPeserta' => fn ($q) => $sekolahId
                        ? $q->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $sekolahId))
                        : $q->with('peserta'),
                ]),
                'paketSoal',
            ])
            ->find($id);
    }

    /**
     * Create a new paket ujian.
     */
    public function create(array $data): PaketUjian
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing paket ujian.
     */
    public function update(PaketUjian $paket, array $data): bool
    {
        return $paket->update($data);
    }

    /**
     * Delete a paket ujian.
     */
    public function delete(PaketUjian $paket): ?bool
    {
        return $paket->delete();
    }

    /**
     * Attach a soal to paket (if not already attached).
     * Returns true if the soal was newly attached, false if already existed.
     */
    public function attachSoal(PaketUjian $paket, string $soalId): bool
    {
        $exists = PaketSoal::where('paket_id', $paket->id)
            ->where('soal_id', $soalId)
            ->exists();

        if ($exists) {
            return false;
        }

        $maxNomor = PaketSoal::where('paket_id', $paket->id)->max('nomor_urut') ?? 0;

        PaketSoal::create([
            'paket_id'   => $paket->id,
            'soal_id'    => $soalId,
            'nomor_urut' => $maxNomor + 1,
        ]);

        $paket->increment('jumlah_soal');

        return true;
    }

    /**
     * Detach a soal from paket.
     */
    public function detachSoal(PaketUjian $paket, string $soalId): bool
    {
        $deleted = PaketSoal::where('paket_id', $paket->id)
            ->where('soal_id', $soalId)
            ->delete();

        if ($deleted) {
            $paket->decrement('jumlah_soal');
            return true;
        }

        return false;
    }

    /**
     * Get the count of soal in a paket.
     */
    public function getSoalCount(PaketUjian $paket): int
    {
        return $paket->paketSoal()->count();
    }

    /**
     * Get soal IDs attached to a paket.
     */
    public function getSoalIdsByPaket(string $paketId): array
    {
        return PaketSoal::where('paket_id', $paketId)->pluck('soal_id')->toArray();
    }

    /**
     * Create a default sesi for a paket.
     */
    public function createSesi(array $data): SesiUjian
    {
        return SesiUjian::create($data);
    }

    /**
     * Register peserta to a sesi.
     */
    public function daftarPesertaToSesi(string $sesiId, array $pesertaIds): int
    {
        // Get existing peserta IDs in this sesi to avoid duplicates
        $existingIds = SesiPeserta::where('sesi_id', $sesiId)
            ->whereIn('peserta_id', $pesertaIds)
            ->pluck('peserta_id')
            ->toArray();

        $newIds = array_diff($pesertaIds, $existingIds);
        if (empty($newIds)) {
            return 0;
        }

        $insertRows = [];
        $now = now();
        foreach ($newIds as $pesertaId) {
            $insertRows[] = [
                'id'          => (string) Str::uuid(),
                'sesi_id'     => $sesiId,
                'peserta_id'  => $pesertaId,
                'status'      => 'belum_login',
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // Insert in chunks to avoid parameter limit
        foreach (array_chunk($insertRows, 500) as $chunk) {
            SesiPeserta::insert($chunk);
        }

        return count($newIds);
    }

    /**
     * Find a sesi ujian by ID.
     */
    public function findSesiById(string $sesiId): ?SesiUjian
    {
        return SesiUjian::findOrFail($sesiId);
    }

    /**
     * Get soft-deleted paket ujian, paginated.
     */
    public function getTrashedPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model::onlyTrashed()
            ->with(['sekolah', 'pembuat'])
            ->withCount(['paketSoal', 'sesi'])
            ->latest('deleted_at')
            ->paginate($perPage);
    }

    /**
     * Force-delete all trashed paket ujian.
     * Returns the count of deleted paket.
     */
    public function forceDeleteAllTrashed(): int
    {
        $count = 0;

        $this->model::onlyTrashed()->chunkById(50, function ($pakets) use (&$count) {
            foreach ($pakets as $paket) {
                $paket->forceDelete();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Sync soal selection for a paket (bulk add/remove).
     */
    public function syncSoalPaket(PaketUjian $paket, array $soalIds): void
    {
        $currentIds = $paket->paketSoal()->pluck('soal_id')->toArray();

        $toAdd    = array_diff($soalIds, $currentIds);
        $toRemove = array_diff($currentIds, $soalIds);

        if (!empty($toRemove)) {
            PaketSoal::where('paket_id', $paket->id)
                ->whereIn('soal_id', $toRemove)
                ->delete();
        }

        $maxNomor = PaketSoal::where('paket_id', $paket->id)->max('nomor_urut') ?? 0;
        if (!empty($toAdd)) {
            $insertRows = [];
            foreach ($toAdd as $soalId) {
                $maxNomor++;
                $insertRows[] = [
                    'id'         => (string) Str::uuid(),
                    'paket_id'   => $paket->id,
                    'soal_id'    => $soalId,
                    'nomor_urut' => $maxNomor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            PaketSoal::insert($insertRows);
        }

        $paket->update(['jumlah_soal' => PaketSoal::where('paket_id', $paket->id)->count()]);
    }

    /**
     * Get list of pengawas users for dropdown.
     */
    public function getPengawasList(): Collection
    {
        return User::where('role', 'pengawas')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * Clone a paket ujian (copy attributes + paket_soal, optionally sesi).
     * Returns the newly created paket.
     */
    public function clonePaket(PaketUjian $source, bool $withSesi = false): PaketUjian
    {
        $newPaket = $this->model->create([
            'sekolah_id'      => $source->sekolah_id,
            'created_by'      => $source->created_by,
            'nama'            => $source->nama . ' (Salinan)',
            'kode'            => strtoupper(Str::random(8)),  // 8 chars, fits varchar(20)
            'jenis_ujian'     => $source->jenis_ujian,
            'jenjang'         => $source->jenjang,
            'deskripsi'       => $source->deskripsi,
            'durasi_menit'    => $source->durasi_menit,
            'jumlah_soal'     => $source->jumlah_soal,
            'acak_soal'       => $source->acak_soal,
            'acak_opsi'       => $source->acak_opsi,
            'tampilkan_hasil' => $source->tampilkan_hasil,
            'boleh_kembali'   => $source->boleh_kembali,
            'anti_curang'     => $source->anti_curang,
            'max_peserta'     => $source->max_peserta,
            'tanggal_mulai'   => $source->tanggal_mulai,
            'tanggal_selesai' => $source->tanggal_selesai,
            'status'          => 'draft',
        ]);

        // Clone paket_soal rows via bulk insert
        $paketSoals = PaketSoal::where('paket_id', $source->id)
            ->orderBy('nomor_urut')
            ->get();

        if ($paketSoals->isNotEmpty()) {
            $insertRows = [];
            foreach ($paketSoals as $ps) {
                $insertRows[] = [
                    'id'             => (string) Str::uuid(),
                    'paket_id'       => $newPaket->id,
                    'soal_id'        => $ps->soal_id,
                    'nomor_urut'     => $ps->nomor_urut,
                    'bobot_override' => $ps->bobot_override,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
            PaketSoal::insert($insertRows);
        }

        // Optionally clone sesi (without peserta)
        if ($withSesi) {
            $sesiList = SesiUjian::where('paket_id', $source->id)->get();
            foreach ($sesiList as $sesi) {
                SesiUjian::create([
                    'paket_id'           => $newPaket->id,
                    'nama_sesi'          => $sesi->nama_sesi,
                    'ruangan'            => $sesi->ruangan,
                    'pengawas_id'        => $sesi->pengawas_id,
                    'waktu_mulai'        => $sesi->waktu_mulai,
                    'waktu_selesai'      => $sesi->waktu_selesai,
                    'status'             => 'persiapan',
                    'kapasitas'          => $sesi->kapasitas,
                    'is_peserta_override' => $sesi->is_peserta_override,
                ]);
            }
        }

        return $newPaket;
    }
}
