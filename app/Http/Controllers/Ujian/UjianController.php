<?php

namespace App\Http\Controllers\Ujian;

use App\Http\Controllers\Controller;
use App\Models\SesiPeserta;
use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use App\Services\PenilaianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UjianController extends Controller
{
    public function __construct(private PenilaianService $penilaian) {}

    public function index(SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();

        if ($sesiPeserta->peserta_id !== $peserta->id) abort(403);
        if ($sesiPeserta->status === 'submit') return redirect()->route('ujian.selesai', $sesiPeserta);

        // Set status mengerjakan + catat waktu mulai
        if ($sesiPeserta->status === 'belum_login' || $sesiPeserta->status === 'login') {
            $sesiPeserta->update([
                'status'     => 'mengerjakan',
                'mulai_at'   => $sesiPeserta->mulai_at ?? now(),
                'ip_address' => request()->ip(),
                'browser_info' => request()->userAgent(),
                'device_type'  => $this->detectDevice(),
                'token_ujian'  => Str::random(64),
            ]);

            LogAktivitasUjian::create([
                'sesi_peserta_id' => $sesiPeserta->id,
                'tipe_event'      => 'mulai_ujian',
                'ip_address'      => request()->ip(),
                'created_at'      => now(),
            ]);
        }

        $sesiPeserta->load(['sesi.paket']);
        $paket = $sesiPeserta->sesi->paket;

        // Cache soal untuk performa — hindari query DB saat 1000 user
        $cacheKey = "paket_soal_{$paket->id}_peserta_{$peserta->id}";
        $soalList = Cache::remember($cacheKey, 3600 * 8, function () use ($paket, $sesiPeserta) {
            return $this->getSoalForPeserta($paket, $sesiPeserta);
        });

        // Ambil jawaban yang sudah ada
        $jawabanExisting = JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)
            ->get()
            ->keyBy('soal_id');

        $sisaWaktu = $sesiPeserta->sisa_waktu_detik;

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
        if ($sesiPeserta->peserta_id !== $peserta->id) abort(403);
        if ($sesiPeserta->status === 'submit') {
            return redirect()->route('ujian.selesai', $sesiPeserta);
        }

        $durasi = $sesiPeserta->mulai_at
            ? now()->diffInSeconds($sesiPeserta->mulai_at)
            : 0;

        $sesiPeserta->update([
            'status'               => 'submit',
            'submit_at'            => now(),
            'durasi_aktual_detik'  => $durasi,
        ]);

        // Hitung nilai otomatis
        $hasil = $this->penilaian->hitungNilai($sesiPeserta);
        $sesiPeserta->update($hasil);

        LogAktivitasUjian::create([
            'sesi_peserta_id' => $sesiPeserta->id,
            'tipe_event'      => 'submit_ujian',
            'detail'          => ['durasi' => $durasi],
            'created_at'      => now(),
        ]);

        // Clear cache soal peserta
        $paketId = $sesiPeserta->sesi->paket_id;
        Cache::forget("paket_soal_{$paketId}_peserta_{$peserta->id}");

        return redirect()->route('ujian.selesai', $sesiPeserta);
    }

    public function selesai(SesiPeserta $sesiPeserta)
    {
        /** @var \App\Models\Peserta $peserta */
        $peserta = Auth::guard('peserta')->user();
        if ($sesiPeserta->peserta_id !== $peserta->id) abort(403);

        $sesiPeserta->load(['sesi.paket', 'jawaban.soal']);

        $totalSoal  = $sesiPeserta->sesi->paket->jumlah_soal ?? 0;
        $terjawab   = (int) $sesiPeserta->soal_terjawab;
        $kosong     = max(0, $totalSoal - $terjawab);
        $ragu       = (int) $sesiPeserta->soal_ditandai;

        $mulai   = $sesiPeserta->mulai_at;
        $selesai = $sesiPeserta->submit_at ?? now();
        $durasi  = $mulai ? (int) $mulai->diffInMinutes($selesai) . ' menit' : '-';

        $ringkasan = compact('terjawab', 'kosong', 'ragu', 'durasi');

        return view('ujian.selesai', compact('sesiPeserta', 'peserta', 'ringkasan'));
    }

    private function getSoalForPeserta($paket, SesiPeserta $sesiPeserta): array
    {
        $soalQuery = $paket->soal()
            ->with(['opsiJawaban', 'pasangan', 'kategori'])
            ->get();

        // Urutan soal tersimpan per peserta (untuk konsistensi saat offline)
        if ($sesiPeserta->urutan_soal) {
            $urutan = $sesiPeserta->urutan_soal;
            $soalMap = $soalQuery->keyBy('id');
            $soalList = collect($urutan)->map(fn ($id) => $soalMap[$id] ?? null)->filter()->values();
        } else {
            $soalList = $paket->acak_soal ? $soalQuery->shuffle() : $soalQuery;

            // Acak opsi per soal jika setting aktif
            if ($paket->acak_opsi) {
                $soalList = $soalList->map(function ($soal) {
                    $soal->setRelation('opsiJawaban', $soal->opsiJawaban->shuffle()->values());
                    return $soal;
                });
            }

            // Simpan urutan ke DB untuk persistensi offline
            $sesiPeserta->update(['urutan_soal' => $soalList->pluck('id')->toArray()]);
        }

        return $soalList->toArray();
    }

    private function detectDevice(): string
    {
        $ua = strtolower(request()->userAgent() ?? '');
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) return 'mobile';
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        return 'desktop';
    }
}
