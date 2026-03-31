<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService
    ) {}

    public function index()
    {
        $sekolah = $this->getSekolah();
        if (! $sekolah) {
            return redirect()->route('dinas.dashboard');
        }

        $data = $this->monitoringService->getSekolahMonitoring($sekolah->id);

        return view('sekolah.monitoring.index', [
            'sekolah'  => $sekolah,
            'sesiList' => $data['sesiList'],
            'summary'  => $data['summary'],
        ]);
    }

    public function sesi(Request $request, SesiUjian $sesi)
    {
        $sekolah = $this->getSekolah();
        if (! $sekolah) {
            return redirect()->route('dinas.dashboard');
        }

        $this->authorizeSesi($sesi, $sekolah);

        $filters = $request->only(['search', 'status', 'per_page']);
        $filters['sekolah_id'] = $sekolah->id;
        $data = $this->monitoringService->getPesertaStatus($sesi->id, $filters);

        return view('sekolah.monitoring.sesi', [
            'sesi'        => $data['sesi'],
            'alerts'      => $data['alerts'],
            'pesertaList' => $data['pesertaList'],
            'stats'       => $data['stats'],
            'filters'     => $filters,
        ]);
    }

    public function apiIndex()
    {
        $sekolah = $this->getSekolah();
        if (! $sekolah) {
            return response()->json(['message' => 'Sekolah tidak ditemukan'], 403);
        }

        $data = $this->monitoringService->getSekolahMonitoring($sekolah->id);

        return response()->json([
            'sesiList' => $data['sesiList'],
            'summary'  => $data['summary'],
        ]);
    }

    public function apiSesi(SesiUjian $sesi)
    {
        $sekolah = $this->getSekolah();
        if (! $sekolah) {
            return response()->json(['message' => 'Sekolah tidak ditemukan'], 403);
        }

        $this->authorizeSesi($sesi, $sekolah);

        $data = $this->monitoringService->getSesiStats($sesi->id, $sekolah->id);

        if (($sesi->status !== 'selesai' || ! ($sesi->paket->tampilkan_hasil ?? false)) && ! empty($data['peserta_live'])) {
            foreach ($data['peserta_live'] as &$pesertaLive) {
                $pesertaLive['nilai_akhir'] = null;
            }
            unset($pesertaLive);
        }

        return response()->json($data);
    }

    protected function getSekolah()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->sekolah;
    }

    protected function authorizeSesi(SesiUjian $sesi, Sekolah $sekolah): void
    {
        $paket = $sesi->paket;
        if (! $paket) {
            abort(403, 'Anda tidak memiliki akses ke sesi ini.');
        }

        // School-owned paket
        if ($paket->sekolah_id === $sekolah->id) {
            return;
        }

        // Dinas-level paket matching jenjang
        if (is_null($paket->sekolah_id)) {
            $jenjang = $sekolah->jenjang;
            if (! $jenjang || $paket->jenjang === $jenjang || $paket->jenjang === 'SEMUA') {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki akses ke sesi ini.');
    }
}
