<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index()
    {
        /** @var \App\Models\User $user */
        $user    = Auth::user();
        $sekolah = $user->sekolah;

        // Redirect admin_dinas/super_admin to dinas dashboard if no school assigned
        if (! $sekolah) {
            return redirect()->route('dinas.dashboard');
        }

        $data = $this->dashboardService->getSekolahDashboard($sekolah->id);

        return view('sekolah.dashboard', [
            'sekolah'       => $data['sekolah'],
            'stats'         => $data['stats'],
            'sesiMendatang' => $data['sesiMendatang'],
        ]);
    }
}
