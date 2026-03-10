<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\SesiUjian;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringRuangController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService
    ) {}

    public function index(Request $request, SesiUjian $sesi)
    {
        $filters = $request->only(['search', 'status', 'per_page']);
        $data = $this->monitoringService->getPesertaByRuang($sesi->id, $filters);

        return view('pengawas.monitoring-ruang', [
            'sesi'             => $data['sesi'],
            'statsPeserta'     => $data['statsPeserta'],
            'pesertaPaginated' => $data['pesertaPaginated'],
            'filters'          => $filters,
        ]);
    }

    public function apiSesi(SesiUjian $sesi)
    {
        $data = $this->monitoringService->getSesiDetail($sesi->id);

        return response()->json($data);
    }
}
