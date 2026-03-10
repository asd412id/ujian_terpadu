@extends('layouts.admin')

@section('title', 'Paket Ujian')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Paket Ujian</span>
@endsection

@section('page-content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Paket Ujian</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('dinas.paket.trash') }}"
               class="btn-secondary inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Sampah
            </a>
            <a href="{{ route('dinas.paket.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Paket Ujian
            </a>
        </div>
    </div>

    {{-- Grid Paket --}}
    @forelse($paket as $item)
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h2 class="text-base font-semibold text-gray-900">{{ $item->nama }}</h2>
                    @if($item->status === 'aktif')
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                    @elseif($item->status === 'selesai')
                        <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Selesai</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Draft</span>
                    @endif
                    @if($item->jenjang)
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $item->jenjang }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mb-3">{{ $item->deskripsi }}</p>

                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        {{ $item->paket_soal_count ?? 0 }} soal
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $item->durasi_menit }} menit
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        {{ $item->sesi_count ?? 0 }} sesi
                    </span>
                    @if($item->jenis_ujian)
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        {{ $item->jenis_ujian }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Aksi --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                @if($item->status === 'draft')
                <form action="{{ route('dinas.paket.publish', $item->id) }}" method="POST"
                      onsubmit="return confirm('Publikasikan paket ujian ini?')">
                    @csrf
                    <button type="submit"
                            class="border border-green-300 hover:bg-green-50 text-green-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        Publish
                    </button>
                </form>
                @endif
                <a href="{{ route('dinas.paket.show', $item->id) }}"
                   class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                    Kelola Soal
                </a>
                <a href="{{ route('dinas.paket.edit', $item->id) }}"
                   class="border border-blue-300 hover:bg-blue-50 text-blue-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                    Edit
                </a>
                <form action="{{ route('dinas.paket.destroy', $item->id) }}" method="POST"
                      onsubmit="return confirm('Hapus paket ujian ini? Paket akan dipindahkan ke Sampah.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="border border-red-200 hover:bg-red-50 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-gray-500 mb-4">Belum ada paket ujian.</p>
        <a href="{{ route('dinas.paket.create') }}" class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Buat Paket Ujian Pertama
        </a>
    </div>
    @endforelse

    {{-- Pagination --}}
    @if($paket->hasPages())
    <div>{{ $paket->links() }}</div>
    @endif

</div>
@endsection
