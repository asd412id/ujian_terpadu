<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Services\SesiUjianService;
use Illuminate\Http\Request;

class SesiUjianController extends Controller
{
    public function __construct(
        protected SesiUjianService $service
    ) {}

    private function ensurePersiapanSesi(SesiUjian $sesi)
    {
        if ($sesi->status !== 'persiapan') {
            return back()->with('error', 'Peserta hanya bisa diubah saat sesi masih berstatus persiapan.');
        }

        return null;
    }

    public function store(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'nama_sesi'     => 'required|string|max:100',
            'ruangan'       => 'nullable|string|max:100',
            'pengawas_id'   => 'nullable|exists:users,id',
            'waktu_mulai'   => 'nullable|date',
            'waktu_selesai' => 'nullable|date|after_or_equal:waktu_mulai',
            'kapasitas'     => 'nullable|integer|min:1|max:999',
            'peserta_mode'  => 'required|in:manual,all',
        ]);

        $sesi = $this->service->createSesi($paket, $request->only([
            'nama_sesi', 'ruangan', 'pengawas_id', 'waktu_mulai', 'waktu_selesai', 'kapasitas', 'peserta_mode',
        ]));

        if ($request->input('peserta_mode') === 'manual') {
            return redirect()->route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id])
                ->with('success', 'Sesi ujian berhasil ditambahkan. Pilih peserta satu per satu, cari peserta, atau gunakan tombol tambah semua peserta.');
        }

        return back()->with('success', 'Sesi ujian berhasil ditambahkan.');
    }

    public function edit(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        $pengawas = $this->service->getPengawasList();
        $activePesertaCount = $this->service->countActivePeserta($sesi);

        return view('dinas.sesi.edit', compact('paket', 'sesi', 'pengawas', 'activePesertaCount'));
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

        try {
            $this->service->updateSesi($sesi, $request->only([
                'nama_sesi', 'ruangan', 'pengawas_id', 'waktu_mulai', 'waktu_selesai', 'kapasitas', 'status',
            ]));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

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

        $sekolahList = $this->service->getSekolahListForPaket($paket);

        return view('dinas.sesi.peserta', compact(
            'paket', 'sesi', 'enrolled', 'available', 'sekolahList',
            'search', 'sekolahFilter', 'totalEnrolled', 'totalAvailable'
        ));
    }

    public function addPeserta(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        $request->validate([
            'peserta_ids'   => 'required|array|min:1',
            'peserta_ids.*' => 'distinct|exists:peserta,id',
        ]);

        try {
            $count = $this->service->addPesertaToSesi($sesi, $request->peserta_ids);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($count > 0) {
            return back()->with('success', "{$count} peserta berhasil ditambahkan.");
        }

        return back()->with('info', 'Tidak ada peserta valid yang bisa ditambahkan ke sesi ini.');
    }

    public function addAllPeserta(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        $validated = $request->validate([
            'search'     => 'nullable|string|max:100',
            'sekolah_id' => 'nullable|exists:sekolah,id',
        ]);

        try {
            $count = $this->service->addAllAvailablePeserta(
                $sesi,
                $validated['search'] ?? null,
                $validated['sekolah_id'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($count > 0) {
            return back()->with('success', "{$count} peserta berhasil ditambahkan ke sesi.");
        }

        return back()->with('info', 'Tidak ada peserta tersedia yang bisa ditambahkan.');
    }

    public function removePeserta(Request $request, PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        $request->validate([
            'peserta_ids'   => 'required|array|min:1',
            'peserta_ids.*' => 'distinct|exists:peserta,id',
        ]);

        try {
            $count = $this->service->removePesertaFromSesi($sesi, $request->peserta_ids);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($count > 0) {
            return back()->with('success', "{$count} peserta berhasil dihapus dari sesi.");
        }

        return back()->with('info', 'Tidak ada peserta terdaftar yang bisa dihapus dari sesi ini.');
    }

    public function resetPeserta(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        try {
            $count = $this->service->resetToAutoSync($sesi);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Peserta direset ke auto-sync. {$count} peserta terdaftar.");
    }

    public function syncPesertaBaru(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        try {
            $count = $this->service->syncNewPeserta($sesi);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($count > 0) {
            return back()->with('success', "{$count} peserta baru berhasil disinkronkan ke sesi.");
        }

        return back()->with('info', 'Tidak ada peserta baru yang perlu disinkronkan.');
    }

    public function removeAllPeserta(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        if ($response = $this->ensurePersiapanSesi($sesi)) {
            return $response;
        }

        try {
            $count = $this->service->removeAllPesertaFromSesi($sesi);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($count > 0) {
            return back()->with('success', "{$count} peserta berhasil dihapus dari sesi.");
        }

        return back()->with('info', 'Tidak ada peserta terdaftar yang bisa dihapus dari sesi ini.');
    }

    public function enrolledIds(PaketUjian $paket, SesiUjian $sesi)
    {
        abort_unless($sesi->paket_id === $paket->id, 404);

        return response()->json([
            'ids' => $this->service->getAllEnrolledPesertaIds($sesi),
        ]);
    }
}
