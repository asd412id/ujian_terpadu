<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Services\MonitoringService;

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

    public function sesi(SesiUjian $sesi)
    {
        $data = $this->monitoringService->getPesertaStatus($sesi->id);

        return view('dinas.monitoring.sesi', [
            'sesi'        => $data['sesi'],
            'alerts'      => $data['alerts'],
            'pesertaList' => $data['pesertaList'],
            'stats'       => $data['stats'],
        ]);
    }

    public function apiIndex()
    {
        $sesiAktif = $this->monitoringService->getSesiAktif();

        return response()->json([
            'sesi'  => $sesiAktif,
            'total' => $sesiAktif->count(),
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
