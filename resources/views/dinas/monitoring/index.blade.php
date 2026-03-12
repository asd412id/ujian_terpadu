@extends('layouts.admin')

@section('title', 'Monitoring Ujian')
@section('polling', true)

@section('breadcrumb')
    <span class="text-gray-500">Dashboard</span>
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Monitoring Ujian</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="monitoringApp()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Monitoring Ujian</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Update otomatis setiap 5 detik.
                Terakhir: <span x-text="lastUpdate">—</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="flex items-center gap-1.5 text-xs text-green-700 bg-green-50 border border-green-200 px-3 py-1.5 rounded-full font-medium">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                LIVE
            </span>
            {{-- Filter Sekolah --}}
            <select @change="filterSekolah = $event.target.value; loadData()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Sekolah</option>
                @foreach($sekolahList as $sekolah)
                <option value="{{ $sekolah->id }}">{{ $sekolah->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Summary Cards --}}
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

    {{-- Tabel Sesi --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Sesi Ujian</h2>
            <input type="text" placeholder="Cari sesi..."
                   x-model="search"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Sesi / Paket</th>
                        <th class="px-5 py-3 text-left">Sekolah</th>
                        <th class="px-5 py-3 text-center">Peserta</th>
                        <th class="px-5 py-3 text-center">Online</th>
                        <th class="px-5 py-3 text-center">Submit</th>
                        <th class="px-5 py-3 text-center">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sesiList as $sesi)
                    <tr class="hover:bg-gray-50 transition-colors"
                        x-show="!search || '{{ strtolower($sesi->nama_sesi . ' ' . ($sesi->paket->nama ?? '')) }}'.includes(search.toLowerCase())">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sesi->nama_sesi }}</p>
                            <p class="text-xs text-gray-500">{{ $sesi->paket->nama ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-700">{{ $sesi->paket->sekolah->nama ?? '—' }}</td>
                        <td class="px-5 py-3 text-center font-medium text-gray-900">{{ $sesi->total_peserta ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="flex items-center justify-center gap-1 text-green-600 font-medium">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                {{ $sesi->peserta_online ?? 0 }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center font-medium text-blue-600">{{ $sesi->sudah_submit ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $sisaMenit = $sesi->sisa_waktu_menit ?? null;
                            @endphp
                            @if($sisaMenit !== null)
                                <span class="{{ $sisaMenit <= 10 ? 'text-red-600 font-bold' : 'text-gray-700' }}">
                                    {{ floor($sisaMenit / 60) }}:{{ str_pad($sisaMenit % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($sesi->status === 'berlangsung')
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Berlangsung
                                </span>
                            @elseif($sesi->status === 'menunggu')
                                <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">Menunggu</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Selesai</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('dinas.monitoring.sesi', $sesi->id) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">Detail →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-gray-400 text-sm">
                            Tidak ada sesi ujian aktif saat ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($sesiList as $sesi)
            <div class="px-4 py-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $sesi->nama_sesi }}</p>
                        <p class="text-xs text-gray-500">{{ $sesi->paket->nama ?? '—' }}</p>
                    </div>
                    @if($sesi->status === 'berlangsung')
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full flex-shrink-0">Live</span>
                    @endif
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="font-bold text-gray-900">{{ $sesi->total_peserta ?? 0 }}</p>
                        <p class="text-gray-500">Total</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-2">
                        <p class="font-bold text-green-700">{{ $sesi->peserta_online ?? 0 }}</p>
                        <p class="text-gray-500">Online</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-2">
                        <p class="font-bold text-blue-700">{{ $sesi->sudah_submit ?? 0 }}</p>
                        <p class="text-gray-500">Submit</p>
                    </div>
                </div>
                <a href="{{ route('dinas.monitoring.sesi', $sesi->id) }}"
                   class="mt-2 block text-center text-blue-600 text-xs font-medium">Lihat Detail →</a>
            </div>
            @empty
            <div class="py-10 text-center text-gray-400 text-sm">Tidak ada sesi aktif.</div>
            @endforelse
        </div>
    </div>

</div>

<script>
function monitoringApp() {
    return {
        search: '',
        filterSekolah: '',
        lastUpdate: '',
        summary: {},
        pollInterval: null,
        _loading: false,

        init() {
            this.updateTime();
            this.pollInterval = setInterval(() => this.loadData(), 5000);
        },

        updateTime() {
            this.lastUpdate = new Date().toLocaleTimeString('id-ID');
        },

        async loadData() {
            if (this._loading) return;
            this._loading = true;
            try {
                const params = this.filterSekolah ? `?sekolah_id=${this.filterSekolah}` : '';
                const res = await fetch(`{{ route('dinas.monitoring.api') }}${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.summary = data.summary ?? {};
                    this.updateTime();
                }
            } catch (e) { /* offline / error */ }
            this._loading = false;
        }
    };
}
</script>
@endsection
