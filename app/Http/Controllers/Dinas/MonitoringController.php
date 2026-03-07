<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Models\SesiPeserta;
use App\Models\LogAktivitasUjian;

class MonitoringController extends Controller
{
    public function index()
    {
        $sekolahList = Sekolah::withCount(['peserta'])
            ->with(['paketUjian' => fn ($q) => $q->whereHas('sesi', fn ($s) => $s->where('status', 'berlangsung'))])
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        $sesiList = SesiUjian::with(['paket.sekolah', 'pengawas', 'sesiPeserta'])
            ->where('status', 'berlangsung')
            ->latest()
            ->get();

        $summary = [
            'total_sesi'     => $sesiList->count(),
            'peserta_online' => SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
                ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung'))->count(),
            'peserta_ragu'   => 0,
            'sudah_submit'   => SesiPeserta::where('status', 'selesai')
                ->whereDate('updated_at', today())->count(),
        ];

        return view('dinas.monitoring.index', compact('sekolahList', 'sesiList', 'summary'));
    }

    public function sekolah(Sekolah $sekolah)
    {
        $sesiAktif = SesiUjian::with(['paket', 'pengawas', 'sesiPeserta.peserta'])
            ->where('status', 'berlangsung')
            ->whereHas('paket', fn ($q) => $q->where('sekolah_id', $sekolah->id))
            ->get();

        return view('dinas.monitoring.sekolah', compact('sekolah', 'sesiAktif'));
    }

    public function sesi(SesiUjian $sesi)
    {
        $sesi->load(['paket.sekolah', 'sesiPeserta.peserta', 'sesiPeserta.jawaban']);

        $alerts = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit', 'koneksi_putus'])
            ->whereHas('sesiPeserta', fn ($q) => $q->where('sesi_id', $sesi->id))
            ->with('sesiPeserta.peserta')
            ->latest()
            ->take(20)
            ->get();

        $pesertaList = $sesi->sesiPeserta;

        $stats = [
            'total'       => $pesertaList->count(),
            'online'      => $pesertaList->whereIn('status', ['login', 'mengerjakan'])->count(),
            'submit'      => $pesertaList->whereIn('status', ['submit', 'dinilai'])->count(),
            'kosong'      => $pesertaList->where('status', 'belum_login')->count(),
            'belum_mulai' => $pesertaList->where('status', 'belum_login')->count(),
        ];

        return view('dinas.monitoring.sesi', compact('sesi', 'alerts', 'pesertaList', 'stats'));
    }

    public function apiIndex()
    {
        $sesiAktif = SesiUjian::with(['paket.sekolah'])
            ->where('status', 'berlangsung')
            ->get();

        return response()->json([
            'sesi'  => $sesiAktif,
            'total' => $sesiAktif->count(),
        ]);
    }

    public function sekolahAll()
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
        return view('dinas.monitoring.sekolah', compact('sekolahList'));
    }

    public function apiSekolahAll()
    {
        $sekolahList = Sekolah::withCount(['peserta'])
            ->where('is_active', true)
            ->get()
            ->map(function ($s) {
                $sesiAktif = SesiUjian::where('status', 'berlangsung')
                    ->whereHas('paket', fn ($q) => $q->where('sekolah_id', $s->id))
                    ->count();

                $pesertaOnline = SesiPeserta::whereIn('status', ['hadir', 'mengerjakan'])
                    ->whereHas('sesi', fn ($q) => $q->where('status', 'berlangsung')
                        ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $s->id)))
                    ->count();

                $pesertaSelesai = SesiPeserta::where('status', 'selesai')
                    ->whereHas('sesi', fn ($q) => $q->whereDate('created_at', today())
                        ->whereHas('paket', fn ($p) => $p->where('sekolah_id', $s->id)))
                    ->count();

                $cheatingCount = LogAktivitasUjian::whereIn('tipe_event', ['ganti_tab', 'fullscreen_exit'])
                    ->whereDate('created_at', today())
                    ->whereHas('sesiPeserta.sesi.paket', fn ($q) => $q->where('sekolah_id', $s->id))
                    ->count();

                $status = $sesiAktif > 0 ? 'aktif' : ($pesertaSelesai > 0 ? 'selesai' : 'belum');

                return [
                    'id'           => $s->id,
                    'nama_sekolah' => $s->nama,
                    'kode_sekolah' => $s->npsn ?? '–',
                    'sesi_aktif'   => $sesiAktif,
                    'peserta_online' => $pesertaOnline,
                    'peserta_selesai' => $pesertaSelesai,
                    'cheating_count' => $cheatingCount,
                    'status'       => $status,
                ];
            });

        $summary = [
            'total_sekolah'       => $sekolahList->count(),
            'sekolah_aktif'       => $sekolahList->where('status', 'aktif')->count(),
            'total_peserta_aktif' => $sekolahList->sum('peserta_online'),
            'total_selesai'       => $sekolahList->sum('peserta_selesai'),
        ];

        return response()->json([
            'sekolah' => $sekolahList->values(),
            'summary' => $summary,
        ]);
    }

    public function apiSesi(SesiUjian $sesi)
    {
        $sesi->load(['paket', 'sesiPeserta' => fn ($q) => $q->with('peserta')->orderBy('updated_at', 'desc')]);

        $data = $sesi->sesiPeserta->map(fn ($sp) => [
            'nama'          => $sp->peserta->nama ?? '–',
            'no_peserta'    => $sp->peserta->no_peserta ?? '–',
            'status'        => $sp->status,
            'soal_dijawab'  => $sp->jawaban()->count(),
            'last_aktif'    => $sp->updated_at?->diffForHumans(),
        ]);

        return response()->json([
            'sesi'   => [
                'id'          => $sesi->id,
                'nama'        => $sesi->nama_sesi,
                'status'      => $sesi->status,
                'waktu_mulai' => $sesi->waktu_mulai?->format('H:i'),
                'waktu_selesai' => $sesi->waktu_selesai?->format('H:i'),
            ],
            'peserta' => $data,
            'stats' => [
                'total'   => $sesi->sesiPeserta->count(),
                'hadir'   => $sesi->sesiPeserta->whereIn('status', ['hadir', 'mengerjakan'])->count(),
                'selesai' => $sesi->sesiPeserta->where('status', 'selesai')->count(),
                'belum'   => $sesi->sesiPeserta->where('status', 'belum_hadir')->count(),
            ],
        ]);
    }
}
