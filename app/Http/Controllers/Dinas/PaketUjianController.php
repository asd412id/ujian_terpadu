<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\PaketUjian;
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

        // Only load details of SELECTED soal — not the entire bank
        $terpilihSoalJson = $paket->paketSoal->map(fn ($ps) => [
            'id'         => $ps->soal->id,
            'pertanyaan' => strip_tags($ps->soal->pertanyaan),
            'tipe_soal'  => $ps->soal->tipe_soal,
            'bobot'      => $ps->soal->bobot,
            'kategori'   => $ps->soal->kategori->nama ?? 'Tanpa Kategori',
            'kategoriId' => $ps->soal->kategori_id ?? '_none',
        ])->values();

        $kategoriList = $this->paketUjianService->getActiveKategoris();
        $pengawas = $this->paketUjianService->getPengawasList();

        return view('dinas.paket.show', compact('paket', 'terpilihSoalJson', 'kategoriList', 'pengawas'));
    }

    /**
     * AJAX endpoint: paginated bank soal for the paket soal picker.
     * GET /dinas/paket/{paket}/soal/bank?search=&jenis=&kategori=&page=&all=
     */
    public function bankSoal(Request $request, PaketUjian $paket)
    {
        $filters = [
            'search'   => trim($request->get('search', '')),
            'jenis'    => $request->get('jenis'),
            'kategori' => $request->get('kategori'),
            'all'      => $request->boolean('all'),
        ];

        return response()->json(
            $this->paketUjianService->getBankSoalFiltered($paket, $filters)
        );
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
        $this->paketUjianService->softDeletePaket($paket);

        return redirect()->route('dinas.paket.index')
                         ->with('success', 'Paket ujian dihapus. Anda dapat memulihkannya dari halaman Sampah.');
    }

    public function trash()
    {
        $paket = $this->paketUjianService->getTrashedPaginated(20);

        return view('dinas.paket.trash', compact('paket'));
    }

    public function restore(PaketUjian $paket)
    {
        $this->paketUjianService->restorePaket($paket);

        return redirect()->route('dinas.paket.trash')
                         ->with('success', 'Paket ujian berhasil dipulihkan.');
    }

    public function forceDelete(PaketUjian $paket)
    {
        $this->paketUjianService->forceDeletePaket($paket);

        return redirect()->route('dinas.paket.trash')
                         ->with('success', 'Paket ujian dihapus permanen beserta semua data terkait.');
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
