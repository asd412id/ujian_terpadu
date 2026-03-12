<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Services\PaketUjianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        abort_unless($paket, 404, 'Paket ujian tidak ditemukan.');

        return view('sekolah.paket.show', compact('paket'));
    }

    public function daftarPeserta(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'sesi_id'      => ['required', Rule::exists('sesi_ujian', 'id')->where('paket_id', $paket->id)],
            'peserta_ids'  => 'required|array',
            'peserta_ids.*'=> ['exists:peserta,id', Rule::exists('peserta', 'id')->where('sekolah_id', Auth::user()->sekolah_id)],
        ]);

        $result = $this->paketUjianService->registerPesertaWithSesiName(
            $request->sesi_id,
            $request->peserta_ids
        );

        return back()->with('success', "{$result['count']} peserta berhasil didaftarkan ke sesi {$result['sesi_nama']}.");
    }
}
