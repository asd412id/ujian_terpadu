<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\Sekolah;
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

    public function peserta(PaketUjian $paket, SesiUjian $sesi, Request $request)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        // Re-sync peserta jika masih mode auto dan sesi belum berlangsung
        if (!$sesi->is_peserta_override && $sesi->status === 'persiapan') {
            $this->service->autoSyncPeserta($sesi);
        }

        $search = $request->get('search');
        $sekolahFilter = $request->get('sekolah_id');

        $enrolled = $this->service->getPesertaSesi($sesi, $search);
        $available = $this->service->getAvailablePeserta($sesi, $search, $sekolahFilter);

        // Stats (aggregate, unaffected by pagination/search)
        $totalEnrolled = $this->service->countEnrolled($sesi);
        $totalAvailable = $this->service->countAvailable($sesi);

        $sekolahList = Sekolah::when($paket->jenjang && strtoupper($paket->jenjang) !== 'SEMUA',
                fn($q) => $q->where('jenjang', $paket->jenjang))
            ->when($paket->sekolah_id, fn($q) => $q->where('id', $paket->sekolah_id))
            ->orderBy('nama')
            ->get();

        return view('dinas.sesi.peserta', compact(
            'paket', 'sesi', 'enrolled', 'available', 'sekolahList',
            'search', 'sekolahFilter', 'totalEnrolled', 'totalAvailable'
        ));
    }

    public function addPeserta(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $request->validate([
            'peserta_ids'   => 'required|array|min:1',
            'peserta_ids.*' => 'exists:peserta,id',
        ]);

        $count = $this->service->addPesertaToSesi($sesi, $request->peserta_ids);

        return back()->with('success', "{$count} peserta berhasil ditambahkan.");
    }

    public function removePeserta(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $request->validate([
            'peserta_ids'   => 'required|array|min:1',
            'peserta_ids.*' => 'exists:peserta,id',
        ]);

        $count = $this->service->removePesertaFromSesi($sesi, $request->peserta_ids);

        return back()->with('success', "{$count} peserta berhasil dihapus dari sesi.");
    }

    public function resetPeserta(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $count = $this->service->resetToAutoSync($sesi);

        return back()->with('success', "Peserta direset ke auto-sync. {$count} peserta terdaftar.");
    }
}
