<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dinas\SoalController as DinasSoalController;
use App\Models\ImportJob;
use App\Models\Soal;
use App\Jobs\ImportSoalWordJob;
use App\Services\SoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

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
            perPage: 20,
            createdBy: Auth::id()
        );

        $kategori = $this->soalService->getActiveKategori();

        return view('pembuat-soal.soal.index', compact('soal', 'kategori'));
    }

    public function create()
    {
        $kategoris = $this->soalService->getActiveKategori();
        $narasis = [];
        return view('pembuat-soal.soal.form', compact('kategoris', 'narasis'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori_soal_id'     => 'required|exists:kategori_soal,id',
            'jenis_soal'           => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,benar_salah,menjodohkan,isian,essay',
            'pertanyaan'           => 'required|string',
            'gambar_pertanyaan'    => 'nullable|image|max:5120',
            'posisi_gambar'        => 'nullable|in:atas,bawah,kiri,kanan',
            'tingkat_kesulitan'    => 'required|in:mudah,sedang,sulit',
            'bobot'                => 'required|numeric|min:0|max:100',
            'pembahasan'           => 'nullable|string',
            'sumber'               => 'nullable|string|max:200',
            'tahun_soal'           => 'nullable|integer|min:2000|max:2099',
            'narasi_id'            => 'nullable|exists:narasi_soal,id',
            'urutan_dalam_narasi'  => 'nullable|integer|min:1',
        ]);

        $this->soalService->createSoal($validated, $request);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function show(Soal $soal)
    {
        abort_unless($soal->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke soal ini.');

        $soal->load(['opsiJawaban', 'pasangan', 'kategori']);

        if (request()->ajax() || request()->wantsJson()) {
            $hasInlineImage = str_contains($soal->pertanyaan ?? '', '<img ');
            return response()->json([
                'id'                => $soal->id,
                'tipe_soal'         => $soal->tipe_soal,
                'pertanyaan'        => $soal->pertanyaan,
                'gambar_soal'       => ($soal->gambar_soal && !$hasInlineImage) ? asset('storage/' . $soal->gambar_soal) : null,
                'kategori'          => $soal->kategori->nama ?? '—',
                'tingkat_kesulitan' => ucfirst($soal->tingkat_kesulitan ?? '—'),
                'bobot'             => $soal->bobot,
                'kunci_jawaban'     => $soal->kunci_jawaban,
                'pembahasan'        => $soal->pembahasan,
                'opsi'              => $soal->opsiJawaban->sortBy('urutan')->values()->map(fn($o) => [
                    'label'    => $o->label,
                    'teks'     => $o->teks,
                    'gambar'   => $o->gambar ? asset('storage/' . $o->gambar) : null,
                    'is_benar' => (bool) $o->is_benar,
                ]),
                'pasangan'          => $soal->pasangan->values()->map(fn($p) => [
                    'kiri'         => $p->kiri_teks,
                    'kiri_gambar'  => $p->kiri_gambar ? asset('storage/' . $p->kiri_gambar) : null,
                    'kanan'        => $p->kanan_teks,
                    'kanan_gambar' => $p->kanan_gambar ? asset('storage/' . $p->kanan_gambar) : null,
                ]),
            ]);
        }

        return view('pembuat-soal.soal.show', compact('soal'));
    }

    public function edit(Soal $soal)
    {
        abort_unless($soal->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke soal ini.');

        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = $this->soalService->getActiveKategori();
        $narasis = $soal->kategori_id
            ? \App\Models\NarasiSoal::where('kategori_id', $soal->kategori_id)
                ->where('created_by', Auth::id())
                ->where('is_active', true)
                ->orderBy('judul')
                ->get(['id', 'judul', 'kategori_id'])
            : collect([]);
        return view('pembuat-soal.soal.form', compact('soal', 'kategoris', 'narasis'));
    }

    public function update(Request $request, Soal $soal)
    {
        abort_unless($soal->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke soal ini.');

        $validated = $request->validate([
            'kategori_soal_id'     => 'required|exists:kategori_soal,id',
            'jenis_soal'           => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,benar_salah,menjodohkan,isian,essay',
            'pertanyaan'           => 'required|string',
            'gambar_pertanyaan'    => 'nullable|image|max:5120',
            'posisi_gambar'        => 'nullable|in:atas,bawah,kiri,kanan',
            'tingkat_kesulitan'    => 'required|in:mudah,sedang,sulit',
            'bobot'                => 'required|numeric|min:0|max:100',
            'pembahasan'           => 'nullable|string',
            'narasi_id'            => 'nullable|exists:narasi_soal,id',
            'urutan_dalam_narasi'  => 'nullable|integer|min:1',
        ]);

        $this->soalService->updateSoal($soal, $validated, $request);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Soal $soal)
    {
        abort_unless($soal->created_by === Auth::id(), 403, 'Anda tidak memiliki akses ke soal ini.');

        if ($soal->is_verified) {
            return redirect()->route('pembuat-soal.soal.index')
                             ->with('error', 'Soal yang sudah diverifikasi tidak dapat dihapus. Hubungi admin jika perlu menghapus soal ini.');
        }

        $this->soalService->deleteSoal($soal);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }

    public function previewAll(Request $request)
    {
        $kategori = $this->soalService->getActiveKategori();

        $query = Soal::with(['opsiJawaban', 'pasangan', 'kategori', 'narasi'])
            ->where('created_by', Auth::id())
            ->orderBy('kategori_id')
            ->orderByRaw('COALESCE(nomor_urut_import, 999999) ASC')
            ->orderBy('id');

        if ($request->filled('kategori')) {
            $query->where('kategori_id', $request->kategori);
        }

        $soalList = $query->get();

        return view('pembuat-soal.soal.preview-all', compact('soalList', 'kategori'));
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $path = $request->file('image')->store('soal/inline', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function showImport()
    {
        $importJobs = $this->soalService->getImportJobsByUser(Auth::id());
        $kategori = $this->soalService->getActiveKategori();

        return view('pembuat-soal.soal.import', compact('importJobs', 'kategori'));
    }

    public function importWord(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|mimes:docx|max:51200',
            'kategori_soal_id' => 'required|exists:kategori_soal,id',
        ]);

        $path = $request->file('file')->store('imports/soal', 'local');

        $importJob = $this->soalService->createImportJob([
            'tipe'       => 'soal_word',
            'filename'   => $request->file('file')->getClientOriginalName(),
            'filepath'   => $path,
            'status'     => 'pending',
            'created_by' => Auth::id(),
            'meta'       => [
                'kategori_soal_id' => $request->kategori_soal_id,
            ],
        ]);

        ImportSoalWordJob::dispatch($importJob);

        return response()->json([
            'message' => 'File berhasil diupload. Import sedang diproses.',
            'job_id'  => $importJob->id,
        ]);
    }

    public function importZip(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|mimes:zip|max:102400',
            'kategori_soal_id' => 'required|exists:kategori_soal,id',
        ]);

        $zipFile = $request->file('file');
        $zip = new ZipArchive;
        $tmpDir = storage_path('app/imports/soal/' . Str::uuid());

        if ($zip->open($zipFile->getRealPath()) !== true) {
            return back()->withErrors(['file' => 'Gagal membuka file ZIP.']);
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        $docxPath = null;
        $imagesPath = null;

        $files = glob($tmpDir . '/*.docx');
        if (empty($files)) {
            $files = glob($tmpDir . '/*/*.docx');
        }

        if (!empty($files)) {
            $docxPath = $files[0];
        }

        if (!$docxPath) {
            $this->cleanupTempDir($tmpDir);
            return back()->withErrors(['file' => 'File ZIP harus berisi file Word (.docx).']);
        }

        foreach (['gambar', 'images', 'img'] as $folder) {
            $candidate = dirname($docxPath) . '/' . $folder;
            if (is_dir($candidate)) {
                $imagesPath = $candidate;
                break;
            }
        }

        if (!$imagesPath) {
            $imagesPath = dirname($docxPath);
        }

        $storedPath = 'imports/soal/' . Str::uuid() . '.docx';
        Storage::disk('local')->put($storedPath, file_get_contents($docxPath));

        $importJob = $this->soalService->createImportJob([
            'tipe'       => 'soal_word',
            'filename'   => $zipFile->getClientOriginalName(),
            'filepath'   => $storedPath,
            'status'     => 'pending',
            'created_by' => Auth::id(),
            'meta'       => [
                'kategori_soal_id' => $request->kategori_soal_id,
            ],
        ]);

        ImportSoalWordJob::dispatch($importJob, $imagesPath);

        return response()->json([
            'message' => 'File ZIP berhasil diupload. Import sedang diproses.',
            'job_id'  => $importJob->id,
        ]);
    }

    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }

    public function importStatus(ImportJob $job)
    {
        abort_unless($job->created_by === Auth::id(), 403);

        return response()->json([
            'status'         => $job->status,
            'total_rows'     => $job->total_rows,
            'processed_rows' => $job->processed_rows,
            'success_rows'   => $job->success_rows,
            'error_rows'     => $job->error_rows,
            'progress'       => $job->progress_percent,
            'errors'         => $job->errors ?? [],
            'message'        => $job->catatan ?? '',
        ]);
    }

    public function templateWord()
    {
        return app(DinasSoalController::class)->templateWord();
    }

    public function templateZip()
    {
        return app(DinasSoalController::class)->templateZip();
    }
}
