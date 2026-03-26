<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use App\Services\SoalAssignmentService;
use App\Services\SoalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected SoalService $soalService,
        protected SoalAssignmentService $assignmentService
    ) {}

    public function index()
    {
        $userId = Auth::id();

        $totalSoal = Soal::where('created_by', $userId)->count();
        $soalVerified = Soal::where('created_by', $userId)->where('is_verified', true)->count();
        $soalPending = Soal::where('created_by', $userId)->where('is_verified', false)->count();

        // Assigned soal stats
        $assignedStats = $this->assignmentService->getAccessibleSoalCount($userId);
        $soalDitugaskan = $assignedStats['assigned_by_kategori'] + $assignedStats['assigned_direct'];

        $perTipe = Soal::where('created_by', $userId)
            ->selectRaw('tipe_soal, count(*) as total')
            ->groupBy('tipe_soal')
            ->pluck('total', 'tipe_soal');

        $perKategori = Soal::where('created_by', $userId)
            ->join('kategori_soal', 'soal.kategori_id', '=', 'kategori_soal.id')
            ->selectRaw('kategori_soal.nama, count(*) as total')
            ->groupBy('kategori_soal.nama')
            ->pluck('total', 'nama');

        $recentSoal = Soal::where('created_by', $userId)
            ->with('kategori')
            ->latest()
            ->take(10)
            ->get();

        return view('pembuat-soal.dashboard', compact(
            'totalSoal', 'soalVerified', 'soalPending', 'soalDitugaskan',
            'perTipe', 'perKategori', 'recentSoal'
        ));
    }
}
