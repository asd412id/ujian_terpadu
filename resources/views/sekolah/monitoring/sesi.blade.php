@extends('layouts.admin')

@section('title', 'Monitoring Peserta — ' . $sesi->nama_sesi)
@section('polling', true)

@section('breadcrumb')
    <a href="{{ route('sekolah.monitoring') }}" class="text-gray-500 hover:text-blue-600">Monitoring</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold truncate">{{ $sesi->nama_sesi }}</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="monitoringSesiSekolahApp()" x-init="init()">

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
                {{ $sesi->paket?->nama ?? '—' }} · {{ $sesi->paket?->sekolah?->nama ?? 'Sekolah' }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-semibold px-3 py-1 rounded-full
                {{ $sesi->status === 'berlangsung' ? 'bg-green-100 text-green-800' : ($sesi->status === 'selesai' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                {{ ucfirst($sesi->status) }}
            </span>
            <span class="text-xs text-gray-400">Terakhir: <span x-text="lastUpdate">{{ now()->format('H:i:s') }}</span></span>
        </div>
    </div>

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

    @if($alerts->isNotEmpty())
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Peringatan Aktivitas</h2>
            <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded-full">{{ $alerts->count() }} alert</span>
        </div>
        <div class="space-y-3">
            @foreach($alerts->take(5) as $alert)
                <div class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50/50 p-3">
                    <div class="mt-0.5 w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0"></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ $alert->sesiPeserta?->peserta?->nama ?? 'Peserta' }}</p>
                        <p class="text-xs text-gray-600">
                            {{ str_replace('_', ' ', $alert->tipe_event) }} · {{ $alert->created_at?->format('H:i:s') ?? '—' }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-gray-900">Daftar Peserta</h2>
            <form method="GET" action="{{ route('sekolah.monitoring.sesi', $sesi->id) }}" class="flex items-center gap-2 flex-wrap">
                <input type="text" name="search" placeholder="Cari nama / NIS..."
                       value="{{ $filters['search'] ?? '' }}"
                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-full sm:w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="online" {{ ($filters['status'] ?? '') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="submit" {{ ($filters['status'] ?? '') === 'submit' ? 'selected' : '' }}>Submit</option>
                    <option value="belum" {{ ($filters['status'] ?? '') === 'belum' ? 'selected' : '' }}>Belum Mulai</option>
                </select>
                <button type="submit" class="btn-primary">Cari</button>
                @if(!empty($filters['search']) || !empty($filters['status']))
                <a href="{{ route('sekolah.monitoring.sesi', $sesi->id) }}" class="text-xs text-gray-500 hover:text-red-500">Reset</a>
                @endif
            </form>
        </div>

        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-center">Jawab</th>
                        <th class="px-5 py-3 text-center">Ragu</th>
                        @if($sesi->status === 'selesai')
                        <th class="px-5 py-3 text-center">Nilai</th>
                        @endif
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Login</th>
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
                        <td class="px-5 py-3 hidden md:table-cell text-xs text-gray-600">{{ $sp->peserta->kelas ?? '—' }}</td>
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
                        @if($sesi->status === 'selesai')
                        <td class="px-5 py-3 text-center">
                            <template x-if="live && ['submit','dinilai'].includes(live.status) && live.nilai_akhir !== null">
                                <span :class="live.nilai_akhir >= 70 ? 'font-bold text-green-600' : 'font-bold text-red-600'"
                                      x-text="parseFloat(live.nilai_akhir).toFixed(1)"></span>
                            </template>
                            <template x-if="!live || !['submit','dinilai'].includes(live.status) || live.nilai_akhir === null">
                                <span class="text-gray-400">—</span>
                            </template>
                        </td>
                        @endif
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
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $sesi->status === 'selesai' ? 8 : 7 }}" class="px-5 py-10 text-center text-gray-400">
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

        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($pesertaList as $sp)
            @if(!$sp->peserta) @continue @endif
            <div class="px-4 py-3" x-data="{ get live() { return pesertaLive['{{ $sp->id }}'] ?? null } }">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 text-sm">{{ $sp->peserta->nama }}</p>
                        <p class="text-xs text-gray-500">{{ $sp->peserta->nis ?? $sp->peserta->nisn }} · {{ $sp->peserta->kelas ?? '—' }}</p>
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
                <div class="grid {{ $sesi->status === 'selesai' ? 'grid-cols-3' : 'grid-cols-2' }} gap-2 text-center text-xs mb-2">
                    <div class="bg-gray-50 rounded-lg p-1.5">
                        <p class="font-bold text-gray-900" x-text="live ? (live.soal_terjawab + '/{{ $sesi->paket?->jumlah_soal ?? '?' }}') : '{{ ($sp->soal_terjawab ?? 0) . '/' . ($sesi->paket?->jumlah_soal ?? '?') }}'"></p>
                        <p class="text-gray-500">Jawab</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-1.5">
                        <p class="font-bold text-amber-700" x-text="live ? live.soal_ditandai : '{{ $sp->soal_ditandai ?? 0 }}'"></p>
                        <p class="text-gray-500">Ragu</p>
                    </div>
                    @if($sesi->status === 'selesai')
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
                    @endif
                </div>
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

</div>

<script>
function monitoringSesiSekolahApp() {
    return {
        lastUpdate: '{{ now()->format("H:i:s") }}',
        stats: {
            total: {{ $stats['total'] }},
            online: {{ $stats['online'] }},
            submit: {{ $stats['submit'] }},
            belum_mulai: {{ $stats['belum_mulai'] }},
        },
        pesertaLive: @json([]),
        showResetModal: false,
        _loading: false,

        init() {
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
                const res = await fetch('{{ route('sekolah.monitoring.sesi.api', $sesi->id) }}', {
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
            } catch (e) { /* silent */ }
            this._loading = false;
        }
    };
}
</script>
@endsection
