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
            'rekap'       => $data['rekap'],
        ]);
    }

    public function export(Request $request)
    {
        $exportData = $this->laporanService->exportHasil($request->all());

        if (empty($exportData)) {
            return back()->with('warning', 'Tidak ada data untuk di-export.');
        }

        $filename = 'laporan_ujian_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Nama Peserta', 'NIS', 'Sekolah', 'Paket', 'Nilai Akhir', 'Benar', 'Salah', 'Kosong', 'Durasi', 'Submit']);

            foreach ($exportData as $row) {
                fputcsv($file, [
                    $row['nama_peserta'],
                    $row['nis'],
                    $row['sekolah'],
                    $row['paket'],
                    $row['nilai_akhir'],
                    $row['jumlah_benar'],
                    $row['jumlah_salah'],
                    $row['jumlah_kosong'],
                    $row['durasi'],
                    $row['submit_at'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
