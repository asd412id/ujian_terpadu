<?php

namespace App\Http\Controllers\Dinas;

use App\Exports\LaporanUjianExport;
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
            'rekap'       => $data['rekap'],
        ]);
    }

    public function analisisSoal(Request $request, string $paketId)
    {
        $data = $this->laporanService->getAnalisisSoal($paketId);

        return view('dinas.laporan.analisis-soal', [
            'paket'    => $data['paket'],
            'analisis' => $data['analisis'],
            'summary'  => $data['summary'],
        ]);
    }

    public function detailSiswa(string $sesiPesertaId)
    {
        $data = $this->laporanService->getDetailSiswa($sesiPesertaId);

        return view('dinas.laporan.detail-siswa', [
            'sesiPeserta' => $data['sesiPeserta'],
            'detail'      => $data['detail'],
        ]);
    }

    public function export(Request $request)
    {
        $exportData = $this->laporanService->exportHasil($request->all());

        if (empty($exportData['hasil'])) {
            return back()->with('warning', 'Tidak ada data untuk di-export.');
        }

        $filename = 'laporan_ujian_' . now()->format('Ymd_His') . '.xlsx';

        return (new LaporanUjianExport(
            hasilData: $exportData['hasil'],
            rekap: $exportData['rekap'],
            filters: $exportData['filters'],
            perSoalData: $exportData['perSoal'],
        ))->download($filename);
    }
}
