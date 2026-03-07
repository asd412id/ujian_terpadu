<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\SesiPeserta;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
        $paketList = PaketUjian::orderBy('nama')->get();

        $data = [];
        if ($request->filled('sekolah_id')) {
            $query = SesiPeserta::with(['peserta', 'sesi.paket', 'jawaban'])
                ->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $request->sekolah_id))
                ->where('status', 'selesai');

            if ($request->filled('paket_id')) {
                $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $request->paket_id));
            }

            $data = $query->paginate(30);
        }

        return view('dinas.laporan.index', compact('sekolahList', 'paketList', 'data'));
    }

    public function export(Request $request)
    {
        return response()->download(
            $this->generateExcelFile($request),
            'laporan_ujian_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    private function generateExcelFile(Request $request): string
    {
        // Placeholder — Excel export diimplementasi di Phase 8
        return '';
    }
}
