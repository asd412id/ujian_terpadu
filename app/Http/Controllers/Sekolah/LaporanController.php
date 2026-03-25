<?php

namespace App\Http\Controllers\Sekolah;

use App\Exports\LaporanUjianExport;
use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\SesiPeserta;
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
            'paket_id', 'status', 'search', 'page', 'per_page',
        ]));

        return view('sekolah.laporan.index', [
            'paketList' => $data['paketList'],
            'data'      => $data['data'],
            'rekap'     => $data['rekap'],
        ]);
    }

    public function export(Request $request)
    {
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        $filters = $request->only(['paket_id', 'search', 'status']);
        $filters['sekolah_id'] = $sekolah->id;

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

    public function analisisSoal(Request $request, PaketUjian $paket)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');
        abort_unless($paket->sekolah_id === $sekolah->id, 403, 'Anda tidak memiliki akses ke paket ini.');

        $data = $this->laporanService->getAnalisisSoal($paket->id);

        return view('sekolah.laporan.analisis-soal', [
            'paket'    => $data['paket'],
            'analisis' => $data['analisis'],
            'summary'  => $data['summary'],
        ]);
    }

    public function detailSiswa(SesiPeserta $sesiPeserta)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        $sesiPeserta->loadMissing(['sesi.paket', 'peserta.sekolah']);
        abort_unless(($sesiPeserta->sesi?->paket?->sekolah_id ?? null) === $sekolah->id, 403, 'Anda tidak memiliki akses ke peserta ini.');

        $data = $this->laporanService->getDetailSiswa($sesiPeserta->id);

        return view('sekolah.laporan.detail-siswa', [
            'sesiPeserta' => $data['sesiPeserta'],
            'detail'      => $data['detail'],
        ]);
    }

    protected function getSekolah()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->sekolah;
    }
}
