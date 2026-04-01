@extends('layouts.admin')

@section('title', 'Penugasan Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Penugasan Soal</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Penugasan Soal</h1>
            <p class="text-sm text-gray-500 mt-1">Tugaskan kategori soal atau soal individual ke pembuat soal</p>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    <div class="card overflow-hidden p-0" x-data="{ search: '' }">
        <div class="px-5 py-3 border-b border-gray-100">
            <div class="relative w-full sm:w-64">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Cari pembuat soal..."
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
                        <th class="px-5 py-3 text-left">Pembuat Soal</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Email</th>
                        <th class="px-5 py-3 text-center">Kategori Ditugaskan</th>
                        <th class="px-5 py-3 text-center">Soal Individual</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50" x-show="!search || (@js(strtolower($user->name . ' ' . $user->email))).includes(search.toLowerCase())">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-teal-700 text-xs font-bold">{{ substr($user->name, 0, 1) }}</span>
                                </div>
                                <span class="font-medium text-gray-900">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">{{ $user->email }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($user->assigned_kategori_soal_count > 0)
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    {{ $user->assigned_kategori_soal_count }} kategori
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($user->assigned_soal_count > 0)
                                <span class="inline-flex items-center gap-1 text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    {{ $user->assigned_soal_count }} soal
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('dinas.penugasan.show', $user->id) }}"
                               class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-800">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Kelola
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-0">
                            <x-empty-state icon="document" title="Belum ada penugasan" subtitle="Buat penugasan baru untuk pembuat soal." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($users as $user)
            <div class="px-4 py-3" x-show="!search || (@js(strtolower($user->name . ' ' . $user->email))).includes(search.toLowerCase())">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-teal-700 text-xs font-bold">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap text-xs ml-10.5 mb-2">
                    @if($user->assigned_kategori_soal_count > 0)
                        <span class="font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $user->assigned_kategori_soal_count }} kategori</span>
                    @endif
                    @if($user->assigned_soal_count > 0)
                        <span class="font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{{ $user->assigned_soal_count }} soal</span>
                    @endif
                    @if($user->assigned_kategori_soal_count === 0 && $user->assigned_soal_count === 0)
                        <span class="text-gray-400">Belum ada penugasan</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 ml-10.5">
                    <a href="{{ route('dinas.penugasan.show', $user->id) }}"
                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Kelola Penugasan</a>
                </div>
            </div>
            @empty
            <x-empty-state icon="document" title="Belum ada penugasan" subtitle="Buat penugasan baru untuk pembuat soal." compact />
            @endforelse
        </div>
    </div>
</div>
@endsection
