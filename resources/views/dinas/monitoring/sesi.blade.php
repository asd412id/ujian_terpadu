@extends('layouts.admin')

@section('title', 'Detail Monitoring — ' . $sesi->nama_sesi)
@section('polling', true)

@section('breadcrumb')
    <a href="{{ route('dinas.monitoring') }}" class="text-gray-500 hover:text-blue-600">Monitoring</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold truncate">{{ $sesi->nama_sesi }}</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="sesiMonitoringApp()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl font-bold text-gray-900">{{ $sesi->nama_sesi }}</h1>
                @if($sesi->status === 'berlangsung')
                    <span class="inline-flex items-center gap-1 text-xs font-bold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                        LIVE
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $sesi->paket?->nama ?? '—' }} · {{ $sesi->paket?->sekolah?->nama ?? 'Semua Sekolah' }}
            </p>
        </div>

        {{-- Kontrol Sesi --}}
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-semibold px-3 py-1 rounded-full
                {{ $sesi->status === 'berlangsung' ? 'bg-green-100 text-green-800' : ($sesi->status === 'selesai' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                {{ ucfirst($sesi->status) }}
            </span>
            <span class="text-xs text-gray-400">Terakhir: <span x-text="lastUpdate">{{ now()->format('H:i:s') }}</span></span>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900" x-text="stats.total ?? {{ $stats['total'] }}">{{ $stats['total'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total Peserta</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600" x-text="stats.online ?? {{ $stats['online'] }}">{{ $stats['online'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sedang Mengerjakan</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600" x-text="stats.submit ?? {{ $stats['submit'] }}">{{ $stats['submit'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sudah Submit</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-400" x-text="stats.belum_mulai ?? {{ $stats['belum_mulai'] }}">{{ $stats['belum_mulai'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Belum Mulai</p>
        </div>
    </div>

    {{-- Progress Bar --}}
    <div class="card">
        <div class="flex items-center justify-between mb-2 text-sm">
            <span class="font-medium text-gray-700">Progress Submit</span>
            <span class="text-gray-500">
                <span x-text="stats.submit ?? {{ $stats['submit'] }}">{{ $stats['submit'] }}</span> /
                <span x-text="stats.total ?? {{ $stats['total'] }}">{{ $stats['total'] }}</span> peserta
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div class="h-3 bg-blue-600 rounded-full transition-all duration-500"
                 :style="`width: ${stats.total > 0 ? Math.round((stats.submit / stats.total) * 100) : 0}%`"
                 style="width: {{ $stats['total'] > 0 ? round(($stats['submit'] / $stats['total']) * 100) : 0 }}%">
            </div>
        </div>
    </div>

    {{-- Tabel Peserta --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-gray-900">Daftar Peserta</h2>
            <form method="GET" action="{{ route('dinas.monitoring.sesi', $sesi->id) }}" class="flex items-center gap-2 flex-wrap">
                <input type="text" name="search" placeholder="Cari nama / NIS..."
                       value="{{ $filters['search'] ?? '' }}"
                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-full sm:w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <div class="w-full sm:w-56" x-data x-on:change="$el.closest('form').submit()">
                    <x-searchable-select
                        name="sekolah_id"
                        :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => $s->nama])"
                        :value="$filters['sekolah_id'] ?? ''"
                        placeholder="Semua Sekolah" />
                </div>
                <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="online" {{ ($filters['status'] ?? '') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="submit" {{ ($filters['status'] ?? '') === 'submit' ? 'selected' : '' }}>Submit</option>
                    <option value="belum" {{ ($filters['status'] ?? '') === 'belum' ? 'selected' : '' }}>Belum Mulai</option>
                </select>
                <button type="submit" class="btn-primary">Cari</button>
                @if(!empty($filters['search']) || !empty($filters['status']) || !empty($filters['sekolah_id']))
                <a href="{{ route('dinas.monitoring.sesi', $sesi->id) }}" class="text-xs text-gray-500 hover:text-red-500">Reset</a>
                @endif
            </form>
        </div>
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-center">Jawab</th>
                        <th class="px-5 py-3 text-center">Ragu</th>
                        <th class="px-5 py-3 text-center">Nilai</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Login</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pesertaList as $sp)
                    @if(!$sp->peserta) @continue @endif
                    <tr class="hover:bg-gray-50" x-data="{ get live() { return pesertaLive['{{ $sp->id }}'] ?? null } }">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sp->peserta->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sp->peserta->nis ?? $sp->peserta->nisn }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-xs text-gray-600">{{ $sp->peserta->sekolah?->nama ?? '—' }}</td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">{{ $sp->peserta->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            <template x-if="live && (live.status === 'submit' || live.status === 'dinilai')">
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                            </template>
                            <template x-if="live && (live.status === 'mengerjakan' || live.status === 'login')">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Online
                                </span>
                            </template>
                            <template x-if="!live || (!['submit','dinilai','mengerjakan','login'].includes(live.status))">
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Belum</span>
                            </template>
                        </td>
                        <td class="px-5 py-3 text-center font-medium text-gray-900">
                            <span x-text="live ? (live.soal_terjawab + '/{{ $sesi->paket?->jumlah_soal ?? '?' }}') : '{{ ($sp->soal_terjawab ?? 0) . '/' . ($sesi->paket?->jumlah_soal ?? '?') }}'"></span>
                        </td>
                        <td class="px-5 py-3 text-center text-amber-600 font-medium">
                            <span x-text="live ? live.soal_ditandai : '{{ $sp->soal_ditandai ?? 0 }}'"></span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <template x-if="live && ['submit','dinilai'].includes(live.status) && live.nilai_akhir !== null">
                                <span :class="live.nilai_akhir >= 70 ? 'font-bold text-green-600' : 'font-bold text-red-600'"
                                      x-text="parseFloat(live.nilai_akhir).toFixed(1)"></span>
                            </template>
                            <template x-if="!live || !['submit','dinilai'].includes(live.status) || live.nilai_akhir === null">
                                <span class="text-gray-400">—</span>
                            </template>
                        </td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell">
                            <template x-if="live && live.sisa_waktu > 0 && ['mengerjakan','login'].includes(live.status)">
                                <span :class="live.sisa_waktu < 600 ? 'text-red-600 font-bold' : 'text-gray-600'"
                                      x-text="Math.floor(live.sisa_waktu/60) + ':' + String(live.sisa_waktu%60).padStart(2,'0')"></span>
                            </template>
                            <template x-if="!live || live.sisa_waktu <= 0 || !['mengerjakan','login'].includes(live.status)">
                                <span class="text-gray-400">—</span>
                            </template>
                        </td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell text-xs text-gray-500">
                            {{ $sp->mulai_at ? \Carbon\Carbon::parse($sp->mulai_at)->format('H:i:s') : '—' }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            <template x-if="live && ['submit','dinilai','mengerjakan','login'].includes(live.status)">
                                <button type="button"
                                    @click="$dispatch('open-reset-modal', { id: '{{ $sp->id }}', nama: '{{ addslashes($sp->peserta->nama) }}' })"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded transition-colors"
                                    title="Reset ujian peserta">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Reset
                                </button>
                            </template>
                            <template x-if="!live || !['submit','dinilai','mengerjakan','login'].includes(live.status)">
                                <span class="text-gray-300 text-xs">—</span>
                            </template>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-5 py-10 text-center text-gray-400">
                            @if(!empty($filters['search']) || !empty($filters['status']))
                                Tidak ada peserta yang cocok dengan filter.
                            @else
                                Belum ada peserta yang login.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($pesertaList as $sp)
            @if(!$sp->peserta) @continue @endif
            <div class="px-4 py-3" x-data="{ get live() { return pesertaLive['{{ $sp->id }}'] ?? null } }">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 text-sm">{{ $sp->peserta->nama }}</p>
                        <p class="text-xs text-gray-500">{{ $sp->peserta->nis ?? $sp->peserta->nisn }} · {{ $sp->peserta->sekolah?->nama ?? '—' }}</p>
                    </div>
                    <div class="shrink-0">
                        <template x-if="live && (live.status === 'submit' || live.status === 'dinilai')">
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                        </template>
                        <template x-if="live && (live.status === 'mengerjakan' || live.status === 'login')">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                Online
                            </span>
                        </template>
                        <template x-if="!live || (!['submit','dinilai','mengerjakan','login'].includes(live.status))">
                            <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Belum</span>
                        </template>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs mb-2">
                    <div class="bg-gray-50 rounded-lg p-1.5">
                        <p class="font-bold text-gray-900" x-text="live ? (live.soal_terjawab + '/{{ $sesi->paket?->jumlah_soal ?? '?' }}') : '{{ ($sp->soal_terjawab ?? 0) . '/' . ($sesi->paket?->jumlah_soal ?? '?') }}'"></p>
                        <p class="text-gray-500">Jawab</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-1.5">
                        <p class="font-bold text-amber-700" x-text="live ? live.soal_ditandai : '{{ $sp->soal_ditandai ?? 0 }}'"></p>
                        <p class="text-gray-500">Ragu</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-1.5">
                        <template x-if="live && ['submit','dinilai'].includes(live.status) && live.nilai_akhir !== null">
                            <div>
                                <p class="font-bold" :class="live.nilai_akhir >= 70 ? 'text-green-600' : 'text-red-600'" x-text="parseFloat(live.nilai_akhir).toFixed(1)"></p>
                                <p class="text-gray-500">Nilai</p>
                            </div>
                        </template>
                        <template x-if="!live || !['submit','dinilai'].includes(live.status) || live.nilai_akhir === null">
                            <div>
                                <p class="font-bold text-gray-400">—</p>
                                <p class="text-gray-500">Nilai</p>
                            </div>
                        </template>
                    </div>
                </div>
                <template x-if="live && ['submit','dinilai','mengerjakan','login'].includes(live.status)">
                    <button type="button"
                        @click="$dispatch('open-reset-modal', { id: '{{ $sp->id }}', nama: '{{ addslashes($sp->peserta->nama) }}' })"
                        class="w-full text-center text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1.5 rounded transition-colors border border-red-200">
                        Reset Ujian
                    </button>
                </template>
            </div>
            @empty
            <div class="py-10 text-center text-gray-400 text-sm">
                @if(!empty($filters['search']) || !empty($filters['status']))
                    Tidak ada peserta yang cocok dengan filter.
                @else
                    Belum ada peserta yang login.
                @endif
            </div>
            @endforelse
        </div>
        @if($pesertaList->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $pesertaList->withQueryString()->links() }}
        </div>
        @endif
    </div>

    {{-- Reset Confirmation Modal --}}
    <div x-show="showResetModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         @open-reset-modal.window="resetTarget = $event.detail; showResetModal = true"
         @keydown.escape.window="showResetModal = false">
        <div class="fixed inset-0 bg-black/50" @click="showResetModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6"
             x-show="showResetModal" x-transition>
            <div class="text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Reset Ujian Peserta</h3>
                <p class="text-sm text-gray-600 mb-1">Apakah Anda yakin ingin mereset ujian:</p>
                <p class="text-sm font-bold text-gray-900 mb-4" x-text="resetTarget?.nama ?? ''"></p>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 text-left">
                    <p class="text-xs text-amber-800 font-medium mb-1">Tindakan ini akan:</p>
                    <ul class="text-xs text-amber-700 space-y-0.5 list-disc list-inside">
                        <li>Menghapus semua jawaban peserta</li>
                        <li>Menghapus log aktivitas ujian</li>
                        <li>Mereset status ke "Terdaftar"</li>
                        <li>Peserta dapat login dan mengerjakan ulang</li>
                    </ul>
                </div>
                <div class="flex gap-3">
                    <button @click="showResetModal = false"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                        Batal
                    </button>
                    <form :action="'{{ url('dinas/monitoring/sesi/' . $sesi->id . '/reset-peserta') }}/' + (resetTarget?.id ?? '')"
                          method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors"
                                :disabled="resetLoading"
                                @click="resetLoading = true">
                            <span x-show="!resetLoading">Ya, Reset Ujian</span>
                            <span x-show="resetLoading" class="inline-flex items-center gap-1">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Mereset...
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function sesiMonitoringApp() {
    return {
        lastUpdate: '{{ now()->format("H:i:s") }}',
        stats: {
            total: {{ $stats['total'] }},
            online: {{ $stats['online'] }},
            submit: {{ $stats['submit'] }},
            belum_mulai: {{ $stats['belum_mulai'] }},
        },
        pesertaLive: @json($pesertaLive ?? []),
        showResetModal: false,
        resetTarget: null,
        resetLoading: false,
        _loading: false,

        init() {
            // Initial load
            this.loadStats();
            this._pollInterval = setInterval(() => this.loadStats(), 10000);
        },

        destroy() {
            clearInterval(this._pollInterval);
        },

        async loadStats() {
            if (this._loading) return;
            this._loading = true;
            try {
                const res = await fetch('{{ route('dinas.monitoring.sesi.api', $sesi->id) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.stats = data.stats ?? this.stats;
                    if (data.peserta_live) {
                        this.pesertaLive = data.peserta_live;
                    }
                    this.lastUpdate = new Date().toLocaleTimeString('id-ID');
                }
            } catch (e) { /* offline */ }
            this._loading = false;
        }
    };
}
</script>
@endsection
