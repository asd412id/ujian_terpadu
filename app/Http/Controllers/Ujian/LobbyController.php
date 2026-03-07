<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Models\SesiPeserta;
use App\Models\PaketUjian;
use Illuminate\Support\Facades\Auth;

class LobbyController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        // Ambil semua sesi yang tersedia untuk peserta ini
        $sesiTersedia = SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $peserta->id)
            ->whereIn('status', ['belum_login', 'login', 'mengerjakan'])
            ->get();

        $sesiSelesai = SesiPeserta::with(['sesi.paket'])
            ->where('peserta_id', $peserta->id)
            ->where('status', 'submit')
            ->latest('submit_at')
            ->get();

        return view('ujian.lobby', compact('peserta', 'sesiTersedia', 'sesiSelesai'));
    }
}
