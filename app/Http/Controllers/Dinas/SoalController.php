<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Soal;
use App\Jobs\ImportSoalWordJob;
use App\Services\SoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            'jenis_soal'        => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,benar_salah,menjodohkan,isian,essay',
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
            'jenis_soal'        => 'required|in:pilihan_ganda,pilihan_ganda_kompleks,benar_salah,menjodohkan,isian,essay',
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

        return view('dinas.soal.show', compact('soal'));
    }

    public function destroy(Soal $soal)
    {
        $this->soalService->deleteSoal($soal);

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Soal berhasil dihapus.');
    }

    public function destroyAll()
    {
        $this->soalService->deleteAllSoal();

        return redirect()->route('dinas.soal.index')
                         ->with('success', 'Semua soal berhasil dihapus.');
    }

    public function previewAll(Request $request)
    {
        $kategori = $this->soalService->getActiveKategori();

        $query = Soal::with(['opsiJawaban', 'pasangan', 'kategori'])
            ->orderBy('kategori_soal_id')
            ->orderBy('id');

        if ($request->filled('kategori')) {
            $query->where('kategori_soal_id', $request->kategori);
        }

        $soalList = $query->get();

        return view('dinas.soal.preview-all', compact('soalList', 'kategori'));
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
            'file'             => 'required|file|mimes:docx,doc|max:51200',
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

        $zip->extractTo($tmpDir);
        $zip->close();

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
            // Cleanup
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

    public function templateWord(): StreamedResponse
    {
        $phpWord = new PhpWord();

        $titleStyle = ['bold' => true, 'size' => 14];
        $headingStyle = ['bold' => true, 'size' => 11, 'color' => '1a56db'];
        $normalStyle = ['size' => 11];
        $boldStyle = ['bold' => true, 'size' => 11];
        $italicStyle = ['italic' => true, 'size' => 10, 'color' => '6b7280'];
        $noteStyle = ['italic' => true, 'size' => 10, 'color' => 'dc2626'];

        $section = $phpWord->addSection();

        $section->addText('Template Import Soal', $titleStyle);
        $section->addText('Gunakan format berikut untuk mengimport soal. Setiap soal diawali nomor urut.', $italicStyle);
        $section->addTextBreak(1);

        // ── PG ──
        $section->addText('PILIHAN GANDA', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('1. Apa ibu kota Indonesia?', $boldStyle);
        $section->addText('    Gambar: peta_indonesia.png', $noteStyle);
        $section->addText('    a. Bandung', $normalStyle);
        $section->addText('    b. Surabaya', $normalStyle);
        $section->addText('    c. Jakarta', $normalStyle);
        $section->addText('    d. Yogyakarta', $normalStyle);
        $section->addText('    Jawaban: C', $normalStyle);
        $section->addTextBreak(1);

        // ── PG dengan gambar opsi ──
        $section->addText('PILIHAN GANDA DENGAN GAMBAR OPSI', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('2. Manakah gambar bendera Indonesia?', $boldStyle);
        $section->addText('    a. Bendera Merah Putih | gambar: bendera_id.png', $normalStyle);
        $section->addText('    b. Bendera Jepang | gambar: bendera_jp.png', $normalStyle);
        $section->addText('    c. Bendera Thailand | gambar: bendera_th.png', $normalStyle);
        $section->addText('    d. Bendera Malaysia | gambar: bendera_my.png', $normalStyle);
        $section->addText('    Jawaban: A', $normalStyle);
        $section->addTextBreak(1);

        // ── PG Kompleks ──
        $section->addText('PILIHAN GANDA KOMPLEKS', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('3. [PG_KOMPLEKS] Manakah yang merupakan bilangan prima?', $boldStyle);
        $section->addText('    a. 2', $normalStyle);
        $section->addText('    b. 4', $normalStyle);
        $section->addText('    c. 7', $normalStyle);
        $section->addText('    d. 9', $normalStyle);
        $section->addText('    e. 11', $normalStyle);
        $section->addText('    Jawaban: A, C, E', $normalStyle);
        $section->addTextBreak(1);

        // ── Menjodohkan ──
        $section->addText('MENJODOHKAN', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('4. [MENJODOHKAN] Jodohkan negara dengan ibu kotanya:', $boldStyle);
        $section->addText('    Indonesia | gambar: indonesia.png = Jakarta | gambar: jakarta.png', $normalStyle);
        $section->addText('    Jepang = Tokyo', $normalStyle);
        $section->addText('    Thailand = Bangkok', $normalStyle);
        $section->addText('    Malaysia = Kuala Lumpur', $normalStyle);
        $section->addText('    (Format gambar opsional: kiri | gambar: file.png = kanan | gambar: file.png)', $italicStyle);
        $section->addTextBreak(1);

        // ── Isian ──
        $section->addText('ISIAN SINGKAT', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('5. [ISIAN] Ibu kota Jepang adalah ___', $boldStyle);
        $section->addText('    Jawaban: Tokyo', $normalStyle);
        $section->addTextBreak(1);

        // ── Essay ──
        $section->addText('ESSAY', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('6. [ESSAY] Jelaskan proses terjadinya hujan!', $boldStyle);
        $section->addText('    Gambar: siklus_air.png', $noteStyle);
        $section->addText('    Jawaban: (tulis jawaban contoh atau kosongkan)', $normalStyle);
        $section->addTextBreak(1);

        // ── Benar/Salah ──
        $section->addText('BENAR / SALAH', $headingStyle);
        $section->addTextBreak(0);
        $section->addText('7. [BENAR_SALAH] Tentukan benar atau salah pernyataan berikut tentang air:', $boldStyle);
        $section->addText('    1) Air mendidih pada suhu 100°C di tekanan standar (BENAR)', $normalStyle);
        $section->addText('    2) Es memiliki massa jenis lebih besar dari air (SALAH)', $normalStyle);
        $section->addText('    3) H2O adalah rumus kimia garam dapur (SALAH)', $normalStyle);
        $section->addText('    4) Air merupakan pelarut universal (BENAR)', $normalStyle);
        $section->addTextBreak(2);

        // ── Notes ──
        $section->addText('CATATAN PENTING:', $boldStyle);
        $section->addListItem('Tandai jenis soal dengan tag [PG_KOMPLEKS], [MENJODOHKAN], [ISIAN], [ESSAY], atau [BENAR_SALAH] setelah nomor soal.', 0, $normalStyle);
        $section->addListItem('Soal tanpa tag yang memiliki opsi a/b/c/d dianggap Pilihan Ganda biasa.', 0, $normalStyle);
        $section->addListItem('Soal tanpa tag dan tanpa opsi dianggap Essay.', 0, $normalStyle);
        $section->addListItem('Untuk PG Kompleks, pisahkan jawaban benar dengan koma: Jawaban: A, C, E', 0, $normalStyle);
        $section->addListItem('Untuk Menjodohkan, gunakan tanda = untuk memisahkan pasangan kiri dan kanan. Gambar opsional: kiri | gambar: file.png = kanan | gambar: file.png', 0, $normalStyle);
        $section->addListItem('Untuk Benar/Salah, gunakan format: 1) Pernyataan (BENAR) atau 1) Pernyataan (SALAH)', 0, $normalStyle);
        $section->addTextBreak(1);
        $section->addText('TAG OPSIONAL:', $boldStyle);
        $section->addListItem('Tingkat kesulitan: [tingkat: mudah], [tingkat: sedang], atau [tingkat: sulit] — default: sedang', 0, $normalStyle);
        $section->addListItem('Bobot nilai: [bobot: 2] — default: 1. Bisa ditaruh di baris soal atau baris terpisah.', 0, $normalStyle);
        $section->addListItem('Contoh: 1. [PG_KOMPLEKS] [tingkat: sulit] [bobot: 3] Manakah bilangan prima?', 0, $italicStyle);
        $section->addTextBreak(1);
        $section->addText('GAMBAR:', $boldStyle);
        $section->addListItem('Untuk soal bergambar, sisipkan gambar langsung di dokumen Word ATAU gunakan format teks: [gambar: namafile.png]', 0, $normalStyle);
        $section->addListItem('Untuk opsi bergambar, tambahkan setelah teks opsi: a. Teks opsi | gambar: namafile.png', 0, $normalStyle);
        $section->addListItem('Jika menggunakan referensi nama file, masukkan file gambar ke folder "gambar/" lalu ZIP bersama file .docx ini.', 0, $normalStyle);
        $section->addListItem('Upload file .docx langsung (tanpa gambar) atau .zip (dengan gambar).', 0, $normalStyle);

        $fileName = 'template_import_soal.docx';

        return response()->streamDownload(function () use ($phpWord) {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function templateZip(): StreamedResponse
    {
        // Generate the Word template
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $titleStyle = ['bold' => true, 'size' => 14, 'color' => '1a56db'];
        $boldStyle  = ['bold' => true, 'size' => 11];
        $normalStyle = ['size' => 11];
        $grayStyle   = ['size' => 10, 'color' => '6B7280', 'italic' => true];

        // Instructions
        $section->addText('TEMPLATE IMPORT SOAL (ZIP + GAMBAR)', $titleStyle);
        $section->addText('Format ini mendukung soal dengan gambar. Masukkan gambar ke folder gambar/.', $grayStyle);
        $section->addTextBreak(1);

        $section->addText('PANDUAN FORMAT:', $boldStyle);
        $section->addListItem('Setiap soal diawali nomor: 1. Pertanyaan', 0, $normalStyle);
        $section->addListItem('Untuk gambar soal, gunakan: [gambar: namafile.png] di baris pertanyaan', 0, $normalStyle);
        $section->addListItem('Opsi PG: a. teks opsi', 0, $normalStyle);
        $section->addListItem('Opsi dengan gambar: a. teks opsi | gambar: namafile.png', 0, $normalStyle);
        $section->addListItem('Jawaban: huruf opsi (A) atau beberapa dipisah koma (A,C)', 0, $normalStyle);
        $section->addListItem('Tag jenis: [PG_KOMPLEKS], [MENJODOHKAN], [ISIAN], [ESSAY], [BENAR_SALAH]', 0, $normalStyle);
        $section->addListItem('Tag tingkat: [tingkat: mudah], [tingkat: sedang], [tingkat: sulit] — default: sedang', 0, $normalStyle);
        $section->addListItem('Tag bobot: [bobot: 2] — default: 1. Bisa di baris soal atau baris terpisah.', 0, $normalStyle);
        $section->addTextBreak(1);

        // PG with image options
        $section->addText('CONTOH PILIHAN GANDA DENGAN GAMBAR OPSI', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('1. Manakah gambar bendera Indonesia?', $boldStyle);
        $section->addText('[gambar: soal_bendera.png]', $grayStyle);
        $section->addText('a. Bendera Jepang | gambar: bendera_jp.png', $normalStyle);
        $section->addText('b. Bendera Indonesia | gambar: bendera_id.png', $normalStyle);
        $section->addText('c. Bendera Thailand | gambar: bendera_th.png', $normalStyle);
        $section->addText('d. Bendera Malaysia | gambar: bendera_my.png', $normalStyle);
        $section->addText('Jawaban: B', $normalStyle);
        $section->addTextBreak(1);

        // PG without images
        $section->addText('CONTOH PILIHAN GANDA TANPA GAMBAR', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('2. Apa ibu kota Indonesia?', $boldStyle);
        $section->addText('a. Bandung', $normalStyle);
        $section->addText('b. Surabaya', $normalStyle);
        $section->addText('c. Jakarta', $normalStyle);
        $section->addText('d. Yogyakarta', $normalStyle);
        $section->addText('Jawaban: C', $normalStyle);
        $section->addTextBreak(1);

        // Menjodohkan
        $section->addText('CONTOH MENJODOHKAN', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('3. [MENJODOHKAN] Jodohkan negara dengan ibu kotanya:', $boldStyle);
        $section->addText('Indonesia | gambar: indonesia.png = Jakarta | gambar: jakarta.png', $normalStyle);
        $section->addText('Jepang = Tokyo', $normalStyle);
        $section->addText('Thailand = Bangkok', $normalStyle);
        $section->addTextBreak(1);

        // Isian
        $section->addText('CONTOH ISIAN SINGKAT', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('4. Ibu kota Jepang adalah ___', $boldStyle);
        $section->addText('Jawaban: Tokyo', $normalStyle);
        $section->addTextBreak(1);

        // Essay
        $section->addText('CONTOH ESSAY', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('5. Jelaskan proses terjadinya hujan!', $boldStyle);
        $section->addText('Jawaban: Proses terjadinya hujan meliputi evaporasi, kondensasi, dan presipitasi.', $normalStyle);
        $section->addTextBreak(1);

        // Benar/Salah
        $section->addText('CONTOH BENAR / SALAH', $titleStyle, ['alignment' => Jc::LEFT]);
        $section->addTextBreak(1);

        $section->addText('6. [BENAR_SALAH] Tentukan benar atau salah pernyataan berikut tentang air:', $boldStyle);
        $section->addText('1) Air mendidih pada suhu 100°C di tekanan standar (BENAR)', $normalStyle);
        $section->addText('2) Es memiliki massa jenis lebih besar dari air (SALAH)', $normalStyle);
        $section->addText('3) H2O adalah rumus kimia garam dapur (SALAH)', $normalStyle);
        $section->addText('4) Air merupakan pelarut universal (BENAR)', $normalStyle);

        $section->addTextBreak(2);
        $section->addText('STRUKTUR ZIP:', $boldStyle);
        $section->addListItem('soal_import.zip', 0, $normalStyle);
        $section->addListItem('    template_soal.docx  (file ini)', 0, $normalStyle);
        $section->addListItem('    gambar/', 0, $normalStyle);
        $section->addListItem('        soal_bendera.png', 0, $normalStyle);
        $section->addListItem('        bendera_jp.png', 0, $normalStyle);
        $section->addListItem('        bendera_id.png', 0, $normalStyle);
        $section->addListItem('        ...', 0, $normalStyle);

        // Create ZIP with docx + empty gambar folder
        $tmpDocx = tempnam(sys_get_temp_dir(), 'soal_tpl_') . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tmpDocx);

        $fileName = 'template_import_soal_zip.zip';

        return response()->streamDownload(function () use ($tmpDocx) {
            $zip = new ZipArchive();
            $tmpZip = tempnam(sys_get_temp_dir(), 'soal_zip_') . '.zip';

            $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($tmpDocx, 'template_soal.docx');
            $zip->addEmptyDir('gambar');
            // Add a README
            $readme = "PANDUAN IMPORT SOAL DENGAN GAMBAR\n";
            $readme .= "================================\n\n";
            $readme .= "1. Edit file template_soal.docx sesuai format yang sudah disediakan.\n";
            $readme .= "2. Masukkan semua file gambar ke folder gambar/\n";
            $readme .= "3. Referensikan gambar di Word dengan format:\n";
            $readme .= "   - Gambar soal: [gambar: namafile.png]\n";
            $readme .= "   - Gambar opsi: a. Teks opsi | gambar: namafile.png\n";
            $readme .= "4. ZIP seluruh isi folder ini (template_soal.docx + gambar/)\n";
            $readme .= "5. Upload file .zip melalui halaman Import Soal\n";
            $zip->addFromString('README.txt', $readme);
            $zip->close();

            readfile($tmpZip);

            @unlink($tmpDocx);
            @unlink($tmpZip);
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
