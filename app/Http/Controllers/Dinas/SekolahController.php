<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Services\SekolahService;
use Illuminate\Http\Request;

class SekolahController extends Controller
{
    public function __construct(
        protected SekolahService $sekolahService
    ) {}

    public function index()
    {
        $sekolahList = $this->sekolahService->getAllPaginated(20);

        return view('dinas.sekolah.index', compact('sekolahList'));
    }

    public function create()
    {
        return view('dinas.sekolah.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:200',
            'npsn'            => 'nullable|string|max:10|unique:sekolah',
            'jenjang'         => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI',
            'alamat'          => 'nullable|string',
            'kota'            => 'nullable|string|max:100',
            'telepon'         => 'nullable|string|max:20',
            'email'           => 'nullable|email',
            'kepala_sekolah'  => 'nullable|string|max:200',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $this->sekolahService->createSekolah($data);

        return redirect()->route('dinas.sekolah.index')
                         ->with('success', 'Sekolah berhasil ditambahkan.');
    }

    public function show(Sekolah $sekolah)
    {
        $sekolah->load(['peserta', 'paketUjian.sesi']);
        return view('dinas.sekolah.show', compact('sekolah'));
    }

    public function edit(Sekolah $sekolah)
    {
        return view('dinas.sekolah.form', compact('sekolah'));
    }

    public function update(Request $request, Sekolah $sekolah)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:200',
            'npsn'            => 'nullable|string|max:10|unique:sekolah,npsn,' . $sekolah->id,
            'jenjang'         => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI',
            'alamat'          => 'nullable|string',
            'kota'            => 'nullable|string|max:100',
            'telepon'         => 'nullable|string|max:20',
            'email'           => 'nullable|email',
            'kepala_sekolah'  => 'nullable|string|max:200',
            'is_active'       => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $this->sekolahService->updateSekolah($sekolah, $data);

        return redirect()->route('dinas.sekolah.index')
                         ->with('success', 'Data sekolah berhasil diperbarui.');
    }

    public function destroy(Sekolah $sekolah)
    {
        $this->sekolahService->deleteSekolah($sekolah);

        return redirect()->route('dinas.sekolah.index')
                         ->with('success', 'Sekolah berhasil dinonaktifkan.');
    }
}
