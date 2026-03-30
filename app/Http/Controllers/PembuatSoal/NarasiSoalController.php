<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Models\NarasiSoal;
use App\Services\NarasiSoalService;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NarasiSoalController extends Controller
{
    public function __construct(
        protected NarasiSoalService $narasiSoalService,
        protected KategoriSoalRepository $kategoriSoalRepository
    ) {}

    public function index(Request $request)
    {
        // Narasi is now a tab within Bank Soal page
        return redirect()->route('pembuat-soal.soal.index', array_merge(
            ['tab' => 'narasi'],
            $request->only(['search', 'kategori'])
        ));
    }

    public function create()
    {
        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('pembuat-soal.narasi.form', compact('kategoris'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul'       => 'required|string|max:255',
            'konten'      => 'required|string',
            'kategori_id' => 'required|exists:kategori_soal,id',
            'is_active'   => 'boolean',
        ]);

        $this->narasiSoalService->createNarasi($data);

        return redirect()->route('pembuat-soal.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil ditambahkan.');
    }

    public function show(NarasiSoal $narasi)
    {
        abort_unless($narasi->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $narasi->load(['kategori', 'pembuat', 'soalList.opsiJawaban']);
        return view('pembuat-soal.narasi.show', compact('narasi'));
    }

    public function edit(NarasiSoal $narasi)
    {
        abort_unless($narasi->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('pembuat-soal.narasi.form', compact('narasi', 'kategoris'));
    }

    public function update(Request $request, NarasiSoal $narasi)
    {
        abort_unless($narasi->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $data = $request->validate([
            'judul'       => 'required|string|max:255',
            'konten'      => 'required|string',
            'kategori_id' => 'required|exists:kategori_soal,id',
            'is_active'   => 'boolean',
        ]);

        $this->narasiSoalService->updateNarasi($narasi, $data);

        return redirect()->route('pembuat-soal.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil diperbarui.');
    }

    public function destroy(NarasiSoal $narasi)
    {
        abort_unless($narasi->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke narasi ini.');

        // Hanya soal milik user ini yang ikut dihapus saat narasi di-cascade.
        // Soal milik pengguna lain yang terhubung ke narasi ini tidak terpengaruh.
        $this->narasiSoalService->deleteNarasi($narasi, Auth::id());

        return redirect()->route('pembuat-soal.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil dihapus.');
    }

    public function trash(Request $request)
    {
        $trashedNarasi = $this->narasiSoalService->getTrashedPaginated(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            perPage: 20,
            createdBy: Auth::id(),
        );

        $kategoris = $this->kategoriSoalRepository->getActive();
        $allFilteredIds = $this->narasiSoalService->getTrashedIds(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            createdBy: Auth::id(),
        );

        return view('pembuat-soal.narasi.trash', compact('trashedNarasi', 'kategoris', 'allFilteredIds'));
    }

    public function restore(NarasiSoal $narasi_trashed)
    {
        abort_unless($narasi_trashed->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $this->narasiSoalService->restoreNarasi($narasi_trashed, Auth::id());

        return redirect()->route('pembuat-soal.narasi.trash')
            ->with('success', 'Narasi berhasil dipulihkan beserta soal terkait yang masih ada di sampah.');
    }

    public function forceDelete(NarasiSoal $narasi_trashed)
    {
        abort_unless($narasi_trashed->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $this->narasiSoalService->forceDeleteNarasi($narasi_trashed, Auth::id());

        return redirect()->route('pembuat-soal.narasi.trash')
            ->with('success', 'Narasi berhasil dihapus permanen beserta soal terkait dan asetnya.');
    }

    public function emptyTrash()
    {
        $count = $this->narasiSoalService->emptyTrash(Auth::id());

        return redirect()->route('pembuat-soal.narasi.trash')
            ->with('success', "{$count} narasi berhasil dihapus permanen.");
    }

    public function bulkRestore(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->narasiSoalService->bulkRestoreNarasi($request->ids, Auth::id());

        return redirect()->route('pembuat-soal.narasi.trash')
            ->with('success', "{$count} narasi berhasil dipulihkan.");
    }

    public function bulkForceDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->narasiSoalService->bulkForceDeleteNarasi($request->ids, Auth::id());

        return redirect()->route('pembuat-soal.narasi.trash')
            ->with('success', "{$count} narasi berhasil dihapus permanen.");
    }

    public function destroyAll(Request $request)
    {
        $validated = $request->validate([
            'kategori' => 'nullable|exists:kategori_soal,id',
        ]);

        $count = $this->narasiSoalService->deleteAllNarasi($validated['kategori'] ?? null, Auth::id());

        if (!empty($validated['kategori'])) {
            $kategori = \App\Models\KategoriSoal::findOrFail($validated['kategori']);
            $message = $count > 0
                ? "{$count} narasi kategori \"{$kategori->nama}\" berhasil dihapus."
                : "Tidak ada narasi kategori \"{$kategori->nama}\" yang perlu dihapus.";
        } else {
            $message = $count > 0
                ? "{$count} narasi berhasil dihapus."
                : 'Tidak ada narasi yang perlu dihapus.';
        }

        return redirect()->route('pembuat-soal.soal.index', ['tab' => 'narasi'])
            ->with($count > 0 ? 'success' : 'info', $message);
    }

    public function apiByKategori(Request $request)
    {
        $narasis = NarasiSoal::accessibleBy(Auth::id())
            ->where('is_active', true)
            ->when($request->kategori_id, fn ($q) => $q->where('kategori_id', $request->kategori_id))
            ->orderBy('judul')
            ->get(['id', 'judul', 'kategori_id']);

        return response()->json($narasis);
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

        $path = $request->file('image')->store('narasi/inline', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
