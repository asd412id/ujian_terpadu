<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\Peserta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KartuLoginController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $peserta = Peserta::where('sekolah_id', $user->sekolah_id)
            ->when($request->kelas, fn ($q) => $q->where('kelas', $request->kelas))
            ->when($request->q, fn ($q) => $q->where('nama', 'like', "%{$request->q}%")
                ->orWhere('nis', 'like', "%{$request->q}%"))
            ->orderBy('kelas')
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        $kelasList = Peserta::where('sekolah_id', $user->sekolah_id)
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        return view('sekolah.kartu.index', compact('peserta', 'kelasList'));
    }

    public function cetakSemua(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $sesiIds = SesiUjian::whereHas('paket', fn ($q) => $q->where('sekolah_id', $user->sekolah_id))
            ->pluck('id');

        $pesertaList = SesiPeserta::with('peserta')
            ->whereIn('sesi_id', $sesiIds)
            ->get()
            ->map(function ($sp) {
                $peserta = $sp->peserta;
                $peserta->password_kartu = $peserta->password_plain
                    ? decrypt($peserta->password_plain)
                    : '(hubungi admin)';
                return $peserta;
            });

        return view('sekolah.kartu.pdf', compact('pesertaList'));
    }

    public function show(Peserta $peserta)
    {
        $passwordKartu = $peserta->password_plain
            ? decrypt($peserta->password_plain)
            : '(hubungi admin)';

        return view('sekolah.kartu.pdf-satu', compact('peserta', 'passwordKartu'));
    }

    public function preview(SesiUjian $sesi)
    {
        $sesi->load(['paket', 'sesiPeserta.peserta']);
        return view('sekolah.kartu.preview', compact('sesi'));
    }

    public function cetak(SesiUjian $sesi)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $sesi->load(['paket.sekolah', 'sesiPeserta.peserta']);

        // Ambil password plain (decrypt)
        $pesertaList = $sesi->sesiPeserta->map(function ($sp) {
            $peserta = $sp->peserta;
            $peserta->password_kartu = $peserta->password_plain
                ? decrypt($peserta->password_plain)
                : '(hubungi admin)';
            return $peserta;
        });

        $pdf = Pdf::loadView('sekolah.kartu.pdf', [
            'sesi'        => $sesi,
            'paket'       => $sesi->paket,
            'sekolah'     => $sesi->paket->sekolah,
            'pesertaList' => $pesertaList,
        ])
        ->setPaper('A4')
        ->setOption('defaultFont', 'sans-serif');

        $filename = 'kartu-login-' . $sesi->paket->kode . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function cetakSatu(Peserta $peserta)
    {
        $passwordKartu = $peserta->password_plain
            ? decrypt($peserta->password_plain)
            : '(hubungi admin)';

        $pdf = Pdf::loadView('sekolah.kartu.pdf-satu', compact('peserta', 'passwordKartu'))
            ->setPaper([0, 0, 226, 340]) // 8cm x 12cm kartu
            ->setOption('defaultFont', 'sans-serif');

        return $pdf->download('kartu-' . $peserta->username_ujian . '.pdf');
    }
}
