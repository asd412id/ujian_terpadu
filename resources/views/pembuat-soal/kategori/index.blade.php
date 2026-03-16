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

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left w-8">#</th>
                        <th class="px-5 py-3 text-left">Nama</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">Kode</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Jenjang</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Kelompok</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Kurikulum</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kategoris as $index => $kat)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $index + 1 }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $kat->nama }}</td>
                        <td class="px-5 py-3 hidden sm:table-cell text-gray-600 text-xs font-mono">{{ $kat->kode ?? '—' }}</td>
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
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            Belum ada kategori soal.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
