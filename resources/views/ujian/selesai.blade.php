@extends('layouts.base')

@section('title', 'Ujian Selesai')

@section('content')
<div x-data="selesaiApp()" x-init="init()">

{{-- ====== SYNC OVERLAY (tampil sampai semua jawaban terverifikasi di server) ====== --}}
<div x-show="!syncVerified" x-transition.opacity class="fixed inset-0 z-[100] bg-white flex flex-col items-center justify-center p-6">
    <div class="w-full max-w-sm text-center space-y-6">

        {{-- Spinner animasi --}}
        <div class="relative mx-auto w-20 h-20">
            <svg class="w-20 h-20 animate-spin" viewBox="0 0 50 50" fill="none">
                <circle cx="25" cy="25" r="20" stroke="#e2e8f0" stroke-width="5"></circle>
                <circle cx="25" cy="25" r="20" stroke="#3b82f6" stroke-width="5"
                        stroke-dasharray="80 126" stroke-linecap="round"
                        :class="syncFailed ? 'stroke-red-500' : 'stroke-blue-500'"></circle>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-sm font-bold"
                      :class="syncFailed ? 'text-red-600' : 'text-blue-600'"
                      x-text="syncProgress + '%'"></span>
            </div>
        </div>

        {{-- Status text --}}
        <div>
            <h2 class="text-lg font-bold text-gray-900" x-text="syncStatusTitle"></h2>
            <p class="text-sm text-gray-500 mt-1" x-text="syncStatusDetail"></p>
        </div>

        {{-- Progress bar --}}
        <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="h-2.5 rounded-full transition-all duration-500"
                 :class="syncFailed ? 'bg-red-500' : 'bg-blue-500'"
                 :style="'width: ' + syncProgress + '%'"></div>
        </div>

        {{-- Jumlah terkirim --}}
        <p class="text-xs text-gray-400" x-show="syncTotalLocal > 0">
            <span x-text="syncSentCount"></span> / <span x-text="syncTotalLocal"></span> jawaban terkirim
        </p>

        {{-- Offline warning --}}
        <div x-show="!isOnline" x-transition
             class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
            <svg class="w-5 h-5 mx-auto mb-2 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Tidak ada koneksi internet. Jawaban akan otomatis terkirim saat koneksi tersambung.
            <br><strong>Jangan tutup browser ini.</strong>
        </div>

        {{-- Retry button --}}
        <button x-show="syncFailed && isOnline" x-transition
                @click="retrySyncAll()"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-all">
            Coba Kirim Ulang
        </button>

        {{-- Sync error detail --}}
        <p x-show="syncFailed" x-transition class="text-xs text-red-500" x-text="syncErrorMsg"></p>
    </div>
</div>

{{-- ====== MAIN CONTENT (tersembunyi sampai sync terverifikasi) ====== --}}
<div x-show="syncVerified" x-transition.opacity>

{{-- Top Navigation Bar --}}
<header class="w-full bg-white border-b border-gray-200 px-4 sm:px-6 py-3.5">
    <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="Logo" class="w-9 h-9 rounded-xl">
            <span class="text-sm font-bold text-gray-900">{{ strtoupper(config('app.name')) }}</span>
        </div>
        <form action="{{ route('ujian.logout') }}" method="POST" @submit.prevent="logout($event)">
            @csrf
            <button type="submit"
                    class="flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-red-600 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>
    </div>
</header>

<main class="min-h-screen bg-slate-100 flex items-center justify-center p-4 sm:p-6">
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

                        {{-- Polling timeout fallback --}}
                        <div x-show="!nilaiLoading && nilaiAkhir === null" x-transition
                             class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-center">
                            <svg class="w-6 h-6 text-amber-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-amber-700 font-medium">Nilai sedang diproses</p>
                            <p class="text-xs text-amber-600 mt-1">Refresh halaman ini atau cek kembali nanti untuk melihat hasil.</p>
                            <button @click="nilaiLoading = true; _isPolling = false; pollNilai()"
                                    class="mt-3 px-4 py-1.5 bg-amber-100 hover:bg-amber-200 text-amber-800 text-xs font-semibold rounded-lg transition-colors">
                                Cek Ulang
                            </button>
                        </div>

                        {{-- Nilai ready --}}
                        <div x-show="!nilaiLoading && nilaiAkhir !== null" x-transition class="space-y-3">
                            <div class="rounded-xl p-5 text-center"
                                 :class="(() => { const n = parseFloat(nilaiAkhir ?? 0); return n >= 86 ? 'bg-green-50 border border-green-200' : n >= 71 ? 'bg-blue-50 border border-blue-200' : n >= 56 ? 'bg-amber-50 border border-amber-200' : n >= 41 ? 'bg-orange-50 border border-orange-200' : 'bg-red-50 border border-red-200'; })()">
                                <p class="text-xs font-medium mb-1"
                                   :class="(() => { const n = parseFloat(nilaiAkhir ?? 0); return n >= 86 ? 'text-green-600' : n >= 71 ? 'text-blue-600' : n >= 56 ? 'text-amber-600' : n >= 41 ? 'text-orange-600' : 'text-red-600'; })()"
                                   x-text="(() => { const n = parseFloat(nilaiAkhir ?? 0); return n >= 86 ? 'SANGAT BAIK' : n >= 71 ? 'BAIK' : n >= 56 ? 'CUKUP' : n >= 41 ? 'KURANG' : 'SANGAT KURANG'; })()"></p>
                                <p class="text-4xl font-black"
                                   :class="(() => { const n = parseFloat(nilaiAkhir ?? 0); return n >= 86 ? 'text-green-700' : n >= 71 ? 'text-blue-700' : n >= 56 ? 'text-amber-700' : n >= 41 ? 'text-orange-700' : 'text-red-700'; })()"
                                   x-text="parseFloat(nilaiAkhir).toFixed(1)"></p>
                                <p class="text-xs text-gray-500 mt-1">Nilai Akhir</p>
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
                        <span class="font-medium text-gray-900">{{ ($sesiPeserta->submit_at ?? now())->format('H:i:s') }} WITA</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Status Sinkronisasi</span>
                        <span class="font-medium flex items-center gap-1.5 text-green-600">
                            <span class="w-2 h-2 rounded-full inline-block bg-green-500"></span>
                            Tersinkron
                        </span>
                    </div>
                </div>

                {{-- Sukses Sync --}}
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-700 text-center">
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

                {{-- Tombol Keluar --}}
                <form action="{{ route('ujian.logout') }}" method="POST" @submit.prevent="logout($event)">
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
            </div>
        </div>
    </div>
</main>

</div>{{-- end syncVerified --}}

</div>{{-- end x-data selesaiApp --}}

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
    alreadySubmitted: {{ in_array($sesiPeserta->status, ['submit', 'dinilai']) ? 'true' : 'false' }},
};

function selesaiApp() {
    return {
        isOnline: navigator.onLine,
        isSyncing: false,
        syncVerified: false,
        syncProgress: 0,
        syncTotalLocal: 0,
        syncSentCount: 0,
        syncFailed: false,
        syncErrorMsg: '',
        syncStatusTitle: 'Mengirim jawaban ke server...',
        syncStatusDetail: 'Mohon tunggu, jangan tutup halaman ini.',
        syncRetries: 0,
        maxRetries: 15,
        _db: null,
        _retryTimer: null,
        _nilaiPollTimer: null,
        _isPolling: false,
        _onlineSyncTimer: null,
        terjawab: window.SELESAI_CONFIG.serverTerjawab,
        ragu: window.SELESAI_CONFIG.serverRagu,
        kosong: window.SELESAI_CONFIG.serverKosong,
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
            // Block back navigation
            history.pushState({ selesaiPage: true }, '', window.location.href);
            window.addEventListener('popstate', () => {
                history.pushState({ selesaiPage: true }, '', window.location.href);
            });
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    history.pushState({ selesaiPage: true }, '', window.location.href);
                }
            });

            window.addEventListener('online',  () => {
                this.isOnline = true;
                this.syncRetries = 0;
                if (!this.syncVerified) {
                    if (this._onlineSyncTimer) clearTimeout(this._onlineSyncTimer);
                    this._onlineSyncTimer = setTimeout(() => this.runSyncFlow(), 1200);
                }
            });
            window.addEventListener('offline', () => this.isOnline = false);

            // Listen for SW trigger
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (e) => {
                    if (e.data?.type === 'TRIGGER_SYNC' && !this.syncVerified) this.runSyncFlow();
                });
            }

            // Start sync verification flow
            await this.runSyncFlow();
        },

        async runSyncFlow() {
            const db = this._getDb();
            const cfg = window.SELESAI_CONFIG;

            // Count local answers
            let localAnswers = [];
            try {
                localAnswers = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .toArray();
            } catch (e) { /* IDB unavailable */ }

            const state = await db.exam_state.get(cfg.sesiPesertaId).catch(() => null);
            const pending = localAnswers.filter(a => !a.synced);

            this.syncTotalLocal = localAnswers.length;
            this.syncSentCount = localAnswers.length - pending.length;

            const hasPendingSubmit = Boolean(state?.pendingSubmit)
                || (typeof state?.pendingSubmitCount === 'number' && state.pendingSubmitCount > 0);

            // If no local data at all AND no pending submit → already clean, skip to result
            if (localAnswers.length === 0 && !hasPendingSubmit) {
                this.syncProgress = 100;
                if (cfg.alreadySubmitted) {
                    this.syncVerified = true;
                    if (this.tampilkanHasil && this.nilaiAkhir === null) this.pollNilai();
                    return;
                }
                await this.verifyServerCount();
                return;
            }

            // If all synced and no pending submit → verify with server
            if (pending.length === 0 && !hasPendingSubmit) {
                this.syncProgress = 80;
                this.syncStatusTitle = 'Memverifikasi dengan server...';
                await this.verifyServerCount();
                return;
            }

            // Need to sync — start the sync process
            if (!this.isOnline) {
                this.syncStatusTitle = 'Menunggu koneksi internet...';
                this.syncStatusDetail = 'Jawaban akan otomatis terkirim saat koneksi tersambung. Jangan tutup halaman.';
                this.syncProgress = Math.round((this.syncSentCount / Math.max(1, this.syncTotalLocal)) * 60);
                return; // Will be retried on 'online' event
            }

            await this.trySyncPending();
        },

        async trySyncPending() {
            if (this.isSyncing || !this.isOnline) return;
            this.isSyncing = true;
            this.syncFailed = false;
            this.syncStatusTitle = 'Mengirim jawaban ke server...';
            this.syncStatusDetail = 'Mohon tunggu, jangan tutup halaman ini.';

            try {
                const db = this._getDb();
                const cfg = window.SELESAI_CONFIG;

                const pending = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .and(a => !a.synced)
                    .toArray();
                const state = await db.exam_state.get(cfg.sesiPesertaId);

                if (pending.length === 0 && !state?.pendingSubmit) {
                    this.syncProgress = 80;
                    this.syncStatusTitle = 'Memverifikasi dengan server...';
                    this.isSyncing = false;
                    await this.verifyServerCount();
                    return;
                }

                const formattedAnswers = pending.map(item => ({
                    soal_id:         item.soalId,
                    jawaban:         this._formatJawaban(item.jawaban),
                    idempotency_key: item.idempotencyKey,
                    client_timestamp: item.updatedAt,
                }));
                const pendingSubmitSnapshot = Array.isArray(state?.pendingSubmitPayload)
                    ? state.pendingSubmitPayload.filter(item => item?.soal_id && item?.jawaban !== null)
                    : [];
                const answersBySoalId = new Map();
                formattedAnswers.forEach(item => answersBySoalId.set(String(item.soal_id), item));
                pendingSubmitSnapshot.forEach(item => {
                    const key = String(item.soal_id);
                    if (!answersBySoalId.has(key)) {
                        answersBySoalId.set(key, item);
                    }
                });
                const answersToSync = Array.from(answersBySoalId.values());

                const sesiToken = cfg.sesiToken || state?.sesiToken;
                if (!sesiToken) {
                    console.warn('[Selesai] No sesi token');
                    this.isSyncing = false;
                    return;
                }

                this.syncProgress = 20;
                this.syncStatusDetail = `Mengirim ${answersToSync.length} jawaban...`;

                // Step 1: Sync answers
                if (answersToSync.length > 0) {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000);

                    const res = await fetch('/api/ujian/sync-jawaban', {
                        method: 'POST',
                        signal: controller.signal,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            sesi_token: sesiToken,
                            final_submit: true,
                            answers: answersToSync,
                            tandai_list: state?.tandaiList ?? [],
                        }),
                    });
                    clearTimeout(timeoutId);

                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.accepted !== false) {
                        this.syncStatusTitle = 'Jawaban sudah diterima server';
                        this.syncStatusDetail = 'Menyimpan status sinkronisasi lokal...';
                        await this.markRowsSyncedIfCurrent(pending);
                        this.syncSentCount = this.syncTotalLocal;
                        this.syncProgress = 50;
                    } else if (res.status === 422 || data.accepted === false) {
                        // Server rejected sync (already submitted / late sync window / time expired)
                        // Don't retry — proceed to verification, server already has the data
                        console.warn('[Selesai] Sync rejected (422):', data.errors || data.error || data.message);
                        this.syncProgress = 70;
                    } else {
                        throw new Error(data.error || data.message || `Server error (${res.status})`);
                    }
                }

                // Step 2: Submit if needed
                if (state?.pendingSubmit) {
                    this.syncProgress = 60;
                    this.syncStatusTitle = 'Mengirim submit ujian...';
                    this.syncStatusDetail = 'Menyelesaikan proses submit...';

                    const submitCtrl = new AbortController();
                    const submitTimeout = setTimeout(() => submitCtrl.abort(), 20000);

                    const submitRes = await fetch('/api/ujian/submit/' + sesiToken, {
                        method: 'POST',
                        signal: submitCtrl.signal,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ sesi_token: sesiToken }),
                    });
                    clearTimeout(submitTimeout);

                    const submitData = await submitRes.json().catch(() => ({}));
                    // If submit fails with already_submitted, that's fine — proceed
                    if (submitRes.ok || submitData.already_submitted || submitData.redirect) {
                        // Success or already submitted — clear pending flag
                    } else if (submitRes.status >= 500) {
                        throw new Error(submitData.error || submitData.message || `Submit gagal (${submitRes.status})`);
                    } else {
                        // 4xx — likely already submitted, proceed to verification
                        console.warn('[Selesai] Submit rejected:', submitRes.status, submitData.error || submitData.message);
                    }

                    await db.exam_state.update(cfg.sesiPesertaId, {
                        pendingSubmit: false,
                        pendingSubmitPayload: null,
                        pendingSubmitQueuedAt: null,
                    }).catch(() => {});
                }

                this.syncProgress = 80;
                this.syncRetries = 0;

                // Step 3: Verify with server
                await this.verifyServerCount();

            } catch (e) {
                console.warn('[Selesai] Sync failed:', e.message);
                this.syncFailed = true;
                this.syncErrorMsg = e.message;
                this.syncStatusTitle = 'Gagal mengirim jawaban';
                this.syncStatusDetail = 'Periksa koneksi internet dan coba lagi.';

                this.syncRetries++;
                if (this.syncRetries < this.maxRetries) {
                    const delay = Math.min(3000 * Math.pow(1.5, this.syncRetries - 1), 30000);
                    this.syncStatusDetail = `Mencoba ulang dalam ${Math.round(delay/1000)} detik... (percobaan ${this.syncRetries}/${this.maxRetries})`;
                    this._retryTimer = setTimeout(() => this.trySyncPending(), delay);
                } else {
                    this.syncStatusDetail = 'Gagal mengirim setelah banyak percobaan. Tekan tombol di bawah untuk mencoba lagi.';
                }
            } finally {
                this.isSyncing = false;
            }
        },

        async verifyServerCount() {
            const cfg = window.SELESAI_CONFIG;
            this.syncStatusTitle = 'Memverifikasi dengan server...';
            this.syncStatusDetail = 'Memastikan semua jawaban tersimpan...';
            this.syncProgress = 90;

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000);
                const statusRes = await fetch(cfg.statusUrl, {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' }
                });
                clearTimeout(timeoutId);

                if (statusRes.ok) {
                    const data = await statusRes.json();
                    this.terjawab = data.soal_terjawab ?? this.terjawab;
                    this.kosong = data.jumlah_kosong ?? Math.max(0, cfg.totalSoal - this.terjawab);
                    this.ragu = 0;

                    if (data.nilai_akhir !== null && data.nilai_akhir !== undefined) {
                        this.nilaiAkhir = parseFloat(data.nilai_akhir);
                        this.jumlahBenar = data.jumlah_benar ?? this.jumlahBenar;
                        this.jumlahSalah = data.jumlah_salah ?? this.jumlahSalah;
                        this.jumlahKosong = data.jumlah_kosong ?? this.jumlahKosong;
                        this.nilaiLoading = false;
                    }

                    // Verify: if local has more answers than server, re-sync
                    if (this.syncTotalLocal > 0 && data.soal_terjawab < this.syncTotalLocal) {
                        const db = this._getDb();
                        const pending = await db.exam_answers
                            .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                            .and(a => !a.synced)
                            .count();
                        const state = await db.exam_state.get(cfg.sesiPesertaId).catch(() => null);
                        const hasPendingSubmit = Boolean(state?.pendingSubmit)
                            || (typeof state?.pendingSubmitCount === 'number' && state.pendingSubmitCount > 0);

                        if ((pending > 0 || hasPendingSubmit) && this.syncRetries < this.maxRetries) {
                            // Still have unsynced rows or queued submit snapshot — retry
                            this.syncProgress = 50;
                            this.syncStatusTitle = 'Beberapa jawaban belum sampai...';
                            this.syncStatusDetail = 'Mengirim ulang jawaban yang belum tersimpan...';
                            this.syncRetries++;
                            setTimeout(() => this.trySyncPending(), 2000);
                            return;
                        }
                    }
                }
            } catch (e) {
                console.warn('[Selesai] Status verification failed:', e.message);
                // If can't verify but sync seemed OK, proceed anyway
            }

            // Clean up IDB — delete ALL data for this session
            try {
                const db = this._getDb();
                const cfg2 = window.SELESAI_CONFIG;
                await db.exam_answers.where('sesiPesertaId').equals(cfg2.sesiPesertaId).delete();
                await db.exam_state.delete(cfg2.sesiPesertaId);
            } catch (e) { /* IDB cleanup non-critical */ }

            // All done — show the result
            this.syncProgress = 100;
            this.syncStatusTitle = 'Selesai!';
            this.syncStatusDetail = '';

            // Small delay for visual feedback
            await new Promise(r => setTimeout(r, 500));
            this.syncVerified = true;

            // Start nilai polling if needed
            if (this.tampilkanHasil && this.nilaiAkhir === null) {
                this.pollNilai();
            }
        },

        async retrySyncAll() {
            // Reset all answers to unsynced and retry
            try {
                const db = this._getDb();
                const cfg = window.SELESAI_CONFIG;
                await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .modify({ synced: false });
            } catch (e) { /* ignore */ }

            this.syncRetries = 0;
            this.syncFailed = false;
            this.syncProgress = 0;
            this.syncSentCount = 0;
            await this.runSyncFlow();
        },

        async pollNilai() {
            if (!this.tampilkanHasil || this.nilaiAkhir !== null) return;

            // Prevent concurrent polling chains
            if (this._nilaiPollTimer) clearTimeout(this._nilaiPollTimer);
            if (this._isPolling) return;
            this._isPolling = true;

            const cfg = window.SELESAI_CONFIG;
            let attempts = 0;
            const maxAttempts = 30;

            const poll = async () => {
                if (this.nilaiAkhir !== null || attempts >= maxAttempts) {
                    this.nilaiLoading = false;
                    this._isPolling = false;
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
                            this._isPolling = false;
                            return;
                        }
                    }
                } catch (e) {
                    console.warn('[Selesai] pollNilai error:', e.message);
                }
                this._nilaiPollTimer = setTimeout(poll, 2000);
            };

            poll();
        },

        async markRowsSyncedIfCurrent(rows) {
            if (!rows.length) return;
            const db = this._getDb();
            try {
                // Use single transaction to avoid IDBTransaction finished errors
                await db.transaction('rw', db.exam_answers, async () => {
                    for (const row of rows) {
                        const current = await db.exam_answers.get(row.id);
                        if (!current || current.idempotencyKey !== row.idempotencyKey) continue;
                        await db.exam_answers.update(row.id, { synced: true });
                    }
                });
            } catch (e) {
                console.warn('[Selesai] Local IDB sync-state update failed after server accepted answers:', e.message);
                this.syncStatusTitle = 'Jawaban sudah diterima server';
                this.syncStatusDetail = 'Menyelesaikan sinkronisasi lokal...';
                this.syncFailed = false;
                this.syncErrorMsg = '';
            }
        },

        _formatJawaban(jawaban) {
            if (!jawaban) return null;
            if (jawaban.pg?.length > 0) return jawaban.pg;
            if (jawaban.benarSalah && Object.keys(jawaban.benarSalah).length > 0) return jawaban.benarSalah;
            if (jawaban.pasangan && Object.keys(jawaban.pasangan).length > 0) return Object.entries(jawaban.pasangan).map(([k,v]) => [k, v]);
            if (jawaban.teks !== undefined && jawaban.teks !== '') return jawaban.teks;
            if (jawaban.terjawab === false) return '';
            return null;
        },

        async logout(event) {
            const form = event?.target;
            if (!(form instanceof HTMLFormElement)) return;

            try {
                if (document.fullscreenElement && document.exitFullscreen) {
                    await document.exitFullscreen();
                } else if (document.webkitFullscreenElement && document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            } catch (e) {
                console.warn('[Selesai] exitFullscreen failed:', e.message);
            }

            form.submit();
        },
    };
}
</script>
@endsection
