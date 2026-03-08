<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Services\LobbyService;
use Illuminate\Support\Facades\Auth;

class LobbyController extends Controller
{
    public function __construct(
        protected LobbyService $lobbyService
    ) {}

    public function index()
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        $lobbyData = $this->lobbyService->getLobbyData($peserta->id);

        $sesiTersedia = $lobbyData['sesiTersedia'];
        $sesiSelesai  = $lobbyData['sesiSelesai'];

        return view('ujian.lobby', compact('peserta', 'sesiTersedia', 'sesiSelesai'));
    }
}
