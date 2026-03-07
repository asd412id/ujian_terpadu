<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\Peserta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaketUjianController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $paketList = PaketUjian::with(['sesi.sesiPeserta', 'paketSoal'])
            ->where(fn ($q) => $q->where('sekolah_id', $user->sekolah_id)
                                 ->orWhereNull('sekolah_id'))
            ->where('status', 'aktif')
            ->latest()
            ->paginate(20);

        return view('sekolah.paket.index', compact('paketList'));
    }

    public function show(PaketUjian $paket)
    {
        $paket->load(['sesi.sesiPeserta.peserta', 'paketSoal']);
        return view('sekolah.paket.show', compact('paket'));
    }

    public function daftarPeserta(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'sesi_id'      => 'required|exists:sesi_ujian,id',
            'peserta_ids'  => 'required|array',
            'peserta_ids.*'=> 'exists:peserta,id',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $sesi = SesiUjian::findOrFail($request->sesi_id);

        $created = 0;
        foreach ($request->peserta_ids as $pesertaId) {
            SesiPeserta::firstOrCreate(
                ['sesi_id' => $sesi->id, 'peserta_id' => $pesertaId],
                ['status' => 'belum_login']
            );
            $created++;
        }

        return back()->with('success', "$created peserta berhasil didaftarkan ke sesi {$sesi->nama_sesi}.");
    }
}
