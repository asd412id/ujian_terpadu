@extends('layouts.admin')

@section('title', 'Monitoring Peserta Ujian')
@section('polling', true)

@section('breadcrumb')
    <span class="text-gray-500">Dashboard</span>
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Monitoring Peserta Ujian</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="monitoringSekolahApp()" x-init="init()">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Monitoring Peserta Ujian</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $sekolah->nama }} · Update otomatis setiap 10 detik.
                Terakhir: <span x-text="lastUpdate">—</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="flex items-center gap-1.5 text-xs text-green-700 bg-green-50 border border-green-200 px-3 py-1.5 rounded-full font-medium">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                LIVE
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900" x-text="summary.total_sesi ?? '{{ $summary['total_sesi'] }}'">{{ $summary['total_sesi'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total Sesi Aktif</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600" x-text="summary.peserta_online ?? '{{ $summary['peserta_online'] }}'">{{ $summary['peserta_online'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Peserta Online</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-amber-600" x-text="summary.peserta_ragu ?? '{{ $summary['peserta_ragu'] }}'">{{ $summary['peserta_ragu'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Ditandai Ragu</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600" x-text="summary.sudah_submit ?? '{{ $summary['sudah_submit'] }}'">{{ $summary['sudah_submit'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sudah Submit</p>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-gray-900">Sesi Ujian Aktif</h2>
                <p class="text-xs text-gray-500 mt-0.5">Daftar sesi akan diperbarui otomatis dari API.</p>
            </div>
            <input type="text" placeholder="Cari sesi..."
                   x-model="search"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Sesi / Paket</th>
                        <th class="px-5 py-3 text-center">Peserta</th>
                        <th class="px-5 py-3 text-center">Online</th>
                        <th class="px-5 py-3 text-center">Submit</th>
                        <th class="px-5 py-3 text-center">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="sesi in filteredSesiList" :key="sesi.id">
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900" x-text="sesi.nama_sesi"></p>
                                <p class="text-xs text-gray-500" x-text="sesi.paket_nama || '—'"></p>
                            </td>
                            <td class="px-5 py-3 text-center font-medium text-gray-900" x-text="sesi.total_peserta ?? 0"></td>
                            <td class="px-5 py-3 text-center">
                                <span class="flex items-center justify-center gap-1 text-green-600 font-medium">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse" :class="(sesi.peserta_online ?? 0) > 0 ? '' : 'opacity-0'"></span>
                                    <span x-text="sesi.peserta_online ?? 0"></span>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center font-medium text-blue-600" x-text="sesi.sudah_submit ?? 0"></td>
                            <td class="px-5 py-3 text-center">
                                <template x-if="sesi.sisa_waktu_menit !== null && sesi.sisa_waktu_menit !== undefined">
                                    <span :class="sesi.sisa_waktu_menit <= 10 ? 'text-red-600 font-bold' : 'text-gray-700'"
                                          x-text="formatDuration(sesi.sisa_waktu_menit)"></span>
                                </template>
                                <template x-if="sesi.sisa_waktu_menit === null || sesi.sisa_waktu_menit === undefined">
                                    <span class="text-gray-400">—</span>
                                </template>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <template x-if="sesi.status === 'berlangsung'">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                        Berlangsung
                                    </span>
                                </template>
                                <template x-if="sesi.status === 'persiapan'">
                                    <span class="text-xs font-semibold bg-blue-100 text-blue-600 px-2 py-1 rounded-full">Persiapan</span>
                                </template>
                                <template x-if="!['berlangsung','persiapan'].includes(sesi.status)">
                                    <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Selesai</span>
                                </template>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a :href="`{{ url('sekolah/monitoring/sesi') }}/${sesi.id}`"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Detail →</a>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && filteredSesiList.length === 0">
                        <td colspan="7" class="px-5 py-0">
                            <div class="py-14 flex flex-col items-center justify-center text-center px-4">
                                <div class="w-16 h-16 mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="text-base font-medium text-gray-500" x-show="!search">Belum ada sesi ujian aktif</p>
                                <p class="text-sm text-gray-400 mt-1" x-show="!search">Sesi ujian yang sedang berlangsung akan muncul di sini secara otomatis.</p>
                                <p class="text-base font-medium text-gray-500" x-show="search" x-cloak>Sesi tidak ditemukan</p>
                                <p class="text-sm text-gray-400 mt-1" x-show="search" x-cloak>Coba ubah kata kunci pencarian Anda.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100">
            <template x-for="sesi in filteredSesiList" :key="sesi.id">
                <div class="px-4 py-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <p class="font-medium text-gray-900 text-sm" x-text="sesi.nama_sesi"></p>
                            <p class="text-xs text-gray-500" x-text="sesi.paket_nama || '—'"></p>
                        </div>
                        <template x-if="sesi.status === 'berlangsung'">
                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full flex-shrink-0">Live</span>
                        </template>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="bg-gray-50 rounded-lg p-2">
                            <p class="font-bold text-gray-900" x-text="sesi.total_peserta ?? 0"></p>
                            <p class="text-gray-500">Total</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-2">
                            <p class="font-bold text-green-700" x-text="sesi.peserta_online ?? 0"></p>
                            <p class="text-gray-500">Online</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-2">
                            <p class="font-bold text-blue-700" x-text="sesi.sudah_submit ?? 0"></p>
                            <p class="text-gray-500">Submit</p>
                        </div>
                    </div>
                    <a :href="`{{ url('sekolah/monitoring/sesi') }}/${sesi.id}`"
                       class="mt-2 block text-center text-blue-600 text-xs font-medium">Lihat Detail →</a>
                </div>
            </template>
            <div x-show="!loading && filteredSesiList.length === 0">
                <div class="py-14 flex flex-col items-center justify-center text-center px-4">
                    <div class="w-16 h-16 mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-base font-medium text-gray-500" x-show="!search">Belum ada sesi ujian aktif</p>
                    <p class="text-sm text-gray-400 mt-1" x-show="!search">Sesi ujian yang sedang berlangsung akan muncul di sini.</p>
                    <p class="text-base font-medium text-gray-500" x-show="search" x-cloak>Sesi tidak ditemukan</p>
                    <p class="text-sm text-gray-400 mt-1" x-show="search" x-cloak>Coba ubah kata kunci pencarian Anda.</p>
                </div>
            </div>
        </div>
    </div>

</div>

@php
    $initialSesiList = $sesiList->map(function ($sesi) {
        return [
            'id' => $sesi->id,
            'nama_sesi' => $sesi->nama_sesi,
            'paket_nama' => $sesi->paket?->nama,
            'total_peserta' => $sesi->total_peserta ?? 0,
            'peserta_online' => $sesi->peserta_online ?? 0,
            'sudah_submit' => $sesi->sudah_submit ?? 0,
            'sisa_waktu_menit' => $sesi->sisa_waktu_menit ?? null,
            'status' => $sesi->status,
        ];
    })->values()->all();
@endphp

<script>
function monitoringSekolahApp() {
    return {
        search: '',
        lastUpdate: '',
        summary: {},
        sesiList: @js($initialSesiList),
        _loading: false,
        timer: null,

        init() {
            this.updateTime();
            this.timer = setInterval(() => this.loadData(), 10000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        updateTime() {
            this.lastUpdate = new Date().toLocaleTimeString('id-ID');
        },

        formatDuration(seconds) {
            const totalSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
            const mm = Math.floor(totalSeconds / 60);
            const ss = String(totalSeconds % 60).padStart(2, '0');
            return `${mm}:${ss}`;
        },

        async loadData() {
            if (this._loading) return;
            this._loading = true;
            try {
                const res = await fetch('{{ route('sekolah.monitoring.api') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.summary = data.summary ?? {};
                    this.sesiList = (data.sesiList ?? []).map((sesi) => ({
                        id: sesi.id,
                        nama_sesi: sesi.nama_sesi,
                        paket_nama: sesi.paket?.nama ?? null,
                        total_peserta: sesi.total_peserta ?? 0,
                        peserta_online: sesi.peserta_online ?? 0,
                        sudah_submit: sesi.sudah_submit ?? 0,
                        sisa_waktu_menit: sesi.sisa_waktu_menit ?? null,
                        status: sesi.status,
                    }));
                    this.updateTime();
                }
            } catch (e) { /* silent */ }
            this._loading = false;
        },

        get filteredSesiList() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.sesiList;
            return this.sesiList.filter((sesi) => {
                const text = `${sesi.nama_sesi || ''} ${sesi.paket_nama || ''}`.toLowerCase();
                return text.includes(q);
            });
        },
    };
}
</script>
@endsection
