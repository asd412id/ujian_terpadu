@extends('layouts.base')

@section('title', 'Ruang Tunggu Ujian')

@section('body-class', 'bg-gradient-to-br from-slate-900 to-blue-950 min-h-screen')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-2xl">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">Selamat Datang, {{ $peserta->nama }}</h1>
            <p class="text-blue-300 text-sm">{{ $peserta->sekolah->nama }} · Kelas {{ $peserta->kelas }}</p>
        </div>

        {{-- Sesi Tersedia --}}
        @if($sesiTersedia->isNotEmpty())
        <div class="space-y-4 mb-6">
            <h2 class="text-white font-semibold text-lg">Ujian Tersedia</h2>
            @foreach($sesiTersedia as $sp)
            @php $sesi = $sp->sesi; $paket = $sesi->paket; @endphp
            <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <h3 class="text-white font-semibold text-lg mb-1">{{ $paket->nama }}</h3>
                        <p class="text-blue-300 text-sm mb-3">{{ $sesi->nama_sesi }}</p>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <span class="flex items-center gap-1 text-blue-200">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $paket->durasi_menit }} menit
                            </span>
                            <span class="flex items-center gap-1 text-blue-200">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                </svg>
                                {{ $paket->jumlah_soal }} soal
                            </span>
                            @if($sesi->ruangan)
                            <span class="flex items-center gap-1 text-blue-200">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                {{ $sesi->ruangan }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        @if($sesi->status === 'berlangsung' && in_array($sp->status, ['belum_login','login','mengerjakan']))
                        <a href="{{ route('ujian.mulai', $sp->id) }}"
                           class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold px-5 py-2.5 rounded-xl transition-colors shadow-md">
                            @if($sp->status === 'mengerjakan')
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                </svg>
                                Lanjutkan
                            @else
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                </svg>
                                Mulai Ujian
                            @endif
                        </a>
                        @elseif($sesi->status === 'persiapan')
                        <span class="inline-flex items-center gap-1 bg-amber-500/20 text-amber-300 text-sm font-semibold px-4 py-2 rounded-xl">
                            <svg class="w-4 h-4 animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/>
                            </svg>
                            Menunggu
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 bg-gray-500/20 text-gray-300 text-sm font-semibold px-4 py-2 rounded-xl">
                            Tidak Tersedia
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 text-center mb-6">
            <svg class="w-12 h-12 text-blue-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-white font-semibold mb-1">Tidak ada ujian aktif</p>
            <p class="text-blue-300 text-sm">Belum ada sesi ujian yang tersedia untuk Anda saat ini.</p>
        </div>
        @endif

        {{-- Riwayat Selesai --}}
        @if($sesiSelesai->isNotEmpty())
        <div class="space-y-3">
            <h2 class="text-white/70 font-semibold text-sm uppercase tracking-wide">Riwayat Ujian</h2>
            @foreach($sesiSelesai as $sp)
            <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-white text-sm font-medium">{{ $sp->sesi->paket->nama }}</p>
                    <p class="text-blue-300 text-xs">{{ $sp->sesi->nama_sesi }}</p>
                </div>
                <div class="text-right">
                    <span class="text-green-400 text-xs font-semibold">Selesai</span>
                    @if($sp->nilai_akhir !== null)
                    <p class="text-white text-sm font-bold">{{ number_format($sp->nilai_akhir, 0) }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Footer --}}
        <div class="text-center mt-8">
            <form action="{{ route('ujian.logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-blue-300 hover:text-white text-sm transition-colors">
                    Keluar
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
