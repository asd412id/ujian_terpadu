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
            <p class="text-sm text-gray-500 mt-0.5">Pilih peserta dan cetak kartu login ujian.</p>
        </div>
    </div>

    {{-- Filter + Cetak Semua --}}
    <form method="GET" action="{{ route('sekolah.kartu.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, kelas..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="kelas"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kelas</option>
            @foreach($kelasList as $kls)
            <option value="{{ $kls }}" {{ request('kelas') === $kls ? 'selected' : '' }}>{{ $kls }}</option>
            @endforeach
        </select>
        <button type="submit"
                class="btn-primary">Cari</button>
        <a href="{{ route('sekolah.kartu.cetak-semua') . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}"
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
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">Kelas</th>
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
                        <td class="px-5 py-3 hidden sm:table-cell text-gray-600">{{ $p->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden md:table-cell">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded font-mono">{{ $p->username_ujian }}</code>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('sekolah.kartu.show', $p->id) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-800 text-xs font-medium border border-amber-200 hover:bg-amber-50 px-3 py-1.5 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Kartu
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($peserta->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $peserta->withQueryString()->links() }}</div>
        @endif
    </div>

</div>
@endsection
