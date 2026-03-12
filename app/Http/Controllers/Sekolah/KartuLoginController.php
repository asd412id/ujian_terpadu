<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use App\Models\SesiUjian;
use App\Services\KartuLoginService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KartuLoginController extends Controller
{
    public function __construct(
        protected KartuLoginService $kartuLoginService
    ) {}

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $this->kartuLoginService->generateKartuLogin($user->sekolah_id, [
            'kelas' => $request->kelas,
            'q'     => $request->q,
        ]);

        return view('sekolah.kartu.index', [
            'peserta'   => $data['peserta'],
            'kelasList' => $data['kelasList'],
        ]);
    }

    public function cetakSemua(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $pesertaList = $this->kartuLoginService->getKartuBySekolah($user->sekolah_id);

        return view('sekolah.kartu.pdf', compact('pesertaList'));
    }

    public function show(Peserta $peserta)
    {
        abort_unless($peserta->sekolah_id === Auth::user()->sekolah_id, 403);

        $data = $this->kartuLoginService->getKartuPeserta($peserta->id);

        return view('sekolah.kartu.pdf-satu', [
            'peserta'       => $data['peserta'],
            'passwordKartu' => $data['passwordKartu'],
        ]);
    }

    public function preview(SesiUjian $sesi)
    {
        $sesi->load(['paket', 'sesiPeserta.peserta']);
        abort_unless($sesi->paket?->sekolah_id === Auth::user()->sekolah_id, 403);

        return view('sekolah.kartu.preview', compact('sesi'));
    }

    public function cetak(SesiUjian $sesi)
    {
        $sesi->load('paket');
        abort_unless($sesi->paket?->sekolah_id === Auth::user()->sekolah_id, 403);

        $data = $this->kartuLoginService->getKartuBySesi($sesi->id);

        $pdf = Pdf::loadView('sekolah.kartu.pdf', $data)
            ->setPaper('A4')
            ->setOption('defaultFont', 'sans-serif');

        $filename = 'kartu-login-' . ($data['paket']?->kode ?? 'unknown') . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function cetakSatu(Peserta $peserta)
    {
        abort_unless($peserta->sekolah_id === Auth::user()->sekolah_id, 403);

        $data = $this->kartuLoginService->getKartuPeserta($peserta->id);

        $pdf = Pdf::loadView('sekolah.kartu.pdf-satu', $data)
            ->setPaper([0, 0, 226, 340]) // 8cm x 12cm kartu
            ->setOption('defaultFont', 'sans-serif');

        return $pdf->download('kartu-' . $data['peserta']->username_ujian . '.pdf');
    }
}
