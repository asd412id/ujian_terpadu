<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\SesiUjian;
use App\Services\MonitoringService;
use Illuminate\Support\Facades\Auth;

class MonitoringRuangController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService
    ) {}

    public function index(SesiUjian $sesi)
    {
        $data = $this->monitoringService->getPesertaByRuang($sesi->id);

        $sesi          = $data['sesi'];
        $statsPeserta  = $data['statsPeserta'];

        return view('pengawas.monitoring-ruang', compact('sesi', 'statsPeserta'));
    }
}
