<?php

namespace App\Http\Controllers\Sekolah;

use App\Exports\LaporanUjianExport;
use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LaporanController extends Controller
{
    public function __construct(
        protected LaporanService $laporanService
    ) {}

    public function index(Request $request)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        $data = $this->laporanService->getHasilUjianBySekolah($sekolah->id, $request->only([
            'paket_id', 'status', 'search', 'page', 'per_page', 'kelas', 'sesi_id',
        ]));

        return view('sekolah.laporan.index', [
            'paketList' => $data['paketList'],
            'kelasList' => $data['kelasList'],
            'sesiList'  => $data['sesiList'],
            'data'      => $data['data'],
            'rekap'     => $data['rekap'],
        ]);
    }

    public function export(Request $request)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $filters = $request->only(['paket_id', 'search', 'status', 'kelas', 'sesi_id']);

        $exportData = $this->laporanService->exportHasilBySekolah($sekolah->id, $filters);

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

    protected function getSekolah()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->sekolah;
    }
}
