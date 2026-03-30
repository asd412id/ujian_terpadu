<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\NarasiSoal;
use App\Services\NarasiSoalService;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NarasiSoalController extends Controller
{
    public function __construct(
        protected NarasiSoalService $narasiSoalService,
        protected KategoriSoalRepository $kategoriSoalRepository
    ) {}

    public function index(Request $request)
    {
        return redirect()->route('dinas.soal.index', ['tab' => 'narasi']);
    }

    public function create()
    {
        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('dinas.narasi.form', compact('kategoris'));
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

        return redirect()->route('dinas.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil ditambahkan.');
    }

    public function show(NarasiSoal $narasi)
    {
        $narasi->load(['kategori', 'pembuat', 'soalList.opsiJawaban']);
        return view('dinas.narasi.show', compact('narasi'));
    }

    public function edit(NarasiSoal $narasi)
    {
        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('dinas.narasi.form', compact('narasi', 'kategoris'));
    }

    public function update(Request $request, NarasiSoal $narasi)
    {
        $data = $request->validate([
            'judul'       => 'required|string|max:255',
            'konten'      => 'required|string',
            'kategori_id' => 'required|exists:kategori_soal,id',
            'is_active'   => 'boolean',
        ]);

        $this->narasiSoalService->updateNarasi($narasi, $data);

        return redirect()->route('dinas.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil diperbarui.');
    }

    public function destroy(NarasiSoal $narasi)
    {
        $this->narasiSoalService->deleteNarasi($narasi);

        return redirect()->route('dinas.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil dihapus.');
    }

    public function trash(Request $request)
    {
        $trashedNarasi = $this->narasiSoalService->getTrashedPaginated(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            perPage: 20,
        );

        $kategoris = $this->kategoriSoalRepository->getActive();
        $allFilteredIds = $this->narasiSoalService->getTrashedIds(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
        );

        return view('dinas.narasi.trash', compact('trashedNarasi', 'kategoris', 'allFilteredIds'));
    }

    public function restore(NarasiSoal $narasi_trashed)
    {
        $this->narasiSoalService->restoreNarasi($narasi_trashed);

        return redirect()->route('dinas.narasi.trash')
            ->with('success', 'Narasi berhasil dipulihkan beserta soal terkait yang masih ada di sampah.');
    }

    public function forceDelete(NarasiSoal $narasi_trashed)
    {
        $this->narasiSoalService->forceDeleteNarasi($narasi_trashed);

        return redirect()->route('dinas.narasi.trash')
            ->with('success', 'Narasi berhasil dihapus permanen beserta soal terkait dan asetnya.');
    }

    public function emptyTrash()
    {
        $count = $this->narasiSoalService->emptyTrash();

        return redirect()->route('dinas.narasi.trash')
            ->with('success', "{$count} narasi berhasil dihapus permanen.");
    }

    public function bulkRestore(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->narasiSoalService->bulkRestoreNarasi($request->ids);

        return redirect()->route('dinas.narasi.trash')
            ->with('success', "{$count} narasi berhasil dipulihkan.");
    }

    public function bulkForceDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->narasiSoalService->bulkForceDeleteNarasi($request->ids);

        return redirect()->route('dinas.narasi.trash')
            ->with('success', "{$count} narasi berhasil dihapus permanen.");
    }

    public function destroyAll(Request $request)
    {
        $validated = $request->validate([
            'kategori' => 'nullable|exists:kategori_soal,id',
        ]);

        $count = $this->narasiSoalService->deleteAllNarasi($validated['kategori'] ?? null);

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

        return redirect()->route('dinas.soal.index', ['tab' => 'narasi'])
            ->with($count > 0 ? 'success' : 'info', $message);
    }

    /**
     * API: get active narasi filtered by kategori (for AJAX select in soal form).
     */
    public function apiByKategori(Request $request)
    {
        $narasis = NarasiSoal::where('is_active', true)
            ->when($request->kategori_id, fn ($q) => $q->where('kategori_id', $request->kategori_id))
            ->when($request->search, fn ($q) => $q->where(function ($inner) use ($request) {
                $inner->where('judul', 'like', "%{$request->search}%")
                      ->orWhere('konten_plain', 'like', "%{$request->search}%");
            }))
            ->withCount('soalList')
            ->orderBy('judul')
            ->get(['id', 'judul', 'kategori_id', 'konten_plain'])
            ->map(fn ($n) => [
                'id'         => $n->id,
                'judul'      => $n->judul,
                'preview'    => Str::limit(strip_tags($n->konten_plain ?? ''), 150),
                'soal_count' => $n->soal_list_count,
            ]);

        return response()->json($narasis);
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

        $path = $request->file('image')->store('narasi/inline', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
