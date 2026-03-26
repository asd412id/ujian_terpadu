<?php

namespace App\Services;

use App\Models\KategoriSoal;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SoalAssignmentService
{
    /**
     * Get all pembuat soal users with assignment counts.
     */
    public function getPembuatSoalWithAssignments(): Collection
    {
        return User::where('role', User::ROLE_PEMBUAT_SOAL)
            ->where('is_active', true)
            ->withCount(['assignedKategoriSoal', 'assignedSoal'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get assignment detail for a specific user.
     */
    public function getAssignmentDetail(User $user): array
    {
        return [
            'kategori' => $user->assignedKategoriSoal()
                ->withCount('soal')
                ->orderBy('urutan')
                ->get(),
            'soal' => $user->assignedSoal()
                ->with('kategori')
                ->latest('soal_user.created_at')
                ->get(),
        ];
    }

    /**
     * Sync kategori assignments for a user.
     */
    public function syncKategoriAssignment(User $user, array $kategoriIds, string $assignedBy): void
    {
        $pivotData = collect($kategoriIds)->mapWithKeys(fn ($id) => [
            $id => ['assigned_by' => $assignedBy],
        ])->all();

        $user->assignedKategoriSoal()->sync($pivotData);
    }

    /**
     * Sync individual soal assignments for a user.
     */
    public function syncSoalAssignment(User $user, array $soalIds, string $assignedBy): void
    {
        $pivotData = collect($soalIds)->mapWithKeys(fn ($id) => [
            $id => ['assigned_by' => $assignedBy],
        ])->all();

        $user->assignedSoal()->sync($pivotData);
    }

    /**
     * Add kategori assignments (without removing existing).
     */
    public function addKategoriAssignment(User $user, array $kategoriIds, string $assignedBy): void
    {
        foreach ($kategoriIds as $id) {
            $user->assignedKategoriSoal()->syncWithoutDetaching([
                $id => ['assigned_by' => $assignedBy],
            ]);
        }
    }

    /**
     * Add individual soal assignments (without removing existing).
     */
    public function addSoalAssignment(User $user, array $soalIds, string $assignedBy): void
    {
        foreach ($soalIds as $id) {
            $user->assignedSoal()->syncWithoutDetaching([
                $id => ['assigned_by' => $assignedBy],
            ]);
        }
    }

    /**
     * Remove specific kategori assignments.
     */
    public function removeKategoriAssignment(User $user, array $kategoriIds): void
    {
        $user->assignedKategoriSoal()->detach($kategoriIds);
    }

    /**
     * Remove specific soal assignments.
     */
    public function removeSoalAssignment(User $user, array $soalIds): void
    {
        $user->assignedSoal()->detach($soalIds);
    }

    /**
     * Get count summary for a user (soal accessible = own + assigned).
     */
    public function getAccessibleSoalCount(string $userId): array
    {
        $ownCount = Soal::where('created_by', $userId)->count();

        $assignedByKategoriCount = Soal::whereIn('kategori_id', function ($q) use ($userId) {
            $q->select('kategori_soal_id')
              ->from('kategori_soal_user')
              ->where('user_id', $userId);
        })->where('created_by', '!=', $userId)->count();

        $assignedDirectCount = DB::table('soal_user')
            ->where('user_id', $userId)
            ->whereNotIn('soal_id', function ($q) use ($userId) {
                $q->select('id')->from('soal')->where('created_by', $userId);
            })
            ->count();

        return [
            'own'                  => $ownCount,
            'assigned_by_kategori' => $assignedByKategoriCount,
            'assigned_direct'      => $assignedDirectCount,
            'total'                => $ownCount + $assignedByKategoriCount + $assignedDirectCount,
        ];
    }

    /**
     * Search soal for assignment (admin use).
     */
    public function searchSoalForAssignment(
        ?string $search = null,
        ?string $kategoriId = null,
        int $perPage = 20
    ): mixed {
        return Soal::with(['kategori', 'pembuat'])
            ->when($search, fn ($q) => $q->where('pertanyaan', 'like', "%{$search}%"))
            ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            ->where('is_active', true)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
