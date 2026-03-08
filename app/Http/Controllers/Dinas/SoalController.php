<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use App\Services\SoalService;
use Illuminate\Http\Request;

class SoalController extends Controller
{
    public function __construct(
        protected SoalService $soalService
    ) {}

    public function index(Request $request)
    {
        $soal = $this->soalService->getFilteredSoal(
            kategoriId: $request->kategori,
            tipe: $request->tipe,
            kesulitan: $request->kesulitan,
            search: $request->search,
            perPage: 20
        );

        $kategori = $this->soalService->getActiveKategori();

        return view('dinas.soal.index', compact('soal', 'kategori'));
    }

    public function create()
    {
        $kategoris = $this->soalService->getActiveKategori();
        return view('dinas.soal.form', compact('kategoris'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori_soal_id'  => 'required|exists:kategori_soal,id',
            'jenis_soal'        => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,menjodohkan,isian,essay',
            'pertanyaan'        => 'required|string',
            'gambar_pertanyaan' => 'nullable|image|max:5120',
            'posisi_gambar'     => 'nullable|in:atas,bawah,kiri,kanan',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'bobot'             => 'required|numeric|min:0|max:100',
            'pembahasan'        => 'nullable|string',
            'sumber'            => 'nullable|string|max:200',
            'tahun_soal'        => 'nullable|integer|min:2000|max:2099',
        ]);

        $this->soalService->createSoal($validated, $request);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = $this->soalService->getActiveKategori();
        return view('dinas.soal.form', compact('soal', 'kategoris'));
    }

    public function update(Request $request, Soal $soal)
    {
        $validated = $request->validate([
            'kategori_soal_id'  => 'required|exists:kategori_soal,id',
            'jenis_soal'        => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,menjodohkan,isian,essay',
            'pertanyaan'        => 'required|string',
            'gambar_pertanyaan' => 'nullable|image|max:5120',
            'posisi_gambar'     => 'nullable|in:atas,bawah,kiri,kanan',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'bobot'             => 'required|numeric|min:0|max:100',
            'pembahasan'        => 'nullable|string',
        ]);

        $this->soalService->updateSoal($soal, $validated, $request);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function show(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan', 'kategori']);
        return view('dinas.soal.show', compact('soal'));
    }

    public function destroy(Soal $soal)
    {
        $this->soalService->deleteSoal($soal);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }
}
