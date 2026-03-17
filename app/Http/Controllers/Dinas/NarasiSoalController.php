<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\NarasiSoal;
use App\Services\NarasiSoalService;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NarasiSoalController extends Controller
{
    public function __construct(
        protected NarasiSoalService $narasiSoalService,
        protected KategoriSoalRepository $kategoriSoalRepository
    ) {}

    public function index(Request $request)
    {
        $narasis = $this->narasiSoalService->getAllPaginated(
            kategoriId: $request->kategori,
            search: $request->search,
            perPage: 20
        );
        $kategoris = $this->kategoriSoalRepository->getActive();

        return view('dinas.narasi.index', compact('narasis', 'kategoris'));
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

        return redirect()->route('dinas.narasi.index')
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

        return redirect()->route('dinas.narasi.index')
                         ->with('success', 'Narasi berhasil diperbarui.');
    }

    public function destroy(NarasiSoal $narasi)
    {
        $this->narasiSoalService->deleteNarasi($narasi);

        return redirect()->route('dinas.narasi.index')
                         ->with('success', 'Narasi berhasil dihapus.');
    }

    /**
     * API: get active narasi filtered by kategori (for AJAX select in soal form).
     */
    public function apiByKategori(Request $request)
    {
        $narasis = $this->narasiSoalService->getActive($request->kategori_id);

        return response()->json($narasis);
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

        $path = $request->file('image')->store('narasi/inline', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
