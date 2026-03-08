<?php

namespace App\Http\Controllers\Pengawas;

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
        $user = Auth::user();

        $data = $this->dashboardService->getPengawasDashboard($user->id);

        $sesiList = $data['sesiList'];
        $stats    = $data['stats'];

        return view('pengawas.dashboard', compact('sesiList', 'stats'));
    }
}
