<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\NarasiSoal;
use App\Models\Soal;
use App\Jobs\ImportSoalWordJob;
use App\Repositories\KategoriSoalRepository;
use App\Services\ExportSoalWordService;
use App\Services\SoalService;
use App\Support\HtmlDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\SoalTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SoalController extends Controller
{
    public function __construct(
        protected SoalService $soalService,
        protected KategoriSoalRepository $kategoriSoalRepository,
        protected ExportSoalWordService $exportService,
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

        $narasiQuery = NarasiSoal::with('kategori')
            ->withCount('soalList')
            ->search($request->input('narasi_search'));

        if ($request->filled('narasi_kategori')) {
            $narasiQuery->where('kategori_id', $request->narasi_kategori);
        }
        $narasis = $narasiQuery->latest()->paginate(20, ['*'], 'narasi_page');

        $trashedCount = Soal::onlyTrashed()->count();
        $trashedNarasiCount = NarasiSoal::onlyTrashed()->count();

        $soalCounts = Soal::selectRaw('kategori_id, count(*) as total')
            ->groupBy('kategori_id')
            ->pluck('total', 'kategori_id');

        return view('dinas.soal.index', compact('soal', 'kategori', 'narasis', 'trashedCount', 'trashedNarasiCount', 'soalCounts'));
    }

    public function create()
    {
        $kategoris = $this->soalService->getActiveKategori();
        $narasis = [];
        return view('dinas.soal.form', compact('kategoris', 'narasis'));
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

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = $this->soalService->getActiveKategori();
        $narasis = $soal->kategori_id
            ? \App\Models\NarasiSoal::where('kategori_id', $soal->kategori_id)->where('is_active', true)->orderBy('judul')->get(['id', 'judul', 'kategori_id'])
            : collect([]);
        return view('dinas.soal.form', compact('soal', 'kategoris', 'narasis'));
    }

    public function update(Request $request, Soal $soal)
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
            'narasi_id'            => 'nullable|exists:narasi_soal,id',
            'urutan_dalam_narasi'  => 'nullable|integer|min:1',
        ]);

        $this->soalService->updateSoal($soal, $validated, $request);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function show(Soal $soal)
    {
        $soal->load(['opsiJawaban', 'pasangan', 'kategori', 'narasi', 'pembuat']);

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
                'sumber'            => $soal->sumber,
                'tahun_soal'        => $soal->tahun_soal,
                'pembuat'           => $soal->pembuat->name ?? null,
                'narasi'            => $soal->narasi ? [
                    'judul'  => $soal->narasi->judul,
                    'konten' => (string) HtmlDisplay::render($soal->narasi->konten),
                ] : null,
                'opsi'              => $soal->opsiJawaban->sortBy('urutan')->values()->map(fn($o) => [
                    'label'    => $o->label,
                    'teks'     => $o->teks,
                    'gambar'   => $o->gambar ? asset('storage/' . $o->gambar) : null,
                    'is_benar' => (bool) $o->is_benar,
                ]),
                'pasangan'          => $soal->pasangan->values()->map(fn($p) => [
                    'kiri'         => HtmlDisplay::plainText($p->kiri_teks),
                    'kiri_gambar'  => $p->kiri_gambar ? asset('storage/' . $p->kiri_gambar) : null,
                    'kanan'        => HtmlDisplay::plainText($p->kanan_teks),
                    'kanan_gambar' => $p->kanan_gambar ? asset('storage/' . $p->kanan_gambar) : null,
                ]),
            ]);
        }

        return view('dinas.soal.show', compact('soal'));
    }

    /**
     * Upload image from Tiptap editor (paste/drop).
     */
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

    public function destroy(Soal $soal)
    {
        $this->soalService->deleteSoal($soal);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }

    public function trash(Request $request)
    {
        $trashedSoal = $this->soalService->getTrashedSoal(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            perPage: 20
        );

        $kategori = $this->soalService->getActiveKategori();
        $allFilteredIds = $this->soalService->getTrashedSoalIds(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
        );

        return view('dinas.soal.trash', compact('trashedSoal', 'kategori', 'allFilteredIds'));
    }

    public function restore(Soal $soal_trashed)
    {
        $this->soalService->restoreSoal($soal_trashed);

        return redirect()->route('dinas.soal.trash')
                         ->with('success', 'Soal berhasil dipulihkan.');
    }

    public function forceDelete(Soal $soal_trashed)
    {
        $this->soalService->forceDeleteSoal($soal_trashed);

        return redirect()->route('dinas.soal.trash')
                         ->with('success', 'Soal berhasil dihapus permanen.');
    }

    public function emptyTrash()
    {
        $count = $this->soalService->emptyTrash();

        return redirect()->route('dinas.soal.trash')
                         ->with('success', "{$count} soal berhasil dihapus permanen.");
    }

    public function bulkRestore(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->soalService->bulkRestoreSoal($request->ids);

        return redirect()->route('dinas.soal.trash')
                         ->with('success', "{$count} soal berhasil dipulihkan.");
    }

    public function bulkForceDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->soalService->bulkForceDeleteSoal($request->ids);

        return redirect()->route('dinas.soal.trash')
                         ->with('success', "{$count} soal berhasil dihapus permanen.");
    }

    public function destroyAll(Request $request)
    {
        $kategoriId = $request->input('kategori');

        if ($kategoriId) {
            $kategori = \App\Models\KategoriSoal::findOrFail($kategoriId);
            $this->soalService->deleteSoalByKategori($kategoriId);
            $msg = "Semua soal kategori \"{$kategori->nama}\" berhasil dihapus.";
        } else {
            $this->soalService->deleteAllSoal();
            $msg = 'Semua soal berhasil dihapus.';
        }

        return redirect()->route('dinas.soal.index')
                         ->with('success', $msg);
    }

    public function previewAll(Request $request)
    {
        $kategori = $this->soalService->getActiveKategori();

        // Count soal per category (unfiltered) for dropdown display
        $soalCounts = Soal::selectRaw('kategori_id, count(*) as total')
            ->groupBy('kategori_id')
            ->pluck('total', 'kategori_id');

        $query = Soal::with(['opsiJawaban', 'pasangan', 'kategori', 'narasi'])
            ->orderBy('kategori_id')
            ->orderByRaw('COALESCE(nomor_urut_import, 999999) ASC')
            ->orderBy('id');

        if ($request->filled('kategori')) {
            $query->where('kategori_id', $request->kategori);
        }

        $soalList = $query->paginate(50)->withQueryString();
        $startNumber = ($soalList->currentPage() - 1) * $soalList->perPage();

        return view('dinas.soal.preview-all', compact('soalList', 'kategori', 'soalCounts', 'startNumber'));
    }

    public function showImport()
    {
        $importJobs = $this->soalService->getImportJobsByUser(auth()->id());
        $kategori = $this->soalService->getActiveKategori();

        return view('dinas.soal.import', compact('importJobs', 'kategori'));
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
            'created_by' => auth()->id(),
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

        // C2 fix: Validate ZIP entries for path traversal before extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                return back()->withErrors(['file' => 'File ZIP mengandung path yang tidak valid.']);
            }
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        // M6 fix: Wrap in try/finally to ensure temp dir cleanup on error
        try {
            // Find .docx file in extracted folder
            $docxPath = null;
            $imagesPath = null;

            $files = glob($tmpDir . '/*.docx');
            if (empty($files)) {
                // Check one level deep
                $files = glob($tmpDir . '/*/*.docx');
            }

            if (!empty($files)) {
                $docxPath = $files[0];
            }

            if (!$docxPath) {
                $this->cleanupTempDir($tmpDir);
                return back()->withErrors(['file' => 'File ZIP harus berisi file Word (.docx).']);
            }

            // Find gambar folder
            foreach (['gambar', 'images', 'img'] as $folder) {
                $candidate = dirname($docxPath) . '/' . $folder;
                if (is_dir($candidate)) {
                    $imagesPath = $candidate;
                    break;
                }
            }

            // If no subfolder found, check if images are alongside the docx
            if (!$imagesPath) {
                $imagesPath = dirname($docxPath);
            }

            // Store docx to local disk for the job
            $storedPath = 'imports/soal/' . Str::uuid() . '.docx';
            Storage::disk('local')->put($storedPath, file_get_contents($docxPath));

            $importJob = $this->soalService->createImportJob([
                'tipe'       => 'soal_word',
                'filename'   => $zipFile->getClientOriginalName(),
                'filepath'   => $storedPath,
                'status'     => 'pending',
                'created_by' => auth()->id(),
                'meta'       => [
                    'kategori_soal_id' => $request->kategori_soal_id,
                ],
            ]);

            ImportSoalWordJob::dispatch($importJob, $imagesPath);

            return response()->json([
                'message' => 'File ZIP berhasil diupload. Import sedang diproses.',
                'job_id'  => $importJob->id,
            ]);
        } catch (\Exception $e) {
            $this->cleanupTempDir($tmpDir);
            throw $e;
        }
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
        abort_unless($job->created_by === auth()->id(), 403);

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

    public function exportWord(Request $request): StreamedResponse
    {
        $request->validate([
            'kategori_soal_id' => 'required|exists:kategori_soal,id',
        ]);

        $kategori = \App\Models\KategoriSoal::findOrFail($request->kategori_soal_id);
        $soalList = $this->soalService->getExportableSoal($request->kategori_soal_id);

        if ($soalList->count() > 1000) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kategori_soal_id' => 'Kategori ini memiliki ' . $soalList->count() . ' soal. Maksimal 1000 soal per export untuk menghindari timeout.',
            ]);
        }

        $phpWord = $this->exportService->generate($soalList);

        $safeNama = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $kategori->nama);
        $fileName = 'export_soal_' . $safeNama . '_' . date('Y-m-d') . '.docx';

        return response()->streamDownload(function () use ($phpWord) {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function templateWord(): StreamedResponse
    {
        return app(SoalTemplateService::class)->templateWord();
    }

    public function templateZip(): StreamedResponse
    {
        return app(SoalTemplateService::class)->templateZip();
    }
}

