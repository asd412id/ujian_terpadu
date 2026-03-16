<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Services\MonitoringService;
use App\Services\SesiUjianService;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService,
        protected SesiUjianService $sesiUjianService,
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
        $filters = $request->only(['search', 'status', 'per_page', 'sekolah_id']);
        $data = $this->monitoringService->getPesertaStatus($sesi->id, $filters);

        return view('dinas.monitoring.sesi', [
            'sesi'        => $data['sesi'],
            'alerts'      => $data['alerts'],
            'pesertaList' => $data['pesertaList'],
            'stats'       => $data['stats'],
            'sekolahList' => $data['sekolahList'],
            'pesertaLive' => $data['pesertaLive'] ?? [],
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
        $data = $this->monitoringService->getSesiStats($sesi->id);

        return response()->json($data);
    }

    public function resetPesertaUjian(SesiUjian $sesi, SesiPeserta $sesiPeserta)
    {
        abort_unless($sesiPeserta->sesi_id === $sesi->id, 403, 'Peserta bukan bagian dari sesi ini.');

        // Hanya bisa reset peserta yang sudah submit/dinilai atau sedang mengerjakan
        abort_unless(
            in_array($sesiPeserta->status, ['submit', 'dinilai', 'mengerjakan', 'login']),
            422,
            'Peserta dengan status "' . $sesiPeserta->status . '" tidak dapat direset.'
        );

        $this->sesiUjianService->resetSesiPeserta($sesiPeserta);

        return back()->with('success', 'Ujian peserta "' . ($sesiPeserta->peserta?->nama ?? '') . '" berhasil direset. Peserta dapat mengulang ujian.');
    }
}
