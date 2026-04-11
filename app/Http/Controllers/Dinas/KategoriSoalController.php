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
        try {
            $this->kategoriSoalService->deleteKategori($kategori);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('dinas.kategori.index')
                             ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('dinas.kategori.index')
                         ->with('success', 'Kategori berhasil dihapus.');
    }

    public function destroyAll()
    {
        $deleted = $this->kategoriSoalService->deleteAllEmptyKategoris();

        if ($deleted === 0) {
            return redirect()->route('dinas.kategori.index')
                             ->with('error', 'Tidak ada kategori yang bisa dihapus. Semua kategori masih memiliki soal.');
        }

        return redirect()->route('dinas.kategori.index')
                         ->with('success', "{$deleted} kategori berhasil dihapus.");
    }

    public function clone(KategoriSoal $kategori)
    {
        try {
            $newKategori = $this->kategoriSoalService->cloneKategori($kategori);
        } catch (\Throwable $e) {
            return redirect()->route('dinas.kategori.index')
                             ->with('error', 'Gagal menyalin kategori. Silakan coba lagi.');
        }

        return redirect()->route('dinas.kategori.index')
                         ->with('success', "Kategori berhasil disalin sebagai \"{$newKategori->nama}\".");
    }
}
