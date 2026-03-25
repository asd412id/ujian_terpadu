<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LaporanController extends Controller
{
    public function __construct(
        protected LaporanService $laporanService
    ) {}

    public function index(Request $request)
    {
        $sekolah = $this->getSekolah();
        abort_unless($sekolah, 403, 'Anda tidak memiliki akses ke laporan ini.');

        $data = $this->laporanService->getHasilUjianBySekolah($sekolah->id, $request->only([
            'paket_id', 'status', 'search', 'page', 'per_page',
        ]));

        return view('sekolah.laporan.index', [
            'paketList' => $data['paketList'],
            'data'      => $data['data'],
            'rekap'     => $data['rekap'],
        ]);
    }

    protected function getSekolah()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->sekolah;
    }
}
