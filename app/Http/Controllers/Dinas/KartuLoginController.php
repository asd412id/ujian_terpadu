<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Peserta;
use App\Services\KartuLoginService;
use Illuminate\Http\Request;

class KartuLoginController extends Controller
{
    public function __construct(
        protected KartuLoginService $kartuLoginService
    ) {}

    public function index(Request $request)
    {
        $data = $this->kartuLoginService->generateKartuLoginDinas([
            'sekolah_id' => $request->sekolah_id,
            'kelas'      => $request->kelas,
            'q'          => $request->q,
        ]);

        return view('dinas.kartu.index', [
            'peserta'     => $data['peserta'],
            'sekolahList' => $data['sekolahList'],
        ]);
    }

    public function cetakSemua(Request $request)
    {
        $pesertaList = $this->kartuLoginService->getKartuAllDinas($request->sekolah_id);

        return view('sekolah.kartu.pdf-dinas', compact('pesertaList'));
    }

    public function show(Peserta $peserta)
    {
        $data = $this->kartuLoginService->getKartuPeserta($peserta->id);

        return view('sekolah.kartu.pdf-satu-dinas', [
            'peserta'       => $data['peserta'],
            'passwordKartu' => $data['passwordKartu'],
        ]);
    }
}
