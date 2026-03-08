<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
use App\Models\Soal;
use App\Services\SoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoalController extends Controller
{
    public function __construct(
        protected SoalService $soalService
    ) {}

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $soals = $this->soalService->getBySekolah($user->sekolah_id, [
            'q'        => $request->q,
            'kategori' => $request->kategori,
            'jenis'    => $request->jenis,
        ]);

        $kategori = $this->soalService->getActiveKategori();

        return view('sekolah.soal.index', compact('soals', 'kategori'));
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
        $kategoris = $this->soalService->getActiveKategori();
        return view('sekolah.soal.form', compact('kategoris'));
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
            'bobot'             => 'required|numeric|min:0',
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
            $data['gambar_pertanyaan'] = $request->file('gambar_pertanyaan')->store('soal/gambar', 'public');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $data['created_by'] = $user->id;
        $data['sekolah_id'] = $user->sekolah_id;

        $soal = Soal::create($data);

        if (in_array($data['tipe_soal'], ['pg', 'pg_kompleks'])) {
            $this->saveOpsi($soal, $request);
        } elseif ($data['tipe_soal'] === 'menjodohkan') {
            $this->savePasangan($soal, $request);
        }

        return redirect()->route('sekolah.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = $this->soalService->getActiveKategori();
        return view('sekolah.soal.form', compact('soal', 'kategoris'));
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
            'bobot'             => 'required|numeric|min:0',
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

        return redirect()->route('sekolah.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Soal $soal)
    {
        $this->soalService->deleteSoal($soal);

        return redirect()->route('sekolah.soal.index')
                         ->with('success', 'Soal dihapus.');
    }

    public function showImport()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $jobs = $this->soalService->getImportJobs($user->sekolah_id);
        $kategoris = $this->soalService->getActiveKategori();

        return view('sekolah.soal.import', compact('jobs', 'kategoris'));
    }

    public function importExcel(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:20480']);

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $path     = $request->file('file')->store('imports', 'local');
        $filename = $request->file('file')->getClientOriginalName();

        $job = $this->soalService->createImportJob([
            'created_by' => $user->id,
            'sekolah_id' => $user->sekolah_id,
            'tipe'       => 'soal_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
        ]);

        return response()->json(['job_id' => $job->id, 'message' => 'Import dimulai']);
    }

    public function importWord(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:docx,doc|max:20480']);

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $path     = $request->file('file')->store('imports', 'local');
        $filename = $request->file('file')->getClientOriginalName();

        $job = $this->soalService->createImportJob([
            'created_by' => $user->id,
            'sekolah_id' => $user->sekolah_id,
            'tipe'       => 'soal_word',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
        ]);

        return response()->json(['job_id' => $job->id, 'message' => 'Import dimulai']);
    }

    public function importStatus(ImportJob $job)
    {
        return response()->json([
            'status'          => $job->status,
            'progress'        => $job->progress_percent,
            'total_rows'      => $job->total_rows,
            'processed_rows'  => $job->processed_rows,
            'success_rows'    => $job->success_rows,
            'error_rows'      => $job->error_rows,
            'errors'          => $job->errors,
        ]);
    }

    public function downloadTemplate(string $format)
    {
        $file = match ($format) {
            'excel' => resource_path('templates/soal_template.xlsx'),
            'word'  => resource_path('templates/soal_template.docx'),
            default => abort(404),
        };
        return response()->download($file);
    }

    private function saveOpsi(Soal $soal, Request $request): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $benar  = (array) $request->input('opsi_benar', []);

        foreach ($labels as $i => $label) {
            $teks   = $request->input("opsi_teks.$label");
            $file   = $request->file("opsi_gambar.$label");
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
                'soal_id'   => $soal->id,
                'kiri_teks' => $kiri,
                'kanan_teks'=> $kananTeks[$i] ?? null,
                'urutan'    => $i,
            ]);
        }
    }
}
