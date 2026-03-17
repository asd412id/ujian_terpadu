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
        abort_unless($narasi->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $narasi->load(['kategori', 'pembuat', 'soalList.opsiJawaban']);
        return view('pembuat-soal.narasi.show', compact('narasi'));
    }

    public function edit(NarasiSoal $narasi)
    {
        abort_unless($narasi->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('pembuat-soal.narasi.form', compact('narasi', 'kategoris'));
    }

    public function update(Request $request, NarasiSoal $narasi)
    {
        abort_unless($narasi->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

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
        abort_unless($narasi->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $this->narasiSoalService->deleteNarasi($narasi);

        return redirect()->route('pembuat-soal.soal.index', ['tab' => 'narasi'])
                         ->with('success', 'Narasi berhasil dihapus.');
    }

    public function apiByKategori(Request $request)
    {
        $narasis = NarasiSoal::where('created_by', Auth::id())
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
