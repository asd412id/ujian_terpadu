@extends('layouts.admin')

@section('title', 'Monitoring Ruang — ' . $sesi->nama_sesi)
@section('polling', true)

@section('breadcrumb')
    <a href="{{ route('pengawas.dashboard') }}" class="text-gray-500 hover:text-blue-600">Dashboard</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold truncate">{{ $sesi->nama_sesi }}</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="pengawasApp()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $sesi->nama_sesi }}</h1>
            <p class="text-sm text-gray-500">{{ $sesi->paket->nama ?? '—' }} · Terakhir update: <span x-text="lastUpdate">{{ now()->format('H:i:s') }}</span></p>
        </div>
        {{-- Buka/Tutup Sesi --}}
        <div class="flex items-center gap-2">
            @if($sesi->status === 'persiapan')
            <form action="#" method="POST">
                @csrf
                <button type="submit" class="btn-success">
                    Buka Sesi
                </button>
            </form>
            @elseif($sesi->status === 'berlangsung')
            <form action="#" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Tutup Sesi',message:'Tutup sesi sekarang? Peserta yang belum submit akan disubmit otomatis.',confirmText:'Ya, Tutup',danger:true})) $el.submit()">
                @csrf
                <button type="submit" class="btn-danger">
                    Tutup Sesi
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Progress Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900" x-text="stats.total">{{ $statsPeserta['total'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600" x-text="stats.online">{{ $statsPeserta['aktif'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Online</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600" x-text="stats.submit">{{ $statsPeserta['submit'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Submit</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-400" x-text="stats.belum">{{ $statsPeserta['belum_masuk'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Belum Mulai</p>
        </div>
    </div>

    {{-- Daftar Peserta --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-gray-900">Status Peserta</h2>
            <form method="GET" action="{{ route('pengawas.sesi', $sesi->id) }}" class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Cari peserta..."
                       value="{{ $filters['search'] ?? '' }}"
                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-40 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="mengerjakan" {{ ($filters['status'] ?? '') === 'mengerjakan' ? 'selected' : '' }}>Online</option>
                    <option value="submit" {{ ($filters['status'] ?? '') === 'submit' ? 'selected' : '' }}>Submit</option>
                    <option value="belum" {{ ($filters['status'] ?? '') === 'belum' ? 'selected' : '' }}>Belum Mulai</option>
                </select>
                <button type="submit" class="btn-primary">Cari</button>
                @if(!empty($filters['search']) || !empty($filters['status']))
                <a href="{{ route('pengawas.sesi', $sesi->id) }}" class="text-xs text-gray-500 hover:text-red-500">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Jawab</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Login</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pesertaPaginated as $sp)
                    <tr class="hover:bg-gray-50" x-data="{ get live() { return pesertaLive['{{ $sp->id }}'] ?? null } }">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sp->peserta->nama_lengkap ?? $sp->peserta->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sp->peserta->kelas ?? '' }}</p>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <template x-if="live && live.status === 'submit'">
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                            </template>
                            <template x-if="live && (live.status === 'mengerjakan' || live.status === 'login')">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Online
                                </span>
                            </template>
                            <template x-if="!live || !['submit','mengerjakan','login'].includes(live.status)">
                                <span class="text-xs text-gray-400">Belum</span>
                            </template>
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell text-gray-700 font-medium">
                            <span x-text="live ? live.soal_terjawab : '{{ $sp->jumlah_terjawab ?? 0 }}'"></span>
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                            <template x-if="live && live.sisa_waktu > 0 && ['mengerjakan','login'].includes(live.status)">
                                <span :class="live.sisa_waktu < 600 ? 'text-red-600 font-bold' : 'text-gray-600'"
                                      x-text="Math.floor(live.sisa_waktu/60) + ':' + String(live.sisa_waktu%60).padStart(2,'0')"></span>
                            </template>
                            <template x-if="!live || live.sisa_waktu <= 0 || !['mengerjakan','login'].includes(live.status)">
                                <span class="text-gray-400">—</span>
                            </template>
                        </td>
                        <td class="px-5 py-3 text-center hidden md:table-cell text-xs text-gray-500">
                            {{ $sp->mulai_at ? \Carbon\Carbon::parse($sp->mulai_at)->format('H:i:s') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-gray-400">
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
        @if($pesertaPaginated->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $pesertaPaginated->withQueryString()->links() }}
        </div>
        @endif
    </div>

</div>

<script>
function pengawasApp() {
    return {
        lastUpdate: '{{ now()->format("H:i:s") }}',
        stats: { total: {{ $statsPeserta['total'] }}, online: {{ $statsPeserta['aktif'] }}, submit: {{ $statsPeserta['submit'] }}, belum: {{ $statsPeserta['belum_masuk'] }} },
        pesertaLive: @json($pesertaLive ?? []),
        _loading: false,

        init() {
            this._pollInterval = setInterval(() => this.loadStats(), 10000);
        },

        destroy() {
            clearInterval(this._pollInterval);
        },

        async loadStats() {
            if (this._loading) return;
            this._loading = true;
            try {
                const res = await fetch('{{ route('pengawas.sesi.api', $sesi->id) }}', {
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
            } catch (e) { console.warn('monitoring:ruang', e); }
            this._loading = false;
        }
    };
}
</script>
@endsection
