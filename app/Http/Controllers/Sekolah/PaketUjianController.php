<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Services\PaketUjianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaketUjianController extends Controller
{
    public function __construct(
        protected PaketUjianService $paketUjianService
    ) {}

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->loadMissing('sekolah');

        $paketList = $this->paketUjianService->getForSekolah(
            $user->sekolah_id,
            $user->sekolah?->jenjang
        );

        return view('sekolah.paket.index', compact('paketList'));
    }

    public function show(PaketUjian $paket)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $paket = $this->paketUjianService->getDetail($paket->id, $user->sekolah_id);

        return view('sekolah.paket.show', compact('paket'));
    }

    public function daftarPeserta(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'sesi_id'      => 'required|exists:sesi_ujian,id',
            'peserta_ids'  => 'required|array',
            'peserta_ids.*'=> 'exists:peserta,id',
        ]);

        $created = $this->paketUjianService->registerPeserta(
            $request->sesi_id,
            $request->peserta_ids
        );

        $sesi = \App\Models\SesiUjian::findOrFail($request->sesi_id);

        return back()->with('success', "$created peserta berhasil didaftarkan ke sesi {$sesi->nama_sesi}.");
    }
}
