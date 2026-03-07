@extends('layouts.admin')

@section('title', 'Data Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Data Sekolah</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Data Sekolah</h1>
        <a href="{{ route('dinas.sekolah.create') }}" class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Sekolah
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.sekolah.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama sekolah..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="tingkat"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Tingkat</option>
            <option value="SD" {{ request('tingkat') === 'SD' ? 'selected' : '' }}>SD</option>
            <option value="SMP" {{ request('tingkat') === 'SMP' ? 'selected' : '' }}>SMP</option>
            <option value="SMA" {{ request('tingkat') === 'SMA' ? 'selected' : '' }}>SMA</option>
        </select>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Cari</button>
        @if(request()->hasAny(['q', 'tingkat']))
        <a href="{{ route('dinas.sekolah.index') }}"
           class="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg text-center">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Nama Sekolah</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">NPSN</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Tingkat</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Peserta</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sekolahList as $sekolah)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sekolah->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sekolah->alamat }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600 font-mono text-xs">{{ $sekolah->npsn ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $sekolah->jenjang }}</span>
                        </td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell font-medium text-gray-700">{{ $sekolah->peserta_count ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($sekolah->is_active)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('dinas.sekolah.edit', $sekolah->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('dinas.sekolah.destroy', $sekolah->id) }}" method="POST"
                                      onsubmit="return confirm('Hapus sekolah ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            Belum ada data sekolah.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sekolahList->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $sekolahList->withQueryString()->links() }}</div>
        @endif
    </div>

</div>
@endsection
