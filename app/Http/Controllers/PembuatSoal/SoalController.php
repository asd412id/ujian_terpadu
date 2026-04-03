<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dinas\SoalController as DinasSoalController;
use App\Models\ImportJob;
use App\Models\NarasiSoal;
use App\Models\Soal;
use App\Jobs\ImportSoalWordJob;
use App\Services\ExportSoalWordService;
use App\Services\SoalService;
use App\Repositories\KategoriSoalRepository;
use App\Support\HtmlDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $soal = $this->soalService->getAccessibleSoal(
            userId: Auth::id(),
            kategoriId: $request->kategori,
            tipe: $request->tipe,
            kesulitan: $request->kesulitan,
            search: $request->search,
            perPage: 20
        );

        $kategori = $this->soalService->getActiveKategori();

        // Narasi tab data: tampilkan narasi milik sendiri + narasi dalam kategori yang di-assign
        $narasis = NarasiSoal::with(['kategori', 'pembuat'])
            ->withCount('soalList')
            ->accessibleBy(Auth::id())
            ->when($request->filled('narasi_kategori'), fn ($q) => $q->where('kategori_id', $request->narasi_kategori))
            ->search($request->input('narasi_search'))
            ->latest()
            ->paginate(20, ['*'], 'narasi_page')
            ->withQueryString();

        $trashedCount = Soal::onlyTrashed()->where('created_by', Auth::id())->count();
        $trashedNarasiCount = NarasiSoal::onlyTrashed()->where('created_by', Auth::id())->count();

        $userId = Auth::id();
        $soalCounts = Soal::where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhereIn('soal.id', function ($sub) use ($userId) {
                      $sub->select('soal_id')->from('soal_user')->where('user_id', $userId);
                  })
                  ->orWhereIn('soal.kategori_id', function ($sub) use ($userId) {
                      $sub->select('kategori_soal_id')->from('kategori_soal_user')->where('user_id', $userId);
                  });
            })
            ->selectRaw('kategori_id, count(*) as total')
            ->groupBy('kategori_id')
            ->pluck('total', 'kategori_id');

        return view('pembuat-soal.soal.index', compact('soal', 'kategori', 'narasis', 'trashedCount', 'trashedNarasiCount', 'soalCounts'));
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
            'narasi_id'            => [
                'nullable',
                Rule::exists('narasi_soal', 'id')->where(function ($query) {
                    $userId = Auth::id();
                    $assignedKategoriIds = \Illuminate\Support\Facades\DB::table('kategori_soal_user')
                        ->where('user_id', $userId)
                        ->pluck('kategori_soal_id')
                        ->toArray();
                    $query->whereNull('deleted_at')
                          ->where(function ($q) use ($userId, $assignedKategoriIds) {
                              $q->where('created_by', $userId)
                                ->orWhereIn('kategori_id', $assignedKategoriIds);
                          });
                }),
            ],
            'urutan_dalam_narasi'  => 'nullable|integer|min:1',
        ]);

        $this->soalService->createSoal($validated, $request);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function show(Soal $soal)
    {
        abort_unless($soal->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke soal ini.');

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

        return view('pembuat-soal.soal.show', compact('soal'));
    }

    public function edit(Soal $soal)
    {
        abort_unless($soal->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke soal ini.');

        $soal->load(['opsiJawaban', 'pasangan']);
        $kategoris = $this->soalService->getActiveKategori();
        $narasis = $soal->kategori_id
            ? NarasiSoal::where('kategori_id', $soal->kategori_id)
                ->accessibleBy(Auth::id())
                ->where('is_active', true)
                ->orderBy('judul')
                ->get(['id', 'judul', 'kategori_id'])
            : collect([]);
        return view('pembuat-soal.soal.form', compact('soal', 'kategoris', 'narasis'));
    }

    public function update(Request $request, Soal $soal)
    {
        abort_unless($soal->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke soal ini.');

        $validated = $request->validate([
            'kategori_soal_id'     => 'required|exists:kategori_soal,id',
            'jenis_soal'           => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,benar_salah,menjodohkan,isian,essay',
            'pertanyaan'           => 'required|string',
            'gambar_pertanyaan'    => 'nullable|image|max:5120',
            'posisi_gambar'        => 'nullable|in:atas,bawah,kiri,kanan',
            'tingkat_kesulitan'    => 'required|in:mudah,sedang,sulit',
            'bobot'                => 'required|numeric|min:0|max:100',
            'pembahasan'           => 'nullable|string',
            'narasi_id'            => [
                'nullable',
                Rule::exists('narasi_soal', 'id')->where(function ($query) {
                    $userId = Auth::id();
                    $assignedKategoriIds = \Illuminate\Support\Facades\DB::table('kategori_soal_user')
                        ->where('user_id', $userId)
                        ->pluck('kategori_soal_id')
                        ->toArray();
                    $query->whereNull('deleted_at')
                          ->where(function ($q) use ($userId, $assignedKategoriIds) {
                              $q->where('created_by', $userId)
                                ->orWhereIn('kategori_id', $assignedKategoriIds);
                          });
                }),
            ],
            'urutan_dalam_narasi'  => 'nullable|integer|min:1',
        ]);

        $this->soalService->updateSoal($soal, $validated, $request);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Soal $soal)
    {
        abort_unless($soal->isAccessibleBy(Auth::id()), 403, 'Anda tidak memiliki akses ke soal ini.');

        if ($soal->is_verified) {
            return redirect()->route('pembuat-soal.soal.index')
                             ->with('error', 'Soal yang sudah diverifikasi tidak dapat dihapus. Hubungi admin jika perlu menghapus soal ini.');
        }

        $this->soalService->deleteSoal($soal);

        return redirect()->route('pembuat-soal.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }

    public function trash(Request $request)
    {
        $trashedSoal = $this->soalService->getTrashedSoal(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            perPage: 20,
            createdBy: Auth::id(),
        );

        $kategori = $this->soalService->getActiveKategori();
        $allFilteredIds = $this->soalService->getTrashedSoalIds(
            kategoriId: $request->trash_kategori,
            search: $request->trash_search,
            createdBy: Auth::id(),
        );

        return view('pembuat-soal.soal.trash', compact('trashedSoal', 'kategori', 'allFilteredIds'));
    }

    public function restore(Soal $soal_trashed)
    {
        abort_unless($soal_trashed->created_by === Auth::id(), 403);
        $this->soalService->restoreSoal($soal_trashed);

        return redirect()->route('pembuat-soal.soal.trash')
                         ->with('success', 'Soal berhasil dipulihkan.');
    }

    public function forceDelete(Soal $soal_trashed)
    {
        abort_unless($soal_trashed->created_by === Auth::id(), 403);
        $this->soalService->forceDeleteSoal($soal_trashed);

        return redirect()->route('pembuat-soal.soal.trash')
                         ->with('success', 'Soal berhasil dihapus permanen.');
    }

    public function emptyTrash()
    {
        $count = $this->soalService->emptyTrashByUser(Auth::id());

        return redirect()->route('pembuat-soal.soal.trash')
                         ->with('success', "{$count} soal berhasil dihapus permanen.");
    }

    public function bulkRestore(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->soalService->bulkRestoreSoal($request->ids, Auth::id());

        return redirect()->route('pembuat-soal.soal.trash')
                         ->with('success', "{$count} soal berhasil dipulihkan.");
    }

    public function bulkForceDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        $count = $this->soalService->bulkForceDeleteSoal($request->ids, Auth::id());

        return redirect()->route('pembuat-soal.soal.trash')
                         ->with('success', "{$count} soal berhasil dihapus permanen.");
    }

    public function previewAll(Request $request)
    {
        $kategori = $this->soalService->getActiveKategori();

        $userId = Auth::id();
        $accessScope = function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhereIn('soal.id', function ($sub) use ($userId) {
                  $sub->select('soal_id')->from('soal_user')->where('user_id', $userId);
              })
              ->orWhereIn('soal.kategori_id', function ($sub) use ($userId) {
                  $sub->select('kategori_soal_id')->from('kategori_soal_user')->where('user_id', $userId);
              });
        };

        // Count accessible soal per category (unfiltered) for dropdown display
        $soalCounts = Soal::where($accessScope)
            ->selectRaw('kategori_id, count(*) as total')
            ->groupBy('kategori_id')
            ->pluck('total', 'kategori_id');

        $query = Soal::with(['opsiJawaban', 'pasangan', 'kategori', 'narasi'])
            ->where($accessScope)
            ->orderBy('kategori_id')
            ->orderByRaw('COALESCE(nomor_urut_import, 999999) ASC')
            ->orderBy('id');

        if ($request->filled('kategori')) {
            $query->where('kategori_id', $request->kategori);
        }

        $soalList = $query->get();

        return view('pembuat-soal.soal.preview-all', compact('soalList', 'kategori', 'soalCounts'));
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

        try {
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

    public function exportWord(Request $request)
    {
        $request->validate([
            'kategori_soal_id' => 'required|exists:kategori_soal,id',
        ]);

        $kategori = \App\Models\KategoriSoal::findOrFail($request->kategori_soal_id);
        $soalList = $this->soalService->getExportableSoalForUser($request->kategori_soal_id, Auth::id());

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

    public function templateWord()
    {
        return app(DinasSoalController::class)->templateWord();
    }

    public function templateZip()
    {
        return app(DinasSoalController::class)->templateZip();
    }
}
