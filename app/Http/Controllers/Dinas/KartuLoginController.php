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

    public function cetakSemua(Request $request)
    {
        $pesertaList = $this->kartuLoginService->getKartuAllDinas($request->sekolah_id);

        return view('dinas.kartu.pdf', compact('pesertaList'));
    }

    public function show(Peserta $peserta)
    {
        $data = $this->kartuLoginService->getKartuPeserta($peserta->id);

        return view('dinas.kartu.pdf-satu', [
            'peserta'       => $data['peserta'],
            'passwordKartu' => $data['passwordKartu'],
        ]);
    }
}
