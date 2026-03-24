@extends('layouts.base')

@section('title', 'Konfirmasi Ujian')

@section('content')
{{-- Top Navigation Bar --}}
<header class="w-full bg-white border-b border-gray-200 px-4 sm:px-6 py-3.5">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="Logo" class="w-9 h-9 rounded-xl">
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

<main class="min-h-screen bg-slate-100 flex items-center justify-center p-4 sm:p-6">
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
                        <span class="font-medium text-gray-900">{{ $paket->jenis_ujian_label }}</span>
                    </div>
                </div>

                {{-- Rules --}}
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 sm:px-5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-amber-900">Perhatian sebelum memulai ujian</p>
                            <p class="mt-1 text-xs leading-5 text-amber-800/80">Baca ketentuan berikut agar ujian berjalan lancar dan tidak terhenti di tengah pengerjaan.</p>
                        </div>
                    </div>

                    <ul class="mt-3 space-y-2.5 text-sm leading-5 text-amber-900">
                        <li class="flex items-start gap-2.5">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Pastikan koneksi internet stabil sebelum memulai.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Waktu ujian akan langsung berjalan otomatis setelah ujian dimulai.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Jawaban tersimpan otomatis setiap ada perubahan.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Jangan menutup browser selama ujian berlangsung.</span>
                        </li>
                        <li class="flex items-start gap-2.5 rounded-lg bg-white/60 px-3 py-2">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Pada perangkat <strong>desktop</strong>, ujian akan berjalan dalam mode <strong>layar penuh (fullscreen)</strong> setelah dimulai.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>Dilarang berpindah tab, copy/paste, atau membuka aplikasi lain selama ujian.</span>
                        </li>
                        <li class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-100/70 px-3 py-2 font-medium">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600"></span>
                            <span>Pelanggaran <strong>3 kali</strong> akan mengakibatkan ujian otomatis dikumpulkan.</span>
                        </li>
                    </ul>
                </div>

                {{-- Action --}}
                <div x-data="{
                        isStarting: false,
                        async activateDesktopFullscreen() {
                            if (window.matchMedia('(max-width: 1023px)').matches) {
                                return;
                            }

                            const el = document.documentElement;

                            try {
                                if (el.requestFullscreen) {
                                    await el.requestFullscreen();
                                } else if (el.webkitRequestFullscreen) {
                                    el.webkitRequestFullscreen();
                                }
                            } catch (_) {
                            }
                        },
                        async startExam() {
                            if (this.isStarting) {
                                return;
                            }

                            const confirmed = await $store.confirmModal.open({
                                title: 'Mulai Ujian',
                                message: 'Mulai ujian sekarang? Timer akan langsung berjalan. Pada perangkat desktop, mode fullscreen akan diaktifkan saat ujian dibuka.',
                                confirmText: 'Mulai'
                            });

                            if (!confirmed) {
                                return;
                            }

                            this.isStarting = true;
                            await this.activateDesktopFullscreen();

                            setTimeout(() => {
                                window.location.href='{{ route('ujian.mengerjakan', $sesiPeserta->id) }}';
                            }, 180);
                        }
                    }">
                    <button type="button"
                        @click="startExam()"
                        :disabled="isStarting"
                        data-start-exam="true"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white transition-colors cursor-pointer hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-blue-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="isStarting ? 'Menyiapkan Ujian...' : 'Mulai Ujian Sekarang'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
