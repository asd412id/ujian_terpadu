<?php

namespace App\Services;

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
        return $this->repository->getTrashedPaginated($perPage);
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
        DB::transaction(fn () => $this->repository->syncSoalPaket($paket, $soalIds));
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

    /**
     * Register peserta to a sesi ujian and return count + sesi name.
     */
    public function registerPesertaWithSesiName(string $sesiId, array $pesertaIds): array
    {
        $count = $this->repository->daftarPesertaToSesi($sesiId, $pesertaIds);
        $sesi = $this->repository->findSesiById($sesiId);

        return [
            'count'     => $count,
            'sesi_nama' => $sesi->nama_sesi,
        ];
    }

    /**
     * Get list of pengawas users for dropdown.
     */
    public function getPengawasList(): mixed
    {
        return $this->repository->getPengawasList();
    }

    /**
     * Get filtered bank soal for the paket soal picker (AJAX endpoint).
     */
    public function getBankSoalFiltered(PaketUjian $paket, array $filters): array
    {
        $result = $this->soalRepository->getBankSoalFiltered($filters);

        if ($result['type'] === 'all') {
            $soal = $result['data'];
            return [
                'data' => $soal->map(fn ($s) => $this->mapSoalToArray($s)),
                'meta' => ['total' => $soal->count(), 'all' => true],
            ];
        }

        $paginated = $result['data'];
        return [
            'data' => collect($paginated->items())->map(fn ($s) => $this->mapSoalToArray($s))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
            ],
        ];
    }

    private function mapSoalToArray($s): array
    {
        return [
            'id'         => $s->id,
            'pertanyaan' => strip_tags($s->pertanyaan),
            'tipe_soal'  => $s->tipe_soal,
            'bobot'      => $s->bobot,
            'kategori'   => $s->kategori->nama ?? 'Tanpa Kategori',
            'kategoriId' => $s->kategori_id ?? '_none',
        ];
    }
}
