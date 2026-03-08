<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index()
    {
        $data = $this->dashboardService->getDinasDashboard();

        return view('dinas.dashboard', [
            'stats'     => $data['stats'],
            'sesiAktif' => $data['sesiAktif'],
        ]);
    }
}
