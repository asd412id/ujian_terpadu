@extends('layouts.base')

@section('title', 'Konfirmasi Ujian')

@section('content')
{{-- Top Navigation Bar --}}
<header class="w-full bg-white border-b border-gray-200 px-6 py-3.5">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <span class="text-sm font-bold text-gray-900">{{ strtoupper(config('app.name')) }}</span>
        </div>
        <a href="{{ route('ujian.lobby') }}"
           class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors font-medium">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
    </div>
</header>

<main class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-lg">
        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 text-white text-center">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h1 class="text-lg font-bold">{{ $paket->nama }}</h1>
                <p class="text-blue-200 text-sm mt-1">{{ $sesiPeserta->sesi->nama_sesi }}</p>
            </div>

            {{-- Detail --}}
            <div class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 rounded-xl px-4 py-3 text-center">
                        <p class="text-xl font-bold text-gray-900">{{ $paket->jumlah_soal }}</p>
                        <p class="text-xs text-gray-500">Jumlah Soal</p>
                    </div>
                    <div class="bg-slate-50 rounded-xl px-4 py-3 text-center">
                        <p class="text-xl font-bold text-gray-900">{{ $paket->durasi_menit }} <span class="text-sm font-normal text-gray-500">menit</span></p>
                        <p class="text-xs text-gray-500">Durasi</p>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-xl px-4 py-3 space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Peserta</span>
                        <span class="font-medium text-gray-900">{{ $peserta->nama }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">NISN</span>
                        <span class="font-medium text-gray-900">{{ $peserta->nisn ?? $peserta->nis }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Jenjang</span>
                        <span class="font-medium text-gray-900">{{ $paket->jenjang }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Jenis Ujian</span>
                        <span class="font-medium text-gray-900">{{ $paket->jenis_ujian }}</span>
                    </div>
                </div>

                {{-- Rules --}}
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <p class="text-sm font-semibold text-amber-800 mb-2">Perhatian:</p>
                    <ul class="text-xs text-amber-700 space-y-1">
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Pastikan koneksi internet stabil sebelum memulai
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Waktu ujian akan berjalan otomatis setelah dimulai
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Jawaban tersimpan otomatis setiap ada perubahan
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Jangan menutup browser selama ujian berlangsung
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Ujian akan berjalan dalam mode <strong>layar penuh (fullscreen)</strong>
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Dilarang berpindah tab, copy/paste, atau membuka aplikasi lain
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Pelanggaran <strong>3 kali</strong> akan mengakibatkan ujian otomatis dikumpulkan
                        </li>
                    </ul>
                </div>

                {{-- Action --}}
                <button type="button" x-data
                   @click="
                       if(await $store.confirmModal.open({title:'Mulai Ujian',message:'Mulai ujian sekarang? Timer akan langsung berjalan dan layar akan masuk mode fullscreen.',confirmText:'Mulai'})) {
                           const el = document.documentElement;
                           const goToExam = () => { window.location.href='{{ route('ujian.mengerjakan', $sesiPeserta->id) }}'; };
                           if (el.requestFullscreen) {
                               el.requestFullscreen().then(goToExam).catch(goToExam);
                           } else if (el.webkitRequestFullscreen) {
                               el.webkitRequestFullscreen(); goToExam();
                           } else {
                               goToExam();
                           }
                       }
                   "
                   class="w-full flex items-center justify-center gap-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-bold px-6 py-3 rounded-xl transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Mulai Ujian Sekarang
                </button>
            </div>
        </div>
    </div>
</main>
@endsection
