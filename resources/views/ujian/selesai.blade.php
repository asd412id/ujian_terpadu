@extends('layouts.base')

@section('title', 'Ujian Selesai')

@section('content')
<div x-data="selesaiApp()" x-init="init()">

{{-- Top Navigation Bar --}}
<header class="w-full bg-white border-b border-gray-200 px-6 py-3.5">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="Logo" class="w-9 h-9 rounded-xl">
            <span class="text-sm font-bold text-gray-900">{{ strtoupper(config('app.name')) }}</span>
        </div>
        <template x-if="!hasPendingSync">
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
        </template>
    </div>
</header>

<main class="min-h-screen bg-slate-100 flex items-center justify-center p-6">

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
                            <p class="text-2xl font-bold text-gray-900" x-text="terjawab"></p>
                            <p class="text-xs text-gray-500 mt-1">Terjawab</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl px-3 py-3 text-center">
                            <p class="text-2xl font-bold text-amber-600" x-text="ragu"></p>
                            <p class="text-xs text-gray-500 mt-1">Ditandai</p>
                        </div>
                        <div class="bg-red-50 rounded-xl px-3 py-3 text-center">
                            <p class="text-2xl font-bold text-red-500" x-text="kosong"></p>
                            <p class="text-xs text-gray-500 mt-1">Kosong</p>
                        </div>
                    </div>
                    <p x-show="countsFromLocal" x-transition
                       class="text-xs text-amber-600 text-center mt-1">
                        * Berdasarkan data lokal — akan diperbarui setelah sinkronisasi
                    </p>
                </div>

                {{-- Hasil Nilai (hanya jika tampilkan_hasil aktif di paket) --}}
                <template x-if="tampilkanHasil">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Hasil Penilaian</p>

                        {{-- Loading state: scoring sedang diproses --}}
                        <div x-show="nilaiLoading" class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-center">
                            <svg class="w-6 h-6 animate-spin text-blue-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-sm text-blue-700 font-medium">Menghitung nilai...</p>
                            <p class="text-xs text-blue-500 mt-1">Hasil akan muncul dalam beberapa detik</p>
                        </div>

                        {{-- Nilai ready --}}
                        <div x-show="!nilaiLoading && nilaiAkhir !== null" x-transition class="space-y-3">
                            {{-- Nilai besar --}}
                            <div class="rounded-xl p-5 text-center"
                                 :class="nilaiAkhir >= 70 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <p class="text-xs font-medium mb-1"
                                   :class="nilaiAkhir >= 70 ? 'text-green-600' : 'text-red-600'"
                                   x-text="nilaiAkhir >= 70 ? 'LULUS' : 'TIDAK LULUS'"></p>
                                <p class="text-4xl font-black"
                                   :class="nilaiAkhir >= 70 ? 'text-green-700' : 'text-red-700'"
                                   x-text="parseFloat(nilaiAkhir).toFixed(1)"></p>
                                <p class="text-xs text-gray-500 mt-1">Nilai Akhir</p>
                            </div>

                            {{-- Detail benar/salah/kosong --}}
                            <div class="grid grid-cols-3 gap-2">
                                <div class="bg-green-50 rounded-lg px-2 py-2 text-center">
                                    <p class="text-lg font-bold text-green-700" x-text="jumlahBenar ?? '-'"></p>
                                    <p class="text-[10px] text-gray-500">Benar</p>
                                </div>
                                <div class="bg-red-50 rounded-lg px-2 py-2 text-center">
                                    <p class="text-lg font-bold text-red-600" x-text="jumlahSalah ?? '-'"></p>
                                    <p class="text-[10px] text-gray-500">Salah</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg px-2 py-2 text-center">
                                    <p class="text-lg font-bold text-gray-500" x-text="jumlahKosong ?? '-'"></p>
                                    <p class="text-[10px] text-gray-500">Kosong</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

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
                    <span x-text="tampilkanHasil && nilaiAkhir !== null ? 'Semua jawaban berhasil diterima dan dinilai.' : 'Semua jawaban berhasil diterima server. Hasil ujian akan segera diproses.'"></span>
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

                {{-- Tombol Sinkronkan Ulang (saat masih pending) --}}
                <template x-if="hasPendingSync">
                    <button @click="trySyncPending()"
                            :disabled="isSyncing"
                            class="w-full flex items-center justify-center gap-2 text-sm font-semibold px-6 py-3 rounded-xl transition-all duration-200"
                            :class="isSyncing
                                ? 'bg-blue-100 text-blue-400 cursor-wait'
                                : 'bg-amber-100 hover:bg-amber-200 active:scale-95 text-amber-700'">
                        <template x-if="isSyncing">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <template x-if="!isSyncing">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </template>
                        <span x-text="isSyncing ? 'Menyinkronkan...' : 'Coba Sinkronkan Ulang'"></span>
                    </button>
                </template>

                {{-- Tombol Keluar (hanya muncul setelah tersinkron) --}}
                <template x-if="!hasPendingSync">
                    <form action="{{ route('ujian.logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 active:scale-95
                                       text-white text-sm font-semibold px-6 py-3 rounded-xl transition-all duration-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Keluar
                        </button>
                    </form>
                </template>
            </div>
        </div>
    </div>
</main>

</div>{{-- end x-data selesaiApp --}}

<script src="https://unpkg.com/dexie@3/dist/dexie.min.js"></script>
<script>
window.SELESAI_CONFIG = {
    sesiPesertaId: '{{ $sesiPeserta->id }}',
    sesiToken: '{{ $sesiToken ?? '' }}',
    totalSoal: {{ $ringkasan['terjawab'] + $ringkasan['kosong'] }},
    serverTerjawab: {{ $ringkasan['terjawab'] }},
    serverRagu: {{ $ringkasan['ragu'] }},
    serverKosong: {{ $ringkasan['kosong'] }},
    tampilkanHasil: {{ $tampilkanHasil ? 'true' : 'false' }},
    nilaiAkhir: {{ $sesiPeserta->nilai_akhir !== null ? $sesiPeserta->nilai_akhir : 'null' }},
    jumlahBenar: {{ $sesiPeserta->jumlah_benar ?? 'null' }},
    jumlahSalah: {{ $sesiPeserta->jumlah_salah ?? 'null' }},
    jumlahKosong: {{ $sesiPeserta->jumlah_kosong ?? 'null' }},
    statusUrl: '{{ route("api.ujian.status", $sesiToken) }}',
};

function selesaiApp() {
    return {
        isOnline: navigator.onLine,
        isSyncing: false,
        hasPendingSync: false,
        syncRetries: 0,
        maxRetries: 10,
        _db: null,
        _retryTimer: null,
        _nilaiPollTimer: null,
        terjawab: window.SELESAI_CONFIG.serverTerjawab,
        ragu: window.SELESAI_CONFIG.serverRagu,
        kosong: window.SELESAI_CONFIG.serverKosong,
        countsFromLocal: false,
        tampilkanHasil: window.SELESAI_CONFIG.tampilkanHasil,
        nilaiAkhir: window.SELESAI_CONFIG.nilaiAkhir,
        jumlahBenar: window.SELESAI_CONFIG.jumlahBenar,
        jumlahSalah: window.SELESAI_CONFIG.jumlahSalah,
        jumlahKosong: window.SELESAI_CONFIG.jumlahKosong,
        nilaiLoading: window.SELESAI_CONFIG.tampilkanHasil && window.SELESAI_CONFIG.nilaiAkhir === null,

        _getDb() {
            if (!this._db) {
                this._db = new Dexie('UjianTerpaduDB');
                this._db.version(1).stores({
                    exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
                    exam_state:   'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
                    image_status: 'url, cached, error',
                });
            }
            return this._db;
        },

        async init() {
            window.addEventListener('online',  () => { this.isOnline = true; this.syncRetries = 0; this.trySyncPending(); });
            window.addEventListener('offline', () => this.isOnline = false);

            // Listen for SW trigger
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (e) => {
                    if (e.data?.type === 'TRIGGER_SYNC') this.trySyncPending();
                });
            }

            await this.checkPendingSync();

            // If server shows 0 terjawab but we have local answers, use local counts
            await this.updateCountsFromLocal();

            if (this.hasPendingSync && this.isOnline) {
                this.trySyncPending();
            }

            // Poll for scoring result if tampilkan_hasil is on and nilai not ready yet
            if (this.tampilkanHasil && this.nilaiAkhir === null) {
                this.pollNilai();
            }
        },

        async pollNilai() {
            if (!this.tampilkanHasil || this.nilaiAkhir !== null) return;

            const cfg = window.SELESAI_CONFIG;
            let attempts = 0;
            const maxAttempts = 30;

            const poll = async () => {
                if (this.nilaiAkhir !== null || attempts >= maxAttempts) {
                    this.nilaiLoading = false;
                    return;
                }
                attempts++;
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000);
                    const res = await fetch(cfg.statusUrl, { signal: controller.signal, headers: { 'Accept': 'application/json' } });
                    clearTimeout(timeoutId);

                    if (res.ok) {
                        const data = await res.json();
                        if (data.nilai_akhir !== null && data.nilai_akhir !== undefined) {
                            this.nilaiAkhir = parseFloat(data.nilai_akhir);
                            this.jumlahBenar = data.jumlah_benar ?? this.jumlahBenar;
                            this.jumlahSalah = data.jumlah_salah ?? this.jumlahSalah;
                            this.jumlahKosong = data.jumlah_kosong ?? this.jumlahKosong;
                            this.nilaiLoading = false;
                            return;
                        }
                    }
                } catch (e) {
                    console.warn('[Selesai] pollNilai error:', e.message);
                }
                // Retry with 2s interval
                this._nilaiPollTimer = setTimeout(poll, 2000);
            };

            poll();
        },

        async updateCountsFromLocal() {
            try {
                const db = this._getDb();
                const cfg = window.SELESAI_CONFIG;
                const localAnswers = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .toArray();

                // Count local answers that have actual jawaban content
                const localTerjawab = localAnswers.filter(a => {
                    const j = a.jawaban;
                    if (!j) return false;
                    if (j.terjawab === true) return true;
                    if (j.pg?.length > 0) return true;
                    if (j.teks !== undefined && j.teks !== '') return true;
                    if (j.benarSalah && Object.keys(j.benarSalah).length > 0) return true;
                    if (j.pasangan && Object.keys(j.pasangan).length > 0) return true;
                    return false;
                }).length;

                // Use the higher count between server and local
                if (localTerjawab > this.terjawab) {
                    this.terjawab = localTerjawab;
                    this.kosong = Math.max(0, cfg.totalSoal - localTerjawab);
                    this.countsFromLocal = true;
                }
            } catch (e) {
                console.warn('[Selesai] updateCountsFromLocal error:', e.message);
            }
        },

        async checkPendingSync() {
            try {
                const db = this._getDb();
                const cfg = window.SELESAI_CONFIG;
                const pending = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .and(a => !a.synced)
                    .count();
                this.hasPendingSync = pending > 0;
            } catch (e) {
                console.warn('[Selesai] checkPendingSync error:', e.message);
            }
        },

        async trySyncPending() {
            if (this.isSyncing || !this.isOnline) return;
            this.isSyncing = true;

            try {
                const db = this._getDb();

                // Find all sessions with pending answers
                const pending = await db.exam_answers.filter(a => !a.synced).toArray();
                if (pending.length === 0) {
                    this.hasPendingSync = false;
                    this.isSyncing = false;
                    return;
                }

                // Group by sesiPesertaId
                const bySesi = {};
                pending.forEach(a => {
                    if (!bySesi[a.sesiPesertaId]) bySesi[a.sesiPesertaId] = [];
                    bySesi[a.sesiPesertaId].push(a);
                });

                // Get sesi token from exam_state
                for (const [sesiPesertaId, answers] of Object.entries(bySesi)) {
                    const state = await db.exam_state.get(sesiPesertaId);

                    // Format answers for API
                    const formattedAnswers = answers.map(item => ({
                        soal_id:         item.soalId,
                        jawaban:         this._formatJawaban(item.jawaban),
                        idempotency_key: item.idempotencyKey,
                        client_timestamp: item.updatedAt,
                    }));

                    // Try to get token - check window config or use stored token
                    const sesiToken = window.SELESAI_CONFIG?.sesiToken || state?.sesiToken;
                    if (!sesiToken) {
                        console.warn('[Selesai] No sesi token for', sesiPesertaId);
                        continue;
                    }

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 20000);

                    const res = await fetch('/api/ujian/sync-jawaban', {
                        method: 'POST',
                        signal: controller.signal,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            sesi_token: sesiToken,
                            answers: formattedAnswers,
                            tandai_list: state?.tandaiList ?? [],
                        }),
                    });

                    clearTimeout(timeoutId);

                    if (res.ok) {
                        // Mark synced
                        await Promise.all(answers.map(a => db.exam_answers.update(a.id, { synced: true })));
                        this.syncRetries = 0;

                        // If pendingSubmit, also submit
                        if (state?.pendingSubmit) {
                            const submitCtrl = new AbortController();
                            const submitTimeout = setTimeout(() => submitCtrl.abort(), 20000);
                            try {
                                await fetch('/api/ujian/submit/' + sesiToken, {
                                    method: 'POST',
                                    signal: submitCtrl.signal,
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                    body: JSON.stringify({ sesi_token: sesiToken }),
                                });
                                clearTimeout(submitTimeout);
                            } catch (submitErr) {
                                clearTimeout(submitTimeout);
                                console.warn('[Selesai] Submit fetch failed:', submitErr.message);
                            }
                            await db.exam_state.update(sesiPesertaId, { pendingSubmit: false });
                        }

                        // Cleanup synced answers
                        await db.exam_answers.where('sesiPesertaId').equals(sesiPesertaId)
                            .filter(a => a.synced).delete();
                    } else {
                        throw new Error(`Server returned ${res.status}`);
                    }
                }

                await this.checkPendingSync();

                // After successful sync, reload page to get fresh server counts
                if (!this.hasPendingSync) {
                    window.location.reload();
                    return;
                }
            } catch (e) {
                console.warn('[Selesai] Sync failed:', e.message);
                this.syncRetries++;
                // Retry with exponential backoff
                if (this.syncRetries < this.maxRetries) {
                    const delay = Math.min(2000 * Math.pow(2, this.syncRetries - 1), 30000);
                    this._retryTimer = setTimeout(() => this.trySyncPending(), delay);
                }
            } finally {
                this.isSyncing = false;
            }
        },

        _formatJawaban(jawaban) {
            if (!jawaban) return null;
            if (jawaban.pg?.length > 0) return jawaban.pg;
            if (jawaban.benarSalah && Object.keys(jawaban.benarSalah).length > 0) return jawaban.benarSalah;
            if (jawaban.pasangan && Object.keys(jawaban.pasangan).length > 0) return Object.entries(jawaban.pasangan).map(([k,v]) => [parseInt(k), v]);
            if (jawaban.teks !== undefined && jawaban.teks !== '') return jawaban.teks;
            return null;
        },
    };
}
</script>
@endsection
