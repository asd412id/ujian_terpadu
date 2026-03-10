<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Peserta;
use App\Models\Sekolah;
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
        $peserta = $this->pesertaService->getAllForDinas([
            'sekolah_id' => $request->sekolah_id,
            'q'          => $request->q,
            'kelas'      => $request->kelas,
        ]);

        $sekolahList = Sekolah::orderBy('nama')->get(['id', 'nama', 'jenjang']);

        return view('dinas.peserta.index', compact('peserta', 'sekolahList'));
    }

    public function create()
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'jenjang']);
        return view('dinas.peserta.form', compact('sekolahList'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sekolah_id'    => 'required|exists:sekolah,id',
            'nama'          => 'required|string|max:200',
            'nis'           => 'nullable|string|max:20',
            'nisn'          => 'nullable|string|max:20',
            'kelas'         => 'nullable|string|max:10',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir'  => 'nullable|string|max:100',
            'password_ujian'=> 'nullable|string|min:4|max:30',
        ]);

        $plainPassword = $request->filled('password_ujian')
            ? $request->input('password_ujian')
            : null;

        $sekolahId = $data['sekolah_id'];
        unset($data['sekolah_id']);

        $this->pesertaService->createForSekolah($data, $sekolahId, $plainPassword);

        return redirect()->route('dinas.peserta.index', ['sekolah_id' => $sekolahId])
                         ->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function edit(Peserta $peserta)
    {
        $sekolahList = Sekolah::orderBy('nama')->get(['id', 'nama', 'jenjang']);
        return view('dinas.peserta.form', compact('peserta', 'sekolahList'));
    }

    public function update(Request $request, Peserta $peserta)
    {
        $data = $request->validate([
            'sekolah_id'    => 'required|exists:sekolah,id',
            'nama'          => 'required|string|max:200',
            'nis'           => 'nullable|string|max:20',
            'nisn'          => 'nullable|string|max:20',
            'kelas'         => 'nullable|string|max:10',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir'  => 'nullable|string|max:100',
            'password_ujian'=> 'nullable|string|min:4|max:30',
            'is_active'     => 'nullable|boolean',
        ]);

        $plainPassword = $request->filled('password_ujian')
            ? $request->input('password_ujian')
            : null;

        $sekolahId        = $data['sekolah_id'];
        $updateData       = $data;
        $updateData['sekolah_id'] = $sekolahId;
        $updateData['is_active']  = $request->boolean('is_active');

        $this->pesertaService->updateForSekolah($peserta->id, $updateData, $plainPassword);

        return redirect()->route('dinas.peserta.index', ['sekolah_id' => $sekolahId])
                         ->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function destroy(Peserta $peserta)
    {
        $sekolahId = $peserta->sekolah_id;
        $this->pesertaService->delete($peserta->id);

        return redirect()->route('dinas.peserta.index', ['sekolah_id' => $sekolahId])
                         ->with('success', 'Peserta berhasil dihapus.');
    }

    public function destroyAll(Request $request)
    {
        $sekolahId = $request->input('sekolah_id');

        if ($sekolahId) {
            $jumlah = Peserta::where('sekolah_id', $sekolahId)->count();
            Peserta::where('sekolah_id', $sekolahId)->delete();
            return redirect()->route('dinas.peserta.index', ['sekolah_id' => $sekolahId])
                             ->with('success', "Semua peserta ($jumlah) dari sekolah ini berhasil dihapus.");
        }

        $jumlah = Peserta::count();
        // Gunakan delete() bukan truncate() agar cascade FK (sesi_peserta, dll) berjalan
        Peserta::query()->delete();
        return redirect()->route('dinas.peserta.index')
                         ->with('success', "Semua data peserta ($jumlah peserta) berhasil dihapus.");
    }

    // =========================================================
    // IMPORT EXCEL
    // =========================================================

    public function showImport(Request $request)
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'jenjang']);
        $selectedSekolahId = $request->sekolah_id;
        return view('dinas.peserta.import', compact('sekolahList', 'selectedSekolahId'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls|max:10240',
            'sekolah_id' => 'required|exists:sekolah,id',
            'mode'       => 'required|in:update,replace_all',
        ]);

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();
        $path     = $file->store('imports/peserta', 'local');

        $importJob = $this->pesertaService->createImportJob([
            'created_by' => Auth::id(),
            'sekolah_id' => $request->input('sekolah_id'),
            'tipe'       => 'peserta_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
            'meta'       => ['mode' => $request->input('mode')],
        ]);

        return redirect()->route('dinas.peserta.import', ['sekolah_id' => $request->input('sekolah_id')])
                         ->with('success', 'File import sedang diproses. Data peserta akan diperbarui sebentar lagi.')
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
