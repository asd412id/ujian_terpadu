<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
use App\Models\Soal;
use App\Models\User;
use App\Services\PaketUjianService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaketUjianController extends Controller
{
    public function __construct(
        protected PaketUjianService $paketUjianService
    ) {}

    public function index()
    {
        $paket = $this->paketUjianService->getAllPaginated(20);

        return view('dinas.paket.index', compact('paket'));
    }

    public function create()
    {
        $kategori = $this->paketUjianService->getActiveKategoris();
        $sekolah  = $this->paketUjianService->getActiveSekolahs();
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
            'max_peserta'      => 'nullable|integer|min:1',
        ]);

        $paket = $this->paketUjianService->createPaket(
            $data,
            $request->input('nama_sesi'),
            $request->input('ruangan')
        );

        return redirect()->route('dinas.paket.show', $paket)
                         ->with('success', 'Paket ujian berhasil dibuat.');
    }

    public function show(PaketUjian $paket)
    {
        $paket->load(['paketSoal.soal.kategori', 'sesi.sesiPeserta', 'sesi.pengawas', 'sekolah']);

        $terpilihIds = $paket->paketSoal->pluck('soal_id')->toArray();
        $bankSoal = \App\Models\Soal::with('kategori')
            ->where('is_active', true)
            ->orderBy('kategori_id')
            ->get();

        $bankSoalJson = $bankSoal->map(fn ($s) => [
            'id'         => $s->id,
            'pertanyaan' => strip_tags($s->pertanyaan),
            'tipe_soal'  => $s->tipe_soal,
            'bobot'      => $s->bobot,
            'kategori'   => $s->kategori->nama ?? 'Tanpa Kategori',
            'kategoriId' => $s->kategori_id ?? '_none',
        ])->values();

        $kategoriList = \App\Models\KategoriSoal::where('is_active', true)->orderBy('nama')->get();
        $pengawas = User::where('role', 'pengawas')->orderBy('name')->get();

        return view('dinas.paket.show', compact('paket', 'bankSoal', 'bankSoalJson', 'terpilihIds', 'kategoriList', 'pengawas'));
    }

    public function edit(PaketUjian $paket)
    {
        $sekolah = $this->paketUjianService->getActiveSekolahs();
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
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'sekolah_id'      => 'nullable|exists:sekolah,id',
            'max_peserta'     => 'nullable|integer|min:1',
        ]);

        $this->paketUjianService->updatePaket($paket, $data);

        return redirect()->route('dinas.paket.show', $paket)
                         ->with('success', 'Paket ujian diperbarui.');
    }

    public function destroy(PaketUjian $paket)
    {
        $this->paketUjianService->archivePaket($paket);

        return redirect()->route('dinas.paket.index')
                         ->with('success', 'Paket ujian diarsipkan.');
    }

    public function publish(PaketUjian $paket)
    {
        try {
            $this->paketUjianService->publishPaket($paket);
            return back()->with('success', 'Paket ujian berhasil dipublikasikan.');
        } catch (ValidationException $e) {
            return back()->with('error', 'Paket harus memiliki minimal 1 soal sebelum dipublikasikan.');
        }
    }

    public function draft(PaketUjian $paket)
    {
        $this->paketUjianService->draftPaket($paket);
        return back()->with('success', 'Paket ujian dikembalikan ke draft.');
    }

    public function soalAdd(Request $request, PaketUjian $paket)
    {
        $request->validate(['soal_id' => 'required|exists:soal,id']);

        $this->paketUjianService->addSoalToPaket($paket, $request->soal_id);

        return back()->with('success', 'Soal berhasil ditambahkan.');
    }

    public function soalRemove(PaketUjian $paket, Soal $soal)
    {
        $this->paketUjianService->removeSoalFromPaket($paket, $soal->id);

        return back()->with('success', 'Soal dihapus dari paket.');
    }

    public function soalSync(Request $request, PaketUjian $paket)
    {
        $request->validate([
            'soal_ids'   => 'present|array',
            'soal_ids.*' => 'exists:soal,id',
        ]);

        $soalIds = $request->input('soal_ids', []);
        $this->paketUjianService->syncSoalPaket($paket, $soalIds);

        return back()->with('success', 'Soal paket berhasil diperbarui (' . count($soalIds) . ' soal).');
    }
}
