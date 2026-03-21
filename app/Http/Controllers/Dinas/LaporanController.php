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
        $data = $this->laporanService->getHasilUjian($request->only([
            'sekolah_id', 'paket_id', 'status', 'page', 'per_page', 'search',
        ]));

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
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $filters = $request->only(['sekolah_id', 'paket_id', 'search', 'status']);

        $exportData = $this->laporanService->exportHasil($filters);

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

    public function recalculate(Request $request)
    {
        $filters = $request->only(['sekolah_id', 'paket_id']);
        $userId  = (string) auth()->id();
        $cacheKey = 'recalculate_progress_' . $userId;

        // Check if already running
        $progress = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($progress && $progress['status'] === 'processing') {
            return back()
                ->withInput()
                ->with('warning', "Recalculate sedang berjalan ({$progress['updated']}/{$progress['total']}). Harap tunggu hingga selesai.");
        }

        \App\Jobs\RecalculateNilaiJob::dispatch($filters, $userId);

        return back()
            ->withInput()
            ->with('info', 'Proses recalculate nilai telah dimulai di background. Refresh halaman untuk melihat progress.');
    }

    public function recalculateProgress()
    {
        $cacheKey = 'recalculate_progress_' . auth()->id();
        $progress = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $progress) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json($progress);
    }
}
