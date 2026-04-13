<?php

namespace App\Http\Controllers\Sekolah;

use App\Exports\LaporanUjianExport;
use App\Http\Controllers\Controller;
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

        if (!empty($exportData['truncated'])) {
            $exportData['rekap']['catatan'] = 'Data dibatasi maksimal ' . number_format($exportData['maxRows']) . ' baris. Gunakan filter untuk mempersempit hasil.';
        }

        $filename = 'laporan_ujian_' . now()->format('Ymd_His') . '.xlsx';

        return (new LaporanUjianExport(
            hasilData: $exportData['hasil'],
            rekap: $exportData['rekap'],
            filters: $exportData['filters'],
            perSoalData: $exportData['perSoal'],
        ))->download($filename);
    }

    public function detailSiswa(string $sesiPesertaId)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        $sp = SesiPeserta::with(['peserta', 'sesi.paket'])->findOrFail($sesiPesertaId);

        // Ownership: peserta must belong to this sekolah
        abort_unless(
            $sp->peserta && $sp->peserta->sekolah_id === $sekolah->id,
            403,
            'Anda tidak memiliki akses ke data peserta ini.'
        );

        // Paket must have tampilkan_hasil enabled
        $paket = $sp->sesi->paket;
        abort_unless(
            $paket && $paket->tampilkan_hasil,
            403,
            'Hasil ujian untuk paket ini belum dipublikasikan.'
        );

        // Sesi must be selesai OR all school peserta in this sesi already submitted
        $sesi = $sp->sesi;
        if ($sesi->status !== 'selesai') {
            $hasUnfinished = SesiPeserta::where('sesi_id', $sesi->id)
                ->whereNotIn('status', ['submit', 'dinilai'])
                ->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $sekolah->id))
                ->exists();

            abort_if($hasUnfinished, 403, 'Hasil ujian belum dapat dilihat karena masih ada peserta yang belum selesai.');
        }

        $data = $this->laporanService->getDetailSiswa($sesiPesertaId);

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
