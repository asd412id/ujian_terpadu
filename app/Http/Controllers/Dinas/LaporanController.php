<?php

namespace App\Http\Controllers\Dinas;

use App\Exports\LaporanUjianExport;
use App\Http\Controllers\Controller;
use App\Models\SesiPeserta;
use App\Services\LaporanService;
use App\Services\PenilaianService;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function __construct(
        protected LaporanService $laporanService
    ) {}

    public function index(Request $request)
    {
        $data = $this->laporanService->getHasilUjian($request->only([
            'sekolah_id', 'paket_id', 'page', 'per_page', 'search',
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
        $exportData = $this->laporanService->exportHasil($request->only([
            'sekolah_id', 'paket_id', 'search',
        ]));

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

    public function recalculate(Request $request, PenilaianService $penilaianService)
    {
        $filters = $request->only(['sekolah_id', 'paket_id']);

        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai'])
            ->with(['sesi.paket.paketSoal.soal', 'jawaban.soal.opsiJawaban']);

        if (! empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (! empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        $updated = 0;
        $changed = 0;

        $query->chunkById(50, function ($chunk) use ($penilaianService, &$updated, &$changed) {
            foreach ($chunk as $sp) {
                $oldNilai = (float) $sp->nilai_akhir;
                $hasil = $penilaianService->hitungNilai($sp);
                $newNilai = (float) $hasil['nilai_akhir'];

                if ($oldNilai !== $newNilai) {
                    $sp->update($hasil);
                    $changed++;
                }

                $updated++;
            }
        });

        if ($changed > 0) {
            return back()
                ->withInput()
                ->with('success', "Recalculate selesai: {$changed} dari {$updated} nilai diperbarui.");
        }

        return back()
            ->withInput()
            ->with('info', "Recalculate selesai: semua {$updated} nilai sudah benar, tidak ada perubahan.");
    }
}
