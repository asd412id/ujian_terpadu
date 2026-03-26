<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\KategoriSoal;
use App\Models\Soal;
use App\Models\User;
use App\Services\SoalAssignmentService;
use App\Support\HtmlDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PenugasanSoalController extends Controller
{
    public function __construct(
        protected SoalAssignmentService $assignmentService
    ) {}

    /**
     * List semua pembuat soal dengan ringkasan assignment.
     */
    public function index()
    {
        $users = $this->assignmentService->getPembuatSoalWithAssignments();
        return view('dinas.penugasan.index', compact('users'));
    }

    /**
     * Detail assignment untuk satu user.
     */
    public function show(User $user)
    {
        abort_unless($user->role === User::ROLE_PEMBUAT_SOAL, 404, 'User bukan pembuat soal.');

        $assignments = $this->assignmentService->getAssignmentDetail($user);
        $allKategori = KategoriSoal::where('is_active', true)->orderBy('urutan')->get();
        $assignedKategoriIds = $assignments['kategori']->pluck('id')->toArray();

        return view('dinas.penugasan.show', compact('user', 'assignments', 'allKategori', 'assignedKategoriIds'));
    }

    /**
     * Update kategori assignment untuk user.
     */
    public function updateKategori(Request $request, User $user)
    {
        abort_unless($user->role === User::ROLE_PEMBUAT_SOAL, 404);

        $validated = $request->validate([
            'kategori_ids'   => 'nullable|array',
            'kategori_ids.*' => 'exists:kategori_soal,id',
        ]);

        $this->assignmentService->syncKategoriAssignment(
            $user,
            $validated['kategori_ids'] ?? [],
            Auth::id()
        );

        return redirect()->route('dinas.penugasan.show', $user->id)
                         ->with('success', 'Penugasan kategori berhasil diperbarui.');
    }

    /**
     * Update individual soal assignment untuk user.
     */
    public function updateSoal(Request $request, User $user)
    {
        abort_unless($user->role === User::ROLE_PEMBUAT_SOAL, 404);

        $validated = $request->validate([
            'soal_ids'   => 'nullable|array',
            'soal_ids.*' => 'exists:soal,id',
        ]);

        $this->assignmentService->syncSoalAssignment(
            $user,
            $validated['soal_ids'] ?? [],
            Auth::id()
        );

        return redirect()->route('dinas.penugasan.show', $user->id)
                         ->with('success', 'Penugasan soal individual berhasil diperbarui.');
    }

    /**
     * Add soal to assignment (AJAX).
     */
    public function addSoal(Request $request, User $user)
    {
        abort_unless($user->role === User::ROLE_PEMBUAT_SOAL, 404);

        $validated = $request->validate([
            'soal_ids'   => 'required|array',
            'soal_ids.*' => 'exists:soal,id',
        ]);

        $this->assignmentService->addSoalAssignment(
            $user,
            $validated['soal_ids'],
            Auth::id()
        );

        return response()->json(['message' => 'Soal berhasil ditugaskan.']);
    }

    /**
     * Remove soal from assignment (AJAX).
     */
    public function removeSoal(Request $request, User $user)
    {
        abort_unless($user->role === User::ROLE_PEMBUAT_SOAL, 404);

        $validated = $request->validate([
            'soal_ids'   => 'required|array',
            'soal_ids.*' => 'exists:soal,id',
        ]);

        $this->assignmentService->removeSoalAssignment($user, $validated['soal_ids']);

        return response()->json(['message' => 'Penugasan soal berhasil dihapus.']);
    }

    /**
     * API: search soal for individual assignment.
     */
    public function apiSearchSoal(Request $request)
    {
        $soal = $this->assignmentService->searchSoalForAssignment(
            search: $request->input('search'),
            kategoriId: $request->input('kategori_id'),
            perPage: 20
        );

        $tipeLabels = [
            'pg' => 'Pilihan Ganda', 'pilihan_ganda' => 'Pilihan Ganda',
            'pg_kompleks' => 'PG Kompleks', 'pilihan_ganda_kompleks' => 'PG Kompleks',
            'benar_salah' => 'Benar / Salah',
            'isian' => 'Isian Singkat', 'essay' => 'Essay', 'menjodohkan' => 'Menjodohkan',
        ];

        $soal->getCollection()->transform(function ($item) use ($tipeLabels) {
            $item->pertanyaan_plain = HtmlDisplay::plainText($item->pertanyaan, 150);
            $item->tipe_soal_label = $tipeLabels[$item->tipe_soal] ?? $item->tipe_soal;
            $item->kategori_nama = $item->kategori->nama ?? "\u{2014}";
            $item->pembuat_nama = $item->pembuat->name ?? "\u{2014}";
            return $item;
        });

        return response()->json($soal);
    }
}
