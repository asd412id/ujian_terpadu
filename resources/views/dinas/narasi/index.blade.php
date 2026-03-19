@extends('layouts.admin')

@section('title', 'Narasi Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Narasi Soal</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Narasi Soal</h1>
        <a href="{{ route('dinas.narasi.create') }}"
           class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Narasi
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Judul atau isi narasi..."
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-full sm:w-56">
            <label class="block text-xs font-medium text-gray-500 mb-1">Kategori</label>
            <select name="kategori"
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Kategori</option>
                @foreach($kategoris as $kat)
                <option value="{{ $kat->id }}" {{ request('kategori') == $kat->id ? 'selected' : '' }}>{{ $kat->nama }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if(request()->hasAny(['search', 'kategori']))
        <a href="{{ route('dinas.narasi.index') }}" class="btn-secondary text-sm">Reset</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="card overflow-hidden p-0">
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Judul</th>
                    <th class="px-5 py-3 text-left hidden md:table-cell">Kategori</th>
                    <th class="px-5 py-3 text-center">Soal</th>
                    <th class="px-5 py-3 text-center hidden md:table-cell">Status</th>
                    <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($narasis as $narasi)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('dinas.narasi.show', $narasi->id) }}" class="font-medium text-gray-900 hover:text-blue-600">
                            {{ $narasi->judul }}
                        </a>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{!! Str::limit(strip_tags($narasi->konten), 80) !!}</p>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell">
                        @if($narasi->kategori)
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $narasi->kategori->nama }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center font-medium text-gray-700">{{ $narasi->soal_list_count ?? 0 }}</td>
                    <td class="px-5 py-3 text-center hidden md:table-cell">
                        @if($narasi->is_active)
                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                        @else
                            <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('dinas.narasi.show', $narasi->id) }}"
                               class="text-gray-500 hover:text-blue-600 text-xs font-medium">Lihat</a>
                            <a href="{{ route('dinas.narasi.edit', $narasi->id) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                            <form action="{{ route('dinas.narasi.destroy', $narasi->id) }}" method="POST"
                                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Narasi',message:'Hapus narasi ini? Soal yang terkait akan dilepas dari narasi.',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Belum ada narasi. <a href="{{ route('dinas.narasi.create') }}" class="text-blue-600 hover:underline">Tambah narasi baru</a></td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($narasis as $narasi)
            <div class="px-4 py-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('dinas.narasi.show', $narasi->id) }}" class="font-medium text-gray-900 text-sm hover:text-blue-600">
                            {{ $narasi->judul }}
                        </a>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{!! Str::limit(strip_tags($narasi->konten), 80) !!}</p>
                    </div>
                    @if($narasi->is_active)
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full flex-shrink-0">Aktif</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0">Nonaktif</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    @if($narasi->kategori)
                    <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">{{ $narasi->kategori->nama }}</span>
                    @endif
                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">{{ $narasi->soal_list_count ?? 0 }} soal</span>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <a href="{{ route('dinas.narasi.show', $narasi->id) }}"
                       class="text-gray-500 hover:text-blue-600 text-xs font-medium">Lihat</a>
                    <a href="{{ route('dinas.narasi.edit', $narasi->id) }}"
                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                    <form action="{{ route('dinas.narasi.destroy', $narasi->id) }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Narasi',message:'Hapus narasi ini? Soal yang terkait akan dilepas dari narasi.',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="py-10 text-center text-gray-400 text-sm">Belum ada narasi. <a href="{{ route('dinas.narasi.create') }}" class="text-blue-600 hover:underline">Tambah narasi baru</a></div>
            @endforelse
        </div>
    </div>

    @if($narasis->hasPages())
    <div class="mt-4">{{ $narasis->links() }}</div>
    @endif

</div>
@endsection
