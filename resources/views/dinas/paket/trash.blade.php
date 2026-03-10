@extends('layouts.admin')

@section('title', 'Sampah - Paket Ujian')

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-blue-600 hover:underline">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-800 font-semibold">Sampah</span>
@endsection

@section('page-content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Sampah Paket Ujian</h1>
            <p class="text-sm text-gray-500 mt-1">Paket yang dihapus dapat dipulihkan atau dihapus permanen.</p>
        </div>
        <a href="{{ route('dinas.paket.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
    </div>

    {{-- Grid Paket Terhapus --}}
    @forelse($paket as $item)
    <div class="card border-l-4 border-l-red-200">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h2 class="text-base font-semibold text-gray-900">{{ $item->nama }}</h2>
                    <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Dihapus</span>
                    @if($item->jenjang)
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $item->jenjang }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mb-2">{{ $item->deskripsi }}</p>

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
                    <span class="flex items-center gap-1.5 text-red-500">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Dihapus {{ $item->deleted_at->diffForHumans() }}
                    </span>
                </div>
            </div>

            {{-- Aksi --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                <form action="{{ route('dinas.paket.restore', $item->id) }}" method="POST"
                      onsubmit="return confirm('Pulihkan paket ujian ini?')">
                    @csrf
                    <button type="submit"
                            class="border border-green-300 hover:bg-green-50 text-green-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Pulihkan
                    </button>
                </form>
                <form action="{{ route('dinas.paket.force-delete', $item->id) }}" method="POST"
                      onsubmit="return confirm('HAPUS PERMANEN paket ujian ini?\n\nSemua sesi, jawaban peserta, dan log aktivitas akan DIHAPUS PERMANEN dan tidak dapat dikembalikan!')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="border border-red-300 hover:bg-red-50 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Permanen
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <p class="text-gray-500 mb-4">Sampah kosong.</p>
        <a href="{{ route('dinas.paket.index') }}" class="text-blue-600 hover:underline text-sm">Kembali ke Paket Ujian</a>
    </div>
    @endforelse

    {{-- Pagination --}}
    @if($paket->hasPages())
    <div>{{ $paket->links() }}</div>
    @endif

</div>
@endsection
