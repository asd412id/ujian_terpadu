<?php

namespace App\Services;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Repositories\PaketUjianRepository;
use App\Repositories\KategoriSoalRepository;
use App\Repositories\SekolahRepository;
use App\Repositories\SoalRepository;
use App\Services\SesiUjianService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaketUjianService
{
    public function __construct(
        protected PaketUjianRepository $repository,
        protected KategoriSoalRepository $kategoriRepository,
        protected SekolahRepository $sekolahRepository,
        protected SoalRepository $soalRepository,
        protected SesiUjianService $sesiService
    ) {}

    /**
     * Get all paket ujian with counts, paginated (Dinas view).
     */
    public function getAllPaginated(int $perPage = 20): mixed
    {
        return $this->repository->getAll($perPage);
    }

    /**
     * Get active kategori list (for dropdowns/filters).
     */
    public function getActiveKategoris(): mixed
    {
        return $this->kategoriRepository->getActive();
    }

    /**
     * Get active sekolah list (for dropdowns/filters).
     */
    public function getActiveSekolahs(): mixed
    {
        return $this->sekolahRepository->getFiltered(true);
    }

    /**
     * Get a single paket ujian by ID with full relations for detail view.
     */
    public function getById(string $id): ?PaketUjian
    {
        return $this->repository->findWithRelations($id);
    }

    /**
     * Get bank soal not yet in paket (for the soal selection screen).
     */
    public function getBankSoalForPaket(PaketUjian $paket, int $perPage = 10): mixed
    {
        $excludeIds = $this->repository->getSoalIdsByPaket($paket->id);
        return $this->soalRepository->getByPaketUjian($paket->id, $excludeIds, $perPage);
    }

    /**
     * Create a new paket ujian.
     */
    public function createPaket(array $data, ?string $namaSesi = null, ?string $ruangan = null): PaketUjian
    {
        return DB::transaction(function () use ($data, $namaSesi, $ruangan) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $data['created_by']  = $user->id;
            $data['kode']        = strtoupper(Str::random(8));
            $data['status']      = 'draft';
            $data['jumlah_soal'] = 0;

            $paket = $this->repository->create($data);

            // Buat sesi default
            if ($namaSesi) {
                $this->repository->createSesi([
                    'paket_id'    => $paket->id,
                    'nama_sesi'   => $namaSesi,
                    'ruangan'     => $ruangan,
                    'waktu_mulai' => $data['tanggal_mulai'] ?? null,
                    'status'      => 'persiapan',
                ]);
            }

            return $paket;
        });
    }

    /**
     * Update an existing paket ujian.
     */
    public function updatePaket(PaketUjian $paket, array $data): PaketUjian
    {
        $this->repository->update($paket, $data);
        return $paket;
    }

    /**
     * Soft-delete a paket ujian.
     * Cancels all non-selesai sesi and marks related sesi_peserta as selesai.
     */
    public function softDeletePaket(PaketUjian $paket): bool
    {
        return DB::transaction(function () use ($paket) {
            // Cancel all sesi that haven't finished yet (persiapan + berlangsung)
            $paket->sesi()
                ->whereIn('status', ['persiapan', 'berlangsung'])
                ->update(['status' => 'selesai']);

            return (bool) $paket->delete();
        });
    }

    /**
     * Restore a soft-deleted paket ujian (back to draft).
     */
    public function restorePaket(PaketUjian $paket): bool
    {
        $paket->restore();
        $paket->update(['status' => 'draft']);
        return true;
    }

    /**
     * Permanently delete a paket ujian and ALL related data.
     * Model's forceDeleting event handles cleanup of sesi, sesi_peserta, jawaban, logs, paket_soal.
     */
    public function forceDeletePaket(PaketUjian $paket): bool
    {
        return DB::transaction(fn () => (bool) $paket->forceDelete());
    }

    /**
     * Get soft-deleted paket ujian, paginated.
     */
    public function getTrashedPaginated(int $perPage = 20): mixed
    {
        return PaketUjian::onlyTrashed()
            ->with(['sekolah', 'pembuat'])
            ->withCount(['paketSoal', 'sesi'])
            ->latest('deleted_at')
            ->paginate($perPage);
    }

    /**
     * Delete a paket ujian (legacy).
     */
    public function deletePaket(PaketUjian $paket): bool
    {
        return (bool) $this->repository->delete($paket);
    }

    /**
     * Archive a paket ujian (soft-delete).
     * Also cancels any pending sesi that haven't started yet.
     */
    public function archivePaket(PaketUjian $paket): PaketUjian
    {
        $this->sesiService->cancelPendingSesiByPaket($paket);
        $paket->delete();
        return $paket;
    }

    /**
     * Publish a paket ujian (set status to 'aktif').
     *
     * @throws ValidationException
     */
    public function publishPaket(PaketUjian $paket): PaketUjian
    {
        if ($this->repository->getSoalCount($paket) === 0) {
            throw ValidationException::withMessages([
                'paket' => 'Paket harus memiliki minimal 1 soal sebelum dipublikasikan.',
            ]);
        }

        $this->repository->update($paket, ['status' => 'aktif']);
        return $paket;
    }

    /**
     * Set paket ujian back to draft.
     */
    public function draftPaket(PaketUjian $paket): PaketUjian
    {
        $this->repository->update($paket, ['status' => 'draft']);
        return $paket;
    }

    /**
     * Add a soal to paket ujian.
     */
    public function addSoalToPaket(PaketUjian $paket, string $soalId): bool
    {
        return $this->repository->attachSoal($paket, $soalId);
    }

    /**
     * Remove a soal from paket ujian.
     */
    public function removeSoalFromPaket(PaketUjian $paket, string $soalId): bool
    {
        return $this->repository->detachSoal($paket, $soalId);
    }

    /**
     * Sync soal selection for a paket (bulk add/remove).
     */
    public function syncSoalPaket(PaketUjian $paket, array $soalIds): void
    {
        DB::transaction(function () use ($paket, $soalIds) {
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
                        'id'         => (string) \Illuminate\Support\Str::uuid(),
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
        });
    }

    /**
     * Get paket ujian for a specific sekolah, filtered by jenjang for global paket.
     */
    public function getForSekolah(string $sekolahId, ?string $jenjang = null, int $perPage = 20): mixed
    {
        return $this->repository->getForSekolah($sekolahId, $jenjang, $perPage);
    }

    /**
     * Manage (sync) soal for a paket ujian.
     */
    public function manageSoal(string $paketId, array $soalIds): mixed
    {
        $paket = $this->repository->findById($paketId);

        return DB::transaction(function () use ($paket, $soalIds) {
            $syncData = [];
            foreach ($soalIds as $index => $item) {
                if (is_array($item)) {
                    $soalId = $item['soal_id'] ?? $item['id'];
                    $syncData[$soalId] = [
                        'urutan'         => $item['urutan'] ?? $index + 1,
                        'bobot_override' => $item['bobot_override'] ?? null,
                    ];
                } else {
                    $syncData[$item] = [
                        'urutan' => $index + 1,
                    ];
                }
            }

            $paket->soal()->sync($syncData);
            return $paket->fresh(['soal']);
        });
    }

    /**
     * Get soal list for a specific paket.
     */
    public function getSoalByPaket(string $paketId): mixed
    {
        $paket = $this->repository->findWithRelations($paketId);
        return $paket ? $paket->paketSoal : collect();
    }

    /**
     * Get paket detail with sesi peserta and paket soal relations.
     * Pass sekolahId to filter peserta count to that school only.
     */
    public function getDetail(string $id, ?string $sekolahId = null): ?PaketUjian
    {
        return $this->repository->findWithSesiPeserta($id, $sekolahId);
    }

    /**
     * Register peserta to a sesi ujian.
     */
    public function registerPeserta(string $sesiId, array $pesertaIds): int
    {
        return $this->repository->daftarPesertaToSesi($sesiId, $pesertaIds);
    }
}
