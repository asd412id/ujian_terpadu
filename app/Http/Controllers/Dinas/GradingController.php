<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\JawabanPeserta;
use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradingController extends Controller
{
    public function index(Request $request)
    {
        $query = JawabanPeserta::with(['soal.kategori', 'sesiPeserta.peserta.sekolah', 'sesiPeserta.sesi.paket'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNull('skor_manual')
            ->whereNotNull('jawaban_teks');

        if ($request->paket_id) {
            $query->whereHas('sesiPeserta.sesi', fn ($q) => $q->where('paket_id', $request->paket_id));
        }
        if ($request->sekolah_id) {
            $query->whereHas('sesiPeserta.peserta', fn ($q) => $q->where('sekolah_id', $request->sekolah_id));
        }

        $jawabans = $query->latest()->paginate(15);

        $totalBelumDinilai = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNull('skor_manual')->whereNotNull('jawaban_teks')->count();

        $paketList = PaketUjian::orderBy('nama')->get();
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();

        return view('dinas.grading.index', compact('jawabans', 'totalBelumDinilai', 'paketList', 'sekolahList'));
    }

    public function nilai(Request $request, JawabanPeserta $jawaban)
    {
        $request->validate([
            'skor_manual'     => 'required|numeric|min:0|max:100',
            'catatan_penilai' => 'nullable|string|max:500',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $jawaban->update([
            'skor_manual'     => $request->skor_manual,
            'catatan_penilai' => $request->catatan_penilai,
            'dinilai_oleh'    => $user->id,
            'dinilai_at'      => now(),
        ]);

        return back()->with('success', 'Nilai essay berhasil disimpan.');
    }
}
