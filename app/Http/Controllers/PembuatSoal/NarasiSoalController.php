<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Models\NarasiSoal;
use App\Services\NarasiSoalService;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NarasiSoalController extends Controller
{
    public function __construct(
        protected NarasiSoalService $narasiSoalService,
        protected KategoriSoalRepository $kategoriSoalRepository
    ) {}

    public function index(Request $request)
    {
        $narasis = NarasiSoal::with('kategori')
            ->where('created_by', Auth::id())
            ->when($request->kategori, fn ($q) => $q->where('kategori_id', $request->kategori))
            ->when($request->search, fn ($q) => $q->where('judul', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $kategoris = $this->kategoriSoalRepository->getActive();

        return view('pembuat-soal.narasi.index', compact('narasis', 'kategoris'));
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
            'gambar'      => 'nullable|image|max:2048',
            'is_active'   => 'boolean',
        ]);

        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')->store('narasi', 'public');
        }

        $this->narasiSoalService->createNarasi($data);

        return redirect()->route('pembuat-soal.narasi.index')
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
            'gambar'      => 'nullable|image|max:2048',
            'is_active'   => 'boolean',
        ]);

        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')->store('narasi', 'public');
        }

        $this->narasiSoalService->updateNarasi($narasi, $data);

        return redirect()->route('pembuat-soal.narasi.index')
                         ->with('success', 'Narasi berhasil diperbarui.');
    }

    public function destroy(NarasiSoal $narasi)
    {
        abort_unless($narasi->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke narasi ini.');

        $this->narasiSoalService->deleteNarasi($narasi);

        return redirect()->route('pembuat-soal.narasi.index')
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
}
