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
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    Buka Sesi
                </button>
            </form>
            @elseif($sesi->status === 'berlangsung')
            <form action="#" method="POST"
                  onsubmit="return confirm('Tutup sesi sekarang? Peserta yang belum submit akan disubmit otomatis.')">
                @csrf
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
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
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Cari</button>
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
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sp->peserta->nama_lengkap ?? $sp->peserta->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sp->peserta->kelas ?? '' }}</p>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($sp->status === 'submit')
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                            @elseif($sp->status === 'mengerjakan' || $sp->status === 'login')
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Online
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Belum</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell text-gray-700 font-medium">
                            {{ $sp->jumlah_terjawab ?? 0 }}
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                            @if($sp->status === 'mengerjakan' && $sp->getSisaWaktuDetikAttribute() !== null)
                                @php $sisa = $sp->getSisaWaktuDetikAttribute(); @endphp
                                <span class="{{ $sisa < 600 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                    {{ floor($sisa / 60) }}:{{ str_pad($sisa % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
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

        init() {
            setInterval(() => this.loadStats(), 5000);
        },

        async loadStats() {
            try {
                const res = await fetch('{{ route('pengawas.sesi.api', $sesi->id) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.stats = data.stats ?? this.stats;
                    this.lastUpdate = new Date().toLocaleTimeString('id-ID');
                }
            } catch (e) {}
        }
    };
}
</script>
@endsection
