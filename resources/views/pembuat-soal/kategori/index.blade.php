@extends('layouts.admin')

@section('title', 'Kategori Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Kategori Soal</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">Kategori Soal</h1>
    </div>

    <div class="card overflow-hidden p-0" x-data="{ search: '' }">
        <div class="px-5 py-3 border-b border-gray-100">
            <div class="relative w-full sm:w-64">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Cari kategori..."
                       class="w-full pl-9 pr-8 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button x-show="search" x-cloak @click="search = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left w-8">#</th>
                        <th class="px-5 py-3 text-left">Nama</th>
                        <th class="px-5 py-3 text-left">Kode</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Jenjang</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Kelompok</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Kurikulum</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kategoris as $index => $kat)
                    <tr class="hover:bg-gray-50" x-show="!search || (@js(strtolower($kat->nama . ' ' . ($kat->kode ?? '') . ' ' . ($kat->kelompok ?? '')))).includes(search.toLowerCase())">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $index + 1 }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $kat->nama }}</td>
                        <td class="px-5 py-3 text-gray-600 text-xs font-mono">{{ $kat->kode ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600">{{ $kat->jenjang ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600">{{ $kat->kelompok ?? '—' }}</td>
                        <td class="px-5 py-3 hidden lg:table-cell text-gray-600">{{ $kat->kurikulum ?? '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($kat->is_active)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-0">
                            <x-empty-state icon="folder" title="Belum ada kategori" subtitle="Kategori soal akan muncul setelah admin menambahkannya." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($kategoris as $index => $kat)
            <div class="px-4 py-3" x-show="!search || (@js(strtolower($kat->nama . ' ' . ($kat->kode ?? '') . ' ' . ($kat->kelompok ?? '')))).includes(search.toLowerCase())">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $kat->nama }}</p>
                        @if($kat->kode)
                        <p class="text-xs text-gray-500 font-mono mt-0.5">{{ $kat->kode }}</p>
                        @endif
                    </div>
                    @if($kat->is_active)
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full shrink-0">Aktif</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">Nonaktif</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-wrap text-xs text-gray-500">
                    @if($kat->jenjang)
                    <span>{{ $kat->jenjang }}</span>
                    @endif
                    @if($kat->kelompok)
                    <span>· {{ $kat->kelompok }}</span>
                    @endif
                    @if($kat->kurikulum)
                    <span>· {{ $kat->kurikulum }}</span>
                    @endif
                </div>
            </div>
            @empty
            <x-empty-state icon="folder" title="Belum ada kategori" subtitle="Kategori soal akan muncul setelah admin menambahkannya." compact />
            @endforelse
        </div>
    </div>

</div>
@endsection
