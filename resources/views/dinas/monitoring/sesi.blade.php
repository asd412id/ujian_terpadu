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
                       class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <div class="w-56" x-data x-on:change="$el.closest('form').submit()">
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
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-center">Jawab</th>
                        <th class="px-5 py-3 text-center">Ragu</th>
                        <th class="px-5 py-3 text-center">Nilai</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Sisa Waktu</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Login</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pesertaList as $sp)
                    @if(!$sp->peserta) @continue @endif
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sp->peserta->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sp->peserta->nis ?? $sp->peserta->nisn }}</p>
                        </td>
                        <td class="px-5 py-3 hidden sm:table-cell text-xs text-gray-600">{{ $sp->peserta->sekolah?->nama ?? '—' }}</td>
                        <td class="px-5 py-3 hidden sm:table-cell text-gray-600">{{ $sp->peserta->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($sp->status === 'submit' || $sp->status === 'dinilai')
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                            @elseif($sp->status === 'mengerjakan' || $sp->status === 'login')
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Online
                                </span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Belum</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center font-medium text-gray-900">{{ $sp->soal_terjawab ?? 0 }}/{{ $sesi->paket?->jumlah_soal ?? '?' }}</td>
                        <td class="px-5 py-3 text-center text-amber-600 font-medium">{{ $sp->soal_ditandai ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @if(in_array($sp->status, ['submit', 'dinilai']) && $sp->nilai_akhir !== null)
                                <span class="font-bold {{ $sp->nilai_akhir >= 70 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($sp->nilai_akhir, 1) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center hidden md:table-cell">
                            @if($sp->status === 'mengerjakan' && $sp->getSisaWaktuDetikAttribute() !== null)
                                @php $sisa = $sp->getSisaWaktuDetikAttribute(); @endphp
                                <span class="{{ $sisa < 600 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                    {{ floor($sisa / 60) }}:{{ str_pad($sisa % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell text-xs text-gray-500">
                            {{ $sp->mulai_at ? \Carbon\Carbon::parse($sp->mulai_at)->format('H:i:s') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-5 py-10 text-center text-gray-400">
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
        @if($pesertaList->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $pesertaList->withQueryString()->links() }}
        </div>
        @endif
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
        _loading: false,

        init() {
            setInterval(() => this.loadStats(), 5000);
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
                    this.lastUpdate = new Date().toLocaleTimeString('id-ID');
                }
            } catch (e) { /* offline */ }
            this._loading = false;
        }
    };
}
</script>
@endsection
