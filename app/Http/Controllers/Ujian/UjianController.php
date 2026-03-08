<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Models\SesiPeserta;
use App\Services\UjianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UjianController extends Controller
{
    public function __construct(
        protected UjianService $ujianService
    ) {}

    public function index(SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        $result = $this->ujianService->startUjian(
            sesiPesertaId: $sesiPeserta->id,
            pesertaId: $peserta->id,
            requestMeta: [
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]
        );

        // Already submitted — redirect to results page
        if ($result['already_submitted']) {
            return redirect()->route('ujian.selesai', $sesiPeserta);
        }

        $sesiPeserta = $result['sesiPeserta'];
        $paket       = $result['paket'];
        $soalList    = $result['soalList'];
        $jawabanExisting = $result['jawabanExisting'];
        $sisaWaktu   = $result['sisaWaktu'];

        // Pre-process soal list for JS (only needed frontend fields)
        $soalListJs = collect($soalList)->map(fn ($s) => [
            'id'          => $s['id'],
            'gambar_soal' => isset($s['gambar_soal']) && $s['gambar_soal']
                             ? asset('storage/' . $s['gambar_soal']) : null,
            'opsi'        => collect($s['opsi_jawaban'] ?? [])->map(fn ($o) => [
                'id'     => $o['id'],
                'label'  => $o['label'],
                'gambar' => isset($o['gambar']) && $o['gambar']
                            ? asset('storage/' . $o['gambar']) : null,
            ])->values()->all(),
        ])->values()->all();

        return view('ujian.soal', compact(
            'peserta', 'sesiPeserta', 'paket', 'soalList', 'soalListJs', 'jawabanExisting', 'sisaWaktu'
        ));
    }

    public function submit(Request $request, SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        $result = $this->ujianService->selesaikanUjian(
            sesiPesertaId: $sesiPeserta->id,
            pesertaId: $peserta->id
        );

        return redirect()->route('ujian.selesai', $sesiPeserta);
    }

    public function selesai(SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        if ($sesiPeserta->peserta_id !== $peserta->id) {
            abort(403);
        }

        $result     = $this->ujianService->getHasilUjian($sesiPeserta->id);
        $sesiPeserta = $result['sesiPeserta'];
        $ringkasan   = $result['ringkasan'];

        return view('ujian.selesai', compact('sesiPeserta', 'peserta', 'ringkasan'));
    }
}
