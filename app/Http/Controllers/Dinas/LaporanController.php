<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function __construct(
        protected LaporanService $laporanService
    ) {}

    public function index(Request $request)
    {
        $data = $this->laporanService->getHasilUjian($request->all());

        return view('dinas.laporan.index', [
            'sekolahList' => $data['sekolahList'],
            'paketList'   => $data['paketList'],
            'data'        => $data['data'],
        ]);
    }

    public function export(Request $request)
    {
        $exportData = $this->laporanService->exportHasil($request->all());

        // Placeholder — Excel export diimplementasi di Phase 8
        return response()->download(
            '',
            'laporan_ujian_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
