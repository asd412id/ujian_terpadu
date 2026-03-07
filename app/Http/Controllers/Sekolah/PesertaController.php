<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use App\Models\ImportJob;
use App\Jobs\ImportPesertaJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PesertaController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $peserta = Peserta::where('sekolah_id', $user->sekolah_id)
            ->when($request->q, fn ($q) => $q->where('nama', 'like', "%{$request->q}%")
                ->orWhere('nis', 'like', "%{$request->q}%")
                ->orWhere('nisn', 'like', "%{$request->q}%"))
            ->when($request->kelas, fn ($q) => $q->where('kelas', $request->kelas))
            ->when($request->jurusan, fn ($q) => $q->where('jurusan', $request->jurusan))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        $kelasList = Peserta::where('sekolah_id', $user->sekolah_id)
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

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
        $data['sekolah_id'] = $user->sekolah_id;

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('peserta/foto', 'public');
        }

        $password = Peserta::generatePassword();
        $data['username_ujian'] = Peserta::generateUsername($data['nis'] ?? null, $data['nisn'] ?? null, $user->sekolah_id);
        $data['password_ujian'] = Hash::make($password);
        $data['password_plain'] = encrypt($password);

        Peserta::create($data);

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

        // Update username_ujian jika NIS berubah
        if (isset($data['nis']) && $data['nis'] !== $peserta->nis) {
            $data['username_ujian'] = Peserta::generateUsername($data['nis'], $data['nisn'] ?? null, $peserta->sekolah_id);
        }

        $peserta->update($data);

        return redirect()->route('sekolah.peserta.index')
                         ->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function destroy(Peserta $peserta)
    {
        $this->authorizeSekolah($peserta);
        $peserta->delete();
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

        $job = ImportJob::create([
            'created_by' => $user->id,
            'sekolah_id' => $user->sekolah_id,
            'tipe'       => 'peserta_excel',
            'filename'   => $filename,
            'filepath'   => $path,
            'status'     => 'pending',
        ]);

        dispatch(new ImportPesertaJob($job));

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
