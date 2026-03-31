@extends('layouts.admin')

@section('title', 'Cetak Kartu Login')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Cetak Kartu Login</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Cetak Kartu Login Peserta</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pilih sekolah dan peserta, lalu cetak kartu login ujian.</p>
        </div>
    </div>

    {{-- Filter + Cetak Semua --}}
    <form method="GET" action="{{ route('dinas.kartu.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <div class="sm:w-64">
            <x-searchable-select
                name="sekolah_id"
                :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => '[' . $s->jenjang . '] ' . $s->nama])"
                :value="request('sekolah_id')"
                placeholder="Semua Sekolah"
                size="md" />
        </div>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, NIS, NISN..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <input type="text" name="kelas" value="{{ request('kelas') }}" placeholder="Kelas..."
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-32">
        <button type="submit"
                class="btn-primary">Cari</button>
        @if(request()->hasAny(['q', 'sekolah_id', 'kelas']))
        <a href="{{ route('dinas.kartu.index') }}"
           class="btn-secondary text-center">Reset</a>
        @endif
        <a href="{{ route('dinas.kartu.cetak-semua') . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Cetak Semua
        </a>
    </form>

    {{-- Grid Peserta --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3.5 border-b border-gray-100 text-sm text-gray-500">
            <span>{{ $peserta->total() }} peserta ditemukan</span>
        </div>
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Username</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($peserta as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $p->nis ?? $p->nisn }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">{{ $p->kelas ?? '-' }}</td>
                        <td class="px-5 py-3 hidden lg:table-cell">
                            <p class="text-gray-700 text-xs font-medium">{{ $p->sekolah?->nama ?? '-' }}</p>
                            <p class="text-gray-400 text-xs">{{ $p->sekolah?->jenjang }}</p>
                        </td>
                        <td class="px-5 py-3 text-center hidden md:table-cell">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded font-mono">{{ $p->username_ujian }}</code>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('dinas.kartu.show', $p->id) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-800 text-xs font-medium border border-amber-200 hover:bg-amber-50 px-3 py-1.5 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Kartu
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($peserta as $p)
            <div class="px-4 py-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $p->nama }}</p>
                        <p class="text-xs text-gray-500">{{ $p->nis ?? $p->nisn }}</p>
                    </div>
                    @if($p->kelas)
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full flex-shrink-0">{{ $p->kelas }}</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500">{{ $p->sekolah?->nama ?? '-' }}</div>
                @if($p->username_ujian)
                <div class="text-xs">
                    <span class="text-gray-500">Username:</span>
                    <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-gray-700">{{ $p->username_ujian }}</code>
                </div>
                @endif
                <div>
                    <a href="{{ route('dinas.kartu.show', $p->id) }}" target="_blank"
                       class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-800 text-xs font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Cetak Kartu
                    </a>
                </div>
            </div>
            @empty
            <div class="py-10 text-center text-gray-400 text-sm">Belum ada peserta.</div>
            @endforelse
        </div>

        @if($peserta->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $peserta->withQueryString()->links() }}</div>
        @endif
    </div>

</div>
@endsection
