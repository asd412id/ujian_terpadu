<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use App\Models\KategoriSoal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SoalController extends Controller
{
    public function index(Request $request)
    {
        $soal = Soal::with(['kategori', 'sekolah', 'pembuat'])
            ->when($request->kategori, fn ($q) => $q->where('kategori_id', $request->kategori))
            ->when($request->tipe, fn ($q) => $q->where('tipe_soal', $request->tipe))
            ->when($request->kesulitan, fn ($q) => $q->where('tingkat_kesulitan', $request->kesulitan))
            ->when($request->search, fn ($q) => $q->where('pertanyaan', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $kategori = KategoriSoal::where('is_active', true)->orderBy('urutan')->get();

        return view('dinas.soal.index', compact('soal', 'kategori'));
    }

    private array $jenisMap = [
        'pilihan_ganda'          => 'pg',
        'pilihan_ganda_kompleks' => 'pg_kompleks',
        'menjodohkan'            => 'menjodohkan',
        'isian'                  => 'isian',
        'essay'                  => 'essay',
    ];

    public function create()
    {
        $kategoris = KategoriSoal::where('is_active', true)->orderBy('urutan')->get();
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

        $data = [
            'kategori_id'       => $validated['kategori_soal_id'],
            'tipe_soal'         => $this->jenisMap[$validated['jenis_soal']],
            'pertanyaan'        => $validated['pertanyaan'],
            'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
            'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
            'bobot'             => $validated['bobot'],
            'pembahasan'        => $validated['pembahasan'] ?? null,
            'sumber'            => $validated['sumber'] ?? null,
            'tahun_soal'        => $validated['tahun_soal'] ?? null,
        ];

        if ($request->hasFile('gambar_pertanyaan')) {
            $data['gambar_pertanyaan'] = $request->file('gambar_pertanyaan')
                ->store('soal/gambar', 'public');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $data['created_by'] = $user->id;
        $data['sekolah_id'] = $user->sekolah_id;

        $soal = Soal::create($data);

        // Simpan opsi jawaban
        if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
            $this->saveOpsi($soal, $request);
        } elseif ($data['tipe_soal'] === 'menjodohkan') {
            $this->savePasangan($soal, $request);
        }

        return redirect()->route('dinas.dinas.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    private function saveOpsi(Soal $soal, Request $request): void
    {
        $labels  = ['A', 'B', 'C', 'D', 'E'];
        $benar   = (array) $request->input('opsi_benar', []);

        foreach ($labels as $i => $label) {
            $teks  = $request->input("opsi_teks.$label");
            $file  = $request->file("opsi_gambar.$label");
            $gambar = null;

            if ($file) {
                $gambar = $file->store('soal/opsi', 'public');
            }

            if ($teks || $gambar) {
                OpsiJawaban::create([
                    'soal_id'  => $soal->id,
                    'label'    => $label,
                    'teks'     => $teks,
                    'gambar'   => $gambar,
                    'is_benar' => in_array($label, $benar),
                    'urutan'   => $i,
                ]);
            }
        }
    }

    private function savePasangan(Soal $soal, Request $request): void
    {
        $kiriTeks  = $request->input('pasangan_kiri_teks', []);
        $kananTeks = $request->input('pasangan_kanan_teks', []);

        foreach ($kiriTeks as $i => $kiri) {
            PasanganSoal::create([
                'soal_id'       => $soal->id,
                'kiri_teks'     => $kiri,
                'kanan_teks'    => $kananTeks[$i] ?? null,
                'urutan'        => $i,
            ]);
        }
    }

    public function edit(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = KategoriSoal::where('is_active', true)->orderBy('urutan')->get();
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

        $data = [
            'kategori_id'       => $validated['kategori_soal_id'],
            'tipe_soal'         => $this->jenisMap[$validated['jenis_soal']],
            'pertanyaan'        => $validated['pertanyaan'],
            'posisi_gambar'     => $validated['posisi_gambar'] ?? null,
            'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
            'bobot'             => $validated['bobot'],
            'pembahasan'        => $validated['pembahasan'] ?? null,
        ];

        if ($request->hasFile('gambar_pertanyaan')) {
            if ($soal->gambar_pertanyaan) Storage::disk('public')->delete($soal->gambar_pertanyaan);
            $data['gambar_pertanyaan'] = $request->file('gambar_pertanyaan')->store('soal/gambar', 'public');
        }

        $soal->update($data);
        $soal->opsiJawaban()->delete();
        $soal->pasangan()->delete();

        if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
            $this->saveOpsi($soal, $request);
        } elseif ($data['tipe_soal'] === 'menjodohkan') {
            $this->savePasangan($soal, $request);
        }

        return redirect()->route('dinas.dinas.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function show(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan', 'kategori']);
        return view('dinas.soal.show', compact('soal'));
    }

    public function destroy(Soal $soal)
    {
        $soal->delete();
        return redirect()->route('dinas.dinas.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }
}
