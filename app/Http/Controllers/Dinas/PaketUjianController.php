<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\KategoriSoal;
use App\Models\Soal;
use App\Models\PaketSoal;
use App\Models\SesiUjian;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaketUjianController extends Controller
{
    public function index()
    {
        $paket = PaketUjian::with(['sekolah', 'pembuat'])
            ->withCount(['paketSoal', 'sesi'])
            ->latest()
            ->paginate(20);

        return view('dinas.paket.index', compact('paket'));
    }

    public function create()
    {
        $kategori = KategoriSoal::where('is_active', true)->orderBy('urutan')->get();
        $sekolah  = Sekolah::where('is_active', true)->orderBy('nama')->get();
        return view('dinas.paket.form', compact('kategori', 'sekolah'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'             => 'required|string|max:200',
            'jenis_ujian'      => 'required|in:TKA_SEKOLAH,SIMULASI_UTBK,TRYOUT,ULANGAN,PAS,PAT,LAINNYA',
            'jenjang'          => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI,SEMUA',
            'deskripsi'        => 'nullable|string',
            'durasi_menit'     => 'required|integer|min:10|max:480',
            'acak_soal'        => 'boolean',
            'acak_opsi'        => 'boolean',
            'tampilkan_hasil'  => 'boolean',
            'boleh_kembali'    => 'boolean',
            'tanggal_mulai'    => 'nullable|date',
            'tanggal_selesai'  => 'nullable|date|after_or_equal:tanggal_mulai',
            'sekolah_id'       => 'nullable|exists:sekolah,id',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $data['created_by'] = $user->id;
        $data['kode']       = strtoupper(Str::random(8));
        $data['status']     = 'draft';

        $paket = PaketUjian::create($data);

        // Buat sesi default
        if ($request->filled('nama_sesi')) {
            SesiUjian::create([
                'paket_id'    => $paket->id,
                'nama_sesi'   => $request->nama_sesi,
                'ruangan'     => $request->ruangan,
                'waktu_mulai' => $data['tanggal_mulai'],
                'status'      => 'persiapan',
            ]);
        }

        return redirect()->route('dinas.paket.show', $paket)
                         ->with('success', 'Paket ujian berhasil dibuat.');
    }

    public function show(PaketUjian $paket)
    {
        $paket->load(['paketSoal.soal.kategori', 'sesi.sesiPeserta', 'sekolah']);

        $bankSoal = Soal::with('kategori')
            ->where('is_active', true)
            ->whereNotIn('id', $paket->paketSoal->pluck('soal_id'))
            ->paginate(10, ['*'], 'soal_page');

        return view('dinas.paket.show', compact('paket', 'bankSoal'));
    }

    public function edit(PaketUjian $paket)
    {
        $sekolah = Sekolah::where('is_active', true)->orderBy('nama')->get();
        return view('dinas.paket.form', compact('paket', 'sekolah'));
    }

    public function update(Request $request, PaketUjian $paket)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:200',
            'jenis_ujian'     => 'required|in:TKA_SEKOLAH,SIMULASI_UTBK,TRYOUT,ULANGAN,PAS,PAT,LAINNYA',
            'jenjang'         => 'required|in:SD,SMP,SMA,SMK,MA,MTs,MI,SEMUA',
            'deskripsi'       => 'nullable|string',
            'durasi_menit'    => 'required|integer|min:10|max:480',
            'acak_soal'       => 'boolean',
            'acak_opsi'       => 'boolean',
            'tampilkan_hasil' => 'boolean',
            'boleh_kembali'   => 'boolean',
            'tanggal_mulai'   => 'nullable|date',
            'tanggal_selesai' => 'nullable|date',
        ]);

        $paket->update($data);
        return redirect()->route('dinas.paket.show', $paket)
                         ->with('success', 'Paket ujian diperbarui.');
    }

    public function destroy(PaketUjian $paket)
    {
        $paket->update(['status' => 'arsip']);
        return redirect()->route('dinas.paket.index')
                         ->with('success', 'Paket ujian diarsipkan.');
    }

    public function publish(PaketUjian $paket)
    {
        if ($paket->paketSoal()->count() === 0) {
            return back()->with('error', 'Paket harus memiliki minimal 1 soal sebelum dipublikasikan.');
        }

        $paket->update(['status' => 'aktif']);
        return back()->with('success', 'Paket ujian berhasil dipublikasikan.');
    }

    public function soalAdd(Request $request, PaketUjian $paket)
    {
        $request->validate(['soal_id' => 'required|exists:soal,id']);

        $already = PaketSoal::where('paket_id', $paket->id)
                             ->where('soal_id', $request->soal_id)
                             ->exists();
        if (!$already) {
            $maxNomor = PaketSoal::where('paket_id', $paket->id)->max('nomor_urut') ?? 0;
            PaketSoal::create([
                'paket_id'  => $paket->id,
                'soal_id'   => $request->soal_id,
                'nomor_urut' => $maxNomor + 1,
            ]);
            $paket->increment('jumlah_soal');
        }

        return back()->with('success', 'Soal berhasil ditambahkan.');
    }

    public function soalRemove(PaketUjian $paket, Soal $soal)
    {
        PaketSoal::where('paket_id', $paket->id)
                 ->where('soal_id', $soal->id)
                 ->delete();
        $paket->decrement('jumlah_soal');
        return back()->with('success', 'Soal dihapus dari paket.');
    }
}
