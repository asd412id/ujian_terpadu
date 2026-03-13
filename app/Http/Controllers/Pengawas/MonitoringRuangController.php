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
        abort_unless($sesi->pengawas_id === Auth::id(), 403, 'Anda bukan pengawas sesi ini.');

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
        abort_unless($sesi->pengawas_id === Auth::id(), 403, 'Anda bukan pengawas sesi ini.');

        $data = $this->monitoringService->getPesertaByRuang($sesi->id);

        return response()->json([
            'stats'        => $data['statsPeserta'],
            'peserta_live' => $data['pesertaLive'] ?? [],
        ]);
    }
}
