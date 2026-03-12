<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Sekolah;
use App\Services\SekolahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SekolahController extends Controller
{
    public function __construct(
        protected SekolahService $sekolahService
    ) {}

    public function index(Request $request)
    {
        $sekolahList = $this->sekolahService->getAllPaginated(20, [
            'q'       => $request->q,
            'jenjang' => $request->jenjang,
        ]);

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

    public function destroyAll()
    {
        $jumlah = $this->sekolahService->deleteAllSekolah();

        return redirect()->route('dinas.sekolah.index')
                         ->with('success', "Semua data sekolah ($jumlah sekolah) berhasil dihapus.");
    }

    // =========================================================
    // IMPORT EXCEL
    // =========================================================

    public function showImport()
    {
        return view('dinas.sekolah.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'mode' => 'required|in:update,replace_all',
        ]);

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();
        $path     = $file->store('imports/sekolah', 'local');

        $importJob = $this->sekolahService->createImportJob([
            'created_by' => Auth::id(),
            'sekolah_id' => null,
            'tipe'       => 'sekolah_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
            'meta'       => ['mode' => $request->input('mode')],
        ]);

        return redirect()->route('dinas.sekolah.import')
                         ->with('success', 'File import sedang diproses. User operator otomatis dibuat untuk setiap sekolah.')
                         ->with('import_job_id', $importJob->id);
    }

    public function importStatus(ImportJob $job)
    {
        return response()->json([
            'status'         => $job->status,
            'total_rows'     => $job->total_rows,
            'processed_rows' => $job->processed_rows,
            'success_rows'   => $job->success_rows,
            'error_rows'     => $job->error_rows,
            'errors'         => $job->errors ?? [],
            'catatan'        => $job->catatan,
        ]);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Header row
        $headers = ['nama', 'npsn', 'jenjang', 'alamat', 'kota', 'telepon', 'email', 'kepala_sekolah'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Contoh data
        $sheet->setCellValueByColumnAndRow(1, 2, 'SMA Negeri 1 Contoh');
        $sheet->setCellValueByColumnAndRow(2, 2, '12345678');
        $sheet->setCellValueByColumnAndRow(3, 2, 'SMA');
        $sheet->setCellValueByColumnAndRow(4, 2, 'Jl. Merdeka No. 1');
        $sheet->setCellValueByColumnAndRow(5, 2, 'Jakarta');
        $sheet->setCellValueByColumnAndRow(6, 2, '021-1234567');
        $sheet->setCellValueByColumnAndRow(7, 2, 'sman1@example.com');
        $sheet->setCellValueByColumnAndRow(8, 2, 'Budi Santoso, M.Pd');

        // Style header
        $headerStyle = [
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->setTitle('Data Sekolah');

        $writer   = new Xlsx($spreadsheet);
        $tmpFile  = tempnam(sys_get_temp_dir(), 'sekolah_template_') . '.xlsx';
        $writer->save($tmpFile);

        return response()->download($tmpFile, 'template_import_sekolah.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
