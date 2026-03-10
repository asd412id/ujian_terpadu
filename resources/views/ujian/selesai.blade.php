@extends('layouts.base')

@section('title', 'Ujian Selesai')

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
        <form action="{{ route('ujian.logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-red-600 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>
    </div>
</header>

<main class="min-h-screen bg-slate-100 flex items-center justify-center p-6"
      x-data="selesaiApp()"
      x-init="init()">

    {{-- Offline Banner --}}
    <div x-show="!isOnline && hasPendingSync"
         x-transition
         class="fixed top-0 inset-x-0 z-50 bg-amber-500 text-white text-sm text-center py-2 px-4 flex items-center justify-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Menunggu koneksi untuk mengirim jawaban tersisa...
    </div>

    {{-- Syncing Banner --}}
    <div x-show="isSyncing"
         x-transition
         class="fixed top-0 inset-x-0 z-50 bg-blue-600 text-white text-sm text-center py-2 px-4 flex items-center justify-center gap-2">
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Menyinkronkan jawaban ke server...
    </div>

    <div class="w-full max-w-lg">
        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5 text-white text-center">
                <div class="relative w-14 h-14 mx-auto mb-3">
                    <div class="absolute inset-0 rounded-2xl bg-white/20 animate-ping"></div>
                    <div class="relative w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-lg font-bold">Ujian Selesai!</h1>
                <p class="text-green-200 text-sm mt-1">
                    Terima kasih, <strong class="text-white">{{ auth('peserta')->user()->nama }}</strong>
                </p>
            </div>

            {{-- Content --}}
            <div class="px-6 py-5 space-y-4">

                {{-- Ringkasan Pengerjaan --}}
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Ringkasan Pengerjaan</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-slate-50 rounded-xl px-3 py-3 text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['terjawab'] }}</p>
                            <p class="text-xs text-gray-500 mt-1">Terjawab</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl px-3 py-3 text-center">
                            <p class="text-2xl font-bold text-amber-600">{{ $ringkasan['ragu'] }}</p>
                            <p class="text-xs text-gray-500 mt-1">Ditandai</p>
                        </div>
                        <div class="bg-red-50 rounded-xl px-3 py-3 text-center">
                            <p class="text-2xl font-bold text-red-500">{{ $ringkasan['kosong'] }}</p>
                            <p class="text-xs text-gray-500 mt-1">Kosong</p>
                        </div>
                    </div>
                </div>

                {{-- Detail Info --}}
                <div class="bg-slate-50 rounded-xl px-4 py-3 space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Durasi Pengerjaan</span>
                        <span class="font-medium text-gray-900">{{ $ringkasan['durasi'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Selesai Pukul</span>
                        <span class="font-medium text-gray-900">{{ now()->format('H:i:s') }} WIB</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Status Sinkronisasi</span>
                        <span class="font-medium flex items-center gap-1.5"
                              :class="hasPendingSync ? 'text-amber-600' : 'text-green-600'">
                            <span class="w-2 h-2 rounded-full inline-block"
                                  :class="hasPendingSync ? 'bg-amber-500' : 'bg-green-500'"></span>
                            <span x-text="hasPendingSync ? 'Belum tersinkron' : 'Tersinkron'"></span>
                        </span>
                    </div>
                </div>

                {{-- Pesan Offline Sync --}}
                <div x-show="hasPendingSync && !isOnline"
                     x-transition
                     class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700 text-center">
                    <svg class="w-5 h-5 mx-auto mb-2 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Masih ada jawaban yang belum dikirim ke server. Jangan tutup browser ini.<br>
                    Jawaban akan otomatis terkirim saat koneksi tersambung kembali.
                </div>

                {{-- Sukses Sync --}}
                <div x-show="!hasPendingSync"
                     x-transition
                     class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-700 text-center">
                    <svg class="w-5 h-5 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Semua jawaban berhasil diterima server. Hasil ujian akan segera diproses.
                </div>

                {{-- Langkah Selanjutnya --}}
                <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
                    <p class="text-sm font-semibold text-blue-800 mb-2">Langkah selanjutnya:</p>
                    <ul class="text-xs text-blue-700 space-y-1">
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Serahkan alat tulis dan kartu ujian kepada pengawas.
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Tunggu pengumuman hasil dari sekolah / dinas pendidikan.
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="mt-0.5">&#8226;</span>
                            Jangan berbagi soal ujian kepada siapapun.
                        </li>
                    </ul>
                </div>

                {{-- Tombol Keluar --}}
                <form action="{{ route('ujian.logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 active:scale-95
                                   text-gray-700 text-sm font-semibold px-6 py-3 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function selesaiApp() {
    return {
        isOnline: navigator.onLine,
        isSyncing: false,
        hasPendingSync: false,

        async init() {
            window.addEventListener('online',  () => { this.isOnline = true; this.trySyncPending(); });
            window.addEventListener('offline', () => this.isOnline = false);

            await this.checkPendingSync();
            if (this.hasPendingSync && this.isOnline) {
                this.trySyncPending();
            }
        },

        async checkPendingSync() {
            if (typeof Dexie === 'undefined') return;
            try {
                const db = new Dexie('UjianTerpadu');
                db.version(1).stores({ exam_answers: '++id, sesiPesertaId, soalId, synced, idempotency_key' });
                const pending = await db.exam_answers.where('synced').equals(0).count();
                this.hasPendingSync = pending > 0;
            } catch (e) { /* IndexedDB not available */ }
        },

        async trySyncPending() {
            if (!this.hasPendingSync || this.isSyncing) return;
            this.isSyncing = true;
            try {
                if ('serviceWorker' in navigator && 'SyncManager' in window) {
                    const reg = await navigator.serviceWorker.ready;
                    await reg.sync.register('jawaban-sync');
                }
                await new Promise(r => setTimeout(r, 3000));
                await this.checkPendingSync();
            } catch (e) { /* fallback */ } finally {
                this.isSyncing = false;
            }
        }
    };
}
</script>
@endsection
