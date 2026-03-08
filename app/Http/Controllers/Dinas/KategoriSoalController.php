<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\KategoriSoal;
use App\Services\KategoriSoalService;
use Illuminate\Http\Request;

class KategoriSoalController extends Controller
{
    public function __construct(
        protected KategoriSoalService $kategoriSoalService
    ) {}

    public function index()
    {
        $kategoris = $this->kategoriSoalService->getAllPaginated(30);
        return view('dinas.kategori.index', compact('kategoris'));
    }

    public function create()
    {
        return view('dinas.kategori.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'kode'       => 'nullable|string|max:20|unique:kategori_soal',
            'jenjang'    => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI,SEMUA',
            'kelompok'   => 'nullable|string|max:50',
            'kurikulum'  => 'required|string|max:50',
            'urutan'     => 'integer|min:0',
        ]);

        $this->kategoriSoalService->createKategori($data);

        return redirect()->route('dinas.kategori.index')
                         ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(KategoriSoal $kategori)
    {
        return view('dinas.kategori.form', compact('kategori'));
    }

    public function update(Request $request, KategoriSoal $kategori)
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'kode'       => 'nullable|string|max:20|unique:kategori_soal,kode,' . $kategori->id,
            'jenjang'    => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI,SEMUA',
            'kelompok'   => 'nullable|string|max:50',
            'kurikulum'  => 'required|string|max:50',
            'urutan'     => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $this->kategoriSoalService->updateKategori($kategori, $data);

        return redirect()->route('dinas.kategori.index')
                         ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(KategoriSoal $kategori)
    {
        $this->kategoriSoalService->deleteKategori($kategori);

        return redirect()->route('dinas.kategori.index')
                         ->with('success', 'Kategori dinonaktifkan.');
    }
}
