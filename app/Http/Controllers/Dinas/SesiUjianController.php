<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Models\User;
use App\Services\SesiUjianService;
use Illuminate\Http\Request;

class SesiUjianController extends Controller
{
    public function __construct(
        protected SesiUjianService $service
    ) {}

    public function store(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'nama_sesi'     => 'required|string|max:100',
            'ruangan'       => 'nullable|string|max:100',
            'pengawas_id'   => 'nullable|exists:users,id',
            'waktu_mulai'   => 'nullable|date',
            'waktu_selesai' => 'nullable|date|after_or_equal:waktu_mulai',
            'kapasitas'     => 'nullable|integer|min:1|max:999',
        ]);

        $this->service->createSesi($paket, $request->only([
            'nama_sesi', 'ruangan', 'pengawas_id', 'waktu_mulai', 'waktu_selesai', 'kapasitas',
        ]));

        return back()->with('success', 'Sesi ujian berhasil ditambahkan.');
    }

    public function edit(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $pengawas = User::where('role', 'pengawas')->orderBy('name')->get();

        return view('dinas.sesi.edit', compact('paket', 'sesi', 'pengawas'));
    }

    public function update(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $request->validate([
            'nama_sesi'     => 'required|string|max:100',
            'ruangan'       => 'nullable|string|max:100',
            'pengawas_id'   => 'nullable|exists:users,id',
            'waktu_mulai'   => 'nullable|date',
            'waktu_selesai' => 'nullable|date|after_or_equal:waktu_mulai',
            'kapasitas'     => 'nullable|integer|min:1|max:999',
            'status'        => 'nullable|in:persiapan,berlangsung,selesai',
        ]);

        $this->service->updateSesi($sesi, $request->only([
            'nama_sesi', 'ruangan', 'pengawas_id', 'waktu_mulai', 'waktu_selesai', 'kapasitas', 'status',
        ]));

        return redirect()->route('dinas.paket.show', $paket)
                         ->with('success', 'Sesi ujian berhasil diperbarui.');
    }

    public function destroy(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        try {
            $this->service->deleteSesi($sesi);
            return back()->with('success', 'Sesi ujian berhasil dihapus.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
