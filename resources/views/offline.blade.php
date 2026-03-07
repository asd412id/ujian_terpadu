@extends('layouts.base')

@section('title', 'Tidak Ada Koneksi')

@section('body-class', 'bg-gradient-to-br from-slate-900 to-blue-950 min-h-screen')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="text-center max-w-md">

        {{-- Icon --}}
        <div class="w-24 h-24 bg-white/10 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M12 13a1 1 0 100-2 1 1 0 000 2zm0 0v2"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15.536 8.464a5 5 0 010 7.072M8.464 8.464a5 5 0 000 7.072"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">Tidak Ada Koneksi</h1>
        <p class="text-blue-300 mb-8 text-sm leading-relaxed">
            Halaman yang kamu minta belum tersedia secara offline.<br>
            Periksa koneksi internet dan coba lagi.
        </p>

        {{-- Tombol --}}
        <div class="space-y-3">
            <button onclick="window.location.reload()"
                    class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-semibold
                           py-3 px-6 rounded-xl transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Coba Lagi
            </button>
            <button onclick="history.back()"
                    class="w-full bg-white/10 hover:bg-white/20 text-white font-semibold
                           py-3 px-6 rounded-xl transition-all duration-200">
                Kembali
            </button>
        </div>

        {{-- Status --}}
        <div id="online-status" class="mt-8 flex items-center justify-center gap-2 text-sm text-blue-400">
            <span class="w-2 h-2 bg-red-400 rounded-full"></span>
            <span>Offline</span>
        </div>
    </div>
</div>

<script>
window.addEventListener('online', () => {
    document.getElementById('online-status').innerHTML =
        '<span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span><span class="text-green-400">Koneksi tersambung! Memuat ulang...</span>';
    setTimeout(() => window.location.reload(), 1000);
});
</script>
@endsection
