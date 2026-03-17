@extends('layouts.base')

@section('title', 'Ruang Tunggu Ujian')

@section('content')
{{-- Top Navigation Bar --}}
<header class="w-full bg-white border-b border-gray-200 px-4 sm:px-6 py-3.5">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="Logo" class="w-9 h-9 rounded-xl">
            <span class="text-sm font-bold text-gray-900">{{ strtoupper(config('app.name')) }}</span>
        </div>
        <form action="{{ route('ujian.logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-red-600 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>
    </div>
</header>

{{-- Main Content --}}
<main class="flex-1 flex items-start justify-center px-4 py-10 sm:py-14">
    <div class="w-full max-w-md space-y-5">

        {{-- Welcome Card --}}
        <div class="card p-6 text-center">
            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-1">Selamat Datang, {{ $peserta->nama }}</h1>
            <p class="text-sm text-gray-500">{{ $peserta->sekolah->nama }} · Kelas {{ $peserta->kelas }}</p>
        </div>

        {{-- Sesi Tersedia --}}
        @if($sesiTersedia->isNotEmpty())
        <div class="space-y-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-1">Ujian Tersedia</p>
            @foreach($sesiTersedia as $sp)
            @php $sesi = $sp->sesi; $paket = $sesi->paket; @endphp
            <div class="card p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 mb-1">{{ $paket->nama }}</h3>
                        <p class="text-sm text-gray-500 mb-2.5">{{ $sesi->nama_sesi }}</p>
                        <div class="flex flex-wrap gap-4 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $paket->durasi_menit }} menit
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ $paket->jumlah_soal }} soal
                            </span>
                            @if($sesi->waktu_mulai || $sesi->waktu_selesai)
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @if($sesi->waktu_mulai) {{ $sesi->waktu_mulai->format('d/m H:i') }} @endif
                                @if($sesi->waktu_mulai && $sesi->waktu_selesai) — @endif
                                @if($sesi->waktu_selesai) {{ $sesi->waktu_selesai->format('d/m H:i') }} @endif
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        @if(($sp->schedule_status ?? 'open') === 'belum_mulai')
                            <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                Belum Dimulai
                            </span>
                        @elseif(($sp->schedule_status ?? 'open') === 'sudah_selesai')
                            <span class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-full">
                                Waktu Habis
                            </span>
                        @elseif($sesi->status === 'berlangsung' && in_array($sp->status, ['terdaftar','belum_login','login','mengerjakan']))
                            @if($sp->status === 'mengerjakan')
                            <a href="{{ route('ujian.mengerjakan', $sp->id) }}"
                               class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                                Lanjutkan
                            </a>
                            @else
                            <a href="{{ route('ujian.konfirmasi', $sp->id) }}"
                               class="inline-flex items-center gap-1.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                                Mulai Ujian
                            </a>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                Menunggu
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        {{-- Empty State --}}
        <div class="card px-6 py-10 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 mb-1">Tidak ada ujian aktif</p>
            <p class="text-sm text-gray-400">Belum ada sesi ujian yang tersedia untuk Anda saat ini.</p>
        </div>
        @endif

        {{-- Riwayat Selesai --}}
        @if($sesiSelesai->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-1">Riwayat Ujian</p>
            <div class="card p-0 divide-y divide-gray-100">
                @foreach($sesiSelesai as $sp)
                @php $paket = $sp->sesi->paket; @endphp
                <div class="flex items-center justify-between px-5 py-3.5">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $paket->nama }}</p>
                            <p class="text-xs text-gray-400">Selesai</p>
                        </div>
                    </div>
                    @if($sp->nilai_akhir !== null && $paket->tampilkan_hasil)
                    <span class="text-lg font-bold text-gray-900">{{ number_format($sp->nilai_akhir, 0) }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</main>
@endsection
