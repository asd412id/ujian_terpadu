<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Peserta;
use App\Services\PesertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public function create()
    {
        return view('sekolah.peserta.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'          => 'required|string|max:200',
            'nis'           => 'nullable|string|max:20',
            'nisn'          => 'nullable|string|max:20',
            'kelas'         => 'nullable|string|max:50',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir'  => 'nullable|string|max:100',
            'password_ujian'=> 'nullable|string|min:4|max:30',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_unless($user->sekolah_id, 403);

        $plainPassword = $request->filled('password_ujian') ? $request->input('password_ujian') : null;
        $this->pesertaService->createForSekolah($data, $user->sekolah_id, $plainPassword);

        return redirect()->route('sekolah.peserta.index')->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function edit(Peserta $peserta)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_unless($user->sekolah_id && $peserta->sekolah_id === $user->sekolah_id, 403);

        return view('sekolah.peserta.form', compact('peserta'));
    }

    public function update(Request $request, Peserta $peserta)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_unless($user->sekolah_id && $peserta->sekolah_id === $user->sekolah_id, 403);

        $data = $request->validate([
            'nama'          => 'required|string|max:200',
            'nis'           => 'nullable|string|max:20',
            'nisn'          => 'nullable|string|max:20',
            'kelas'         => 'nullable|string|max:50',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir'  => 'nullable|string|max:100',
            'password_ujian'=> 'nullable|string|min:4|max:30',
            'is_active'     => 'nullable|boolean',
        ]);

        $plainPassword = $request->filled('password_ujian') ? $request->input('password_ujian') : null;
        $data['is_active'] = $request->boolean('is_active');

        $this->pesertaService->updateForSekolah($peserta->id, $data, $plainPassword);

        return redirect()->route('sekolah.peserta.index')->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function destroy(Peserta $peserta)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        abort_unless($user->sekolah_id && $peserta->sekolah_id === $user->sekolah_id, 403);

        try {
            $this->pesertaService->delete($peserta->id);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('sekolah.peserta.index')->with('success', 'Peserta berhasil dihapus.');
    }

    public function destroyAll()
    {
        /** @var \App\Models\User $user */
        $user   = Auth::user();
        $jumlah = $this->pesertaService->deleteAllBySekolah($user->sekolah_id);

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', "Semua data peserta ($jumlah peserta) berhasil dihapus.");
    }

    // =========================================================
    // IMPORT EXCEL
    // =========================================================

    public function showImport()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Dinas admin seharusnya menggunakan fitur import di menu dinas
        if ($user->isDinas()) {
            return redirect()->route('dinas.peserta.import');
        }

        return view('sekolah.peserta.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'mode' => 'required|in:update,replace_all',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Dinas admin seharusnya menggunakan fitur import di menu dinas
        if ($user->isDinas()) {
            return redirect()->route('dinas.peserta.import');
        }

        if (!$user->sekolah_id) {
            return redirect()->route('sekolah.peserta.index')
                             ->with('error', 'Akun Anda tidak terkait dengan sekolah manapun.');
        }

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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Operator sekolah hanya bisa melihat status import milik sekolahnya
        if ($user->isAdminSekolah() && $job->sekolah_id !== $user->sekolah_id) {
            abort(403);
        }

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
        $headers = ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir', 'password'];
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
        $sheet->setCellValueByColumnAndRow(8, 2, '');

        $sheet->setCellValueByColumnAndRow(1, 3, 'Siti Aminah');
        $sheet->setCellValueByColumnAndRow(2, 3, '12346');
        $sheet->setCellValueByColumnAndRow(3, 3, '1234567891');
        $sheet->setCellValueByColumnAndRow(4, 3, 'XII IPA 1');
        $sheet->setCellValueByColumnAndRow(5, 3, 'IPA');
        $sheet->setCellValueByColumnAndRow(6, 3, 'P');
        $sheet->setCellValueByColumnAndRow(7, 3, '2006-08-15');
        $sheet->setCellValueByColumnAndRow(8, 3, 'custom123');

        // Note row for password column
        $sheet->setCellValueByColumnAndRow(8, 4, 'Opsional. Jika kosong, password akan di-generate otomatis.');
        $sheet->getStyle('H4')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B7280'));

        // Style header
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Password header with grey color (optional column)
        $sheet->getStyle('H1')->applyFromArray([
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '6B7280'],
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
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
