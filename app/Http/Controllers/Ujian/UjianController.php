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

    public function konfirmasi(SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        if ($sesiPeserta->peserta_id !== $peserta->id) {
            abort(403);
        }

        if (in_array($sesiPeserta->status, ['submit', 'dinilai'])) {
            return redirect()->route('ujian.selesai', $sesiPeserta);
        }

        if ($sesiPeserta->status === 'mengerjakan') {
            return redirect()->route('ujian.mengerjakan', $sesiPeserta);
        }

        $sesiPeserta->load(['sesi.paket']);
        $paket = $sesiPeserta->sesi->paket;
        $sesi  = $sesiPeserta->sesi;

        // Schedule enforcement — block if outside time window
        $now = now();
        if ($sesi->waktu_mulai && $now->lt($sesi->waktu_mulai)) {
            return redirect()->route('ujian.lobby')
                ->with('warning', 'Ujian belum dimulai. Jadwal mulai: ' . $sesi->waktu_mulai->format('d/m/Y H:i'));
        }
        if ($sesi->waktu_selesai && $now->gt($sesi->waktu_selesai)) {
            return redirect()->route('ujian.lobby')
                ->with('warning', 'Waktu ujian sudah berakhir.');
        }

        return view('ujian.konfirmasi', compact('peserta', 'sesiPeserta', 'paket'));
    }

    public function mengerjakan(SesiPeserta $sesiPeserta)
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

        if ($result['already_submitted']) {
            return redirect()->route('ujian.selesai', $sesiPeserta);
        }

        $sesiPeserta = $result['sesiPeserta'];
        $paket       = $result['paket'];
        $soalList    = $result['soalList'];
        $jawabanExisting = $result['jawabanExisting'];
        $sisaWaktu   = $result['sisaWaktu'];

        // Pre-process soal list for JS (only needed frontend fields)
        $labels = range('A', 'Z');
        $soalListJs = collect($soalList)->map(fn ($s) => [
            'id'          => $s['id'],
            'narasi_id'   => $s['narasi_id'] ?? null,
            'gambar_soal' => isset($s['gambar_soal']) && $s['gambar_soal']
                             ? asset('storage/' . $s['gambar_soal']) : null,
            'opsi'        => collect($s['opsi_jawaban'] ?? [])->values()->map(fn ($o, $i) => [
                'id'     => $o['id'],
                'label'  => $labels[$i] ?? chr(65 + $i),
                'gambar' => isset($o['gambar']) && $o['gambar']
                            ? asset('storage/' . $o['gambar']) : null,
            ])->values()->all(),
        ])->values()->all();

        $jawabanExistingJs = $jawabanExisting->map(fn($j) => [
            'soal_id'          => $j->soal_id,
            'jawaban_pg'       => $j->jawaban_pg,
            'jawaban_teks'     => $j->jawaban_teks,
            'jawaban_pasangan' => $j->jawaban_pasangan,
            'is_terjawab'      => $j->is_terjawab,
            'is_ditandai'      => $j->is_ditandai,
        ])->values()->all();

        $waktuSelesaiSesi = $sesiPeserta->sesi->waktu_selesai?->timestamp;

        return view('ujian.soal', compact(
            'peserta', 'sesiPeserta', 'paket', 'soalList', 'soalListJs', 'jawabanExistingJs', 'sisaWaktu', 'waktuSelesaiSesi'
        ));
    }

    public function submit(Request $request, SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        abort_unless($sesiPeserta->peserta_id === $peserta->id, 403);

        // If form fallback includes answers, sync them first via JawabanService
        if ($request->filled('answers_json')) {
            try {
                $answers = json_decode($request->input('answers_json'), true);
                if (is_array($answers) && count($answers) > 0) {
                    app(\App\Services\JawabanService::class)
                        ->syncOfflineAnswers($sesiPeserta->token_ujian, $answers, [], true);
                }
            } catch (\Exception $e) {
                \Log::warning('Final sync failed during submit', [
                    'error' => $e->getMessage(),
                    'sesi_peserta' => $sesiPeserta->id,
                ]);
            }
        }

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

        $result         = $this->ujianService->getHasilUjian($sesiPeserta->id);
        $sesiPeserta    = $result['sesiPeserta'];
        $ringkasan      = $result['ringkasan'];
        $sesiToken      = $sesiPeserta->token_ujian;
        $tampilkanHasil = $result['tampilkanHasil'];

        return view('ujian.selesai', compact('sesiPeserta', 'peserta', 'ringkasan', 'sesiToken', 'tampilkanHasil'));
    }
}
