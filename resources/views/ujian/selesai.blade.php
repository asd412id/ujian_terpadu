@extends('layouts.base')

@section('title', 'Ujian Selesai')

@section('body-class', 'bg-gradient-to-br from-slate-900 to-blue-950 min-h-screen')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-4"
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

    <div class="w-full max-w-xl">

        {{-- Animasi Selesai --}}
        <div class="text-center mb-8">
            <div class="relative w-24 h-24 mx-auto mb-6">
                {{-- Lingkaran Animasi --}}
                <div class="absolute inset-0 rounded-full bg-green-500/20 animate-ping"></div>
                <div class="relative w-24 h-24 bg-green-500 rounded-full flex items-center justify-center shadow-2xl shadow-green-500/30">
                    <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>

            <h1 class="text-3xl font-bold text-white mb-2">Ujian Selesai!</h1>
            <p class="text-blue-300">
                Terima kasih, <strong class="text-white">{{ auth('peserta')->user()->nama }}</strong>.
            </p>
        </div>

        {{-- Ringkasan --}}
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 mb-5 border border-white/20 text-white">
            <h2 class="text-xs font-semibold text-blue-200 uppercase tracking-wide mb-4">Ringkasan Pengerjaan</h2>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold">{{ $ringkasan['terjawab'] }}</p>
                    <p class="text-blue-300 text-xs mt-1">Terjawab</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold text-amber-400">{{ $ringkasan['ragu'] }}</p>
                    <p class="text-blue-300 text-xs mt-1">Ditandai</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4">
                    <p class="text-2xl font-bold text-red-400">{{ $ringkasan['kosong'] }}</p>
                    <p class="text-blue-300 text-xs mt-1">Kosong</p>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-white/10">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-blue-300">Durasi Pengerjaan</span>
                    <span class="font-semibold">{{ $ringkasan['durasi'] }}</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-2">
                    <span class="text-blue-300">Selesai Pukul</span>
                    <span class="font-semibold">{{ now()->format('H:i:s') }} WIB</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-2">
                    <span class="text-blue-300">Status Sinkronisasi</span>
                    <span class="font-semibold flex items-center gap-1.5"
                          :class="hasPendingSync ? 'text-amber-400' : 'text-green-400'">
                        <span class="w-2 h-2 rounded-full inline-block"
                              :class="hasPendingSync ? 'bg-amber-400' : 'bg-green-400'"></span>
                        <span x-text="hasPendingSync ? 'Belum tersinkron' : 'Tersinkron'"></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Pesan Offline Sync --}}
        <div x-show="hasPendingSync && !isOnline"
             x-transition
             class="bg-amber-500/20 border border-amber-500/40 rounded-xl p-4 mb-5 text-sm text-amber-200 text-center">
            <svg class="w-5 h-5 mx-auto mb-2 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Masih ada jawaban yang belum dikirim ke server. Jangan tutup browser ini.<br>
            Jawaban akan otomatis terkirim saat koneksi tersambung kembali.
        </div>

        {{-- Sukses Sync --}}
        <div x-show="!hasPendingSync"
             x-transition
             class="bg-green-500/20 border border-green-500/40 rounded-xl p-4 mb-5 text-sm text-green-200 text-center">
            <svg class="w-5 h-5 mx-auto mb-2 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Semua jawaban berhasil diterima server. Hasil ujian akan segera diproses.
        </div>

        {{-- Info --}}
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-5 mb-6 border border-white/20 text-sm text-blue-100">
            <p class="font-semibold text-white mb-2">Langkah selanjutnya:</p>
            <ul class="space-y-1.5">
                <li class="flex items-start gap-2">
                    <span class="text-blue-400 font-bold flex-shrink-0">1.</span>
                    Serahkan alat tulis dan kartu ujian kepada pengawas.
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-blue-400 font-bold flex-shrink-0">2.</span>
                    Tunggu pengumuman hasil dari sekolah / dinas pendidikan.
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-blue-400 font-bold flex-shrink-0">3.</span>
                    Jangan berbagi soal ujian kepada siapapun.
                </li>
            </ul>
        </div>

        {{-- Tombol Logout --}}
        <form action="{{ route('ujian.logout') }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full bg-white/20 hover:bg-white/30 active:scale-95 text-white font-semibold
                           py-3.5 px-6 rounded-xl transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>

    </div>
</div>

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
                // Trigger background sync melalui Service Worker
                if ('serviceWorker' in navigator && 'SyncManager' in window) {
                    const reg = await navigator.serviceWorker.ready;
                    await reg.sync.register('jawaban-sync');
                }
                // Tunggu sebentar lalu cek ulang
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
