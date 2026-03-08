<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use App\Services\PesertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'kelas'         => 'nullable|string|max:10',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir'  => 'nullable|string|max:100',
            'foto'          => 'nullable|image|max:2048',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('peserta/foto', 'public');
        }

        $plainPassword = $request->filled('password_ujian')
            ? $request->input('password_ujian')
            : null;

        $this->pesertaService->createForSekolah($data, $user->sekolah_id, $plainPassword);

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function edit(Peserta $peserta)
    {
        $this->authorizeSekolah($peserta);
        return view('sekolah.peserta.form', compact('peserta'));
    }

    public function update(Request $request, Peserta $peserta)
    {
        $this->authorizeSekolah($peserta);

        $data = $request->validate([
            'nama'          => 'required|string|max:200',
            'nis'           => 'nullable|string|max:20',
            'nisn'          => 'nullable|string|max:20',
            'kelas'         => 'nullable|string|max:10',
            'jurusan'       => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'foto'          => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('peserta/foto', 'public');
        }

        $plainPassword = $request->filled('password_ujian')
            ? $request->input('password_ujian')
            : null;

        $this->pesertaService->updateForSekolah($peserta->id, $data, $plainPassword);

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function destroy(Peserta $peserta)
    {
        $this->authorizeSekolah($peserta);

        $this->pesertaService->delete($peserta->id);

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', 'Peserta berhasil dihapus.');
    }

    public function showImport()
    {
        return view('sekolah.peserta.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $path     = $request->file('file')->store('imports', 'local');
        $filename = $request->file('file')->getClientOriginalName();

        $job = $this->pesertaService->createImportJob([
            'created_by' => $user->id,
            'sekolah_id' => $user->sekolah_id,
            'tipe'       => 'peserta_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
        ]);

        return redirect()->route('sekolah.peserta.import')
                         ->with('job_id', $job->id)
                         ->with('success', 'File sedang diproses. Tunggu sebentar...');
    }

    public function downloadTemplate()
    {
        return response()->download(
            resource_path('templates/peserta_template.xlsx'),
            'template_import_peserta.xlsx'
        );
    }

    private function authorizeSekolah(Peserta $peserta): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($peserta->sekolah_id !== $user->sekolah_id && ! $user->isDinas()) {
            abort(403);
        }
    }
}
