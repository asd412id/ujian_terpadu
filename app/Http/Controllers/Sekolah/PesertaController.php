<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\PesertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PesertaController extends Controller
{
    public function __construct(
        protected PesertaService $pesertaService
    ) {}

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $peserta = $this->pesertaService->getBySekolah($user->sekolah_id, [
            'q'       => $request->q,
            'kelas'   => $request->kelas,
            'jurusan' => $request->jurusan,
        ]);

        $kelasList = $this->pesertaService->getKelasList($user->sekolah_id);

        return view('sekolah.peserta.index', compact('peserta', 'kelasList'));
    }

    public function destroyAll()
    {
        /** @var \App\Models\User $user */
        $user   = Auth::user();
        $jumlah = \App\Models\Peserta::where('sekolah_id', $user->sekolah_id)->count();
        \App\Models\Peserta::where('sekolah_id', $user->sekolah_id)->delete();

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', "Semua data peserta ($jumlah peserta) berhasil dihapus.");
    }

    // =========================================================
    // IMPORT EXCEL
    // =========================================================

    public function showImport()
    {
        return view('sekolah.peserta.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'mode' => 'required|in:update,replace_all',
        ]);

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $file     = $request->file('file');
        $path     = $file->store('imports/peserta', 'local');
        $filename = $file->getClientOriginalName();

        $job = $this->pesertaService->createImportJob([
            'created_by' => $user->id,
            'sekolah_id' => $user->sekolah_id,
            'tipe'       => 'peserta_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
            'meta'       => ['mode' => $request->input('mode')],
        ]);

        return redirect()->route('sekolah.peserta.import')
                         ->with('job_id', $job->id)
                         ->with('success', 'File sedang diproses. Tunggu sebentar...');
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
        ]);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Header row
        $headers = ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Contoh data
        $sheet->setCellValueByColumnAndRow(1, 2, 'Ahmad Fauzi');
        $sheet->setCellValueByColumnAndRow(2, 2, '12345');
        $sheet->setCellValueByColumnAndRow(3, 2, '1234567890');
        $sheet->setCellValueByColumnAndRow(4, 2, 'XII IPA 1');
        $sheet->setCellValueByColumnAndRow(5, 2, 'IPA');
        $sheet->setCellValueByColumnAndRow(6, 2, 'L');
        $sheet->setCellValueByColumnAndRow(7, 2, '2006-05-20');

        $sheet->setCellValueByColumnAndRow(1, 3, 'Siti Aminah');
        $sheet->setCellValueByColumnAndRow(2, 3, '12346');
        $sheet->setCellValueByColumnAndRow(3, 3, '1234567891');
        $sheet->setCellValueByColumnAndRow(4, 3, 'XII IPA 1');
        $sheet->setCellValueByColumnAndRow(5, 3, 'IPA');
        $sheet->setCellValueByColumnAndRow(6, 3, 'P');
        $sheet->setCellValueByColumnAndRow(7, 3, '2006-08-15');

        // Style header
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->setTitle('Data Peserta');

        $writer  = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'peserta_template_') . '.xlsx';
        $writer->save($tmpFile);

        return response()->download($tmpFile, 'template_import_peserta.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
