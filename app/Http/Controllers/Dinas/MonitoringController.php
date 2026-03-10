<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Services\MonitoringService;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService
    ) {}

    public function index()
    {
        $data = $this->monitoringService->getDashboardMonitoring();

        return view('dinas.monitoring.index', [
            'sekolahList' => $data['sekolahList'],
            'sesiList'    => $data['sesiList'],
            'summary'     => $data['summary'],
        ]);
    }

    public function sekolah(Sekolah $sekolah)
    {
        $sesiAktif = $this->monitoringService->getSesiAktif([
            'sekolah_id' => $sekolah->id,
        ]);

        return view('dinas.monitoring.sekolah', compact('sekolah', 'sesiAktif'));
    }

    public function sesi(Request $request, SesiUjian $sesi)
    {
        $filters = $request->only(['search', 'status', 'per_page']);
        $data = $this->monitoringService->getPesertaStatus($sesi->id, $filters);

        return view('dinas.monitoring.sesi', [
            'sesi'        => $data['sesi'],
            'alerts'      => $data['alerts'],
            'pesertaList' => $data['pesertaList'],
            'stats'       => $data['stats'],
            'filters'     => $filters,
        ]);
    }

    public function apiIndex()
    {
        $data = $this->monitoringService->getDashboardMonitoring();

        return response()->json([
            'sesiList' => $data['sesiList'],
            'summary'  => $data['summary'],
        ]);
    }

    public function sekolahAll()
    {
        $sekolahList = $this->monitoringService->getSekolahList();

        return view('dinas.monitoring.sekolah', compact('sekolahList'));
    }

    public function apiSekolahAll()
    {
        $data = $this->monitoringService->getSekolahMonitoringData();

        return response()->json([
            'sekolah' => $data['sekolah'],
            'summary' => $data['summary'],
        ]);
    }

    public function apiSesi(SesiUjian $sesi)
    {
        $data = $this->monitoringService->getSesiDetail($sesi->id);

        return response()->json($data);
    }
}
