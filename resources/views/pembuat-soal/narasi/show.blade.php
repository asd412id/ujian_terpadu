@extends('layouts.admin')

@section('title', 'Detail Narasi — ' . $narasi->judul)

@section('breadcrumb')
    <a href="{{ route('pembuat-soal.narasi.index') }}" class="text-gray-500 hover:text-blue-600">Narasi Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ Str::limit($narasi->judul, 40) }}</span>
@endsection

@section('page-content')
<div class="space-y-5 max-w-4xl">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $narasi->judul }}</h1>
            <div class="flex items-center gap-2 mt-1 text-sm text-gray-500">
                @if($narasi->kategori)
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $narasi->kategori->nama }}</span>
                @endif
                @if($narasi->is_active)
                    <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                @else
                    <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                @endif
                @if($narasi->pembuat)
                    <span>oleh {{ $narasi->pembuat->name }}</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pembuat-soal.narasi.edit', $narasi->id) }}" class="btn-primary text-sm">Edit Narasi</a>
        </div>
    </div>

    {{-- Narasi Content --}}
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-3">Teks Narasi</h2>
        @if($narasi->gambar)
        <div class="mb-4">
            <img src="{{ Storage::url($narasi->gambar) }}" alt="Gambar Narasi" class="max-h-60 rounded-lg border">
        </div>
        @endif
        <div class="prose prose-sm max-w-none text-gray-700 bg-gray-50 rounded-xl p-5 border border-gray-200">
            {!! $narasi->konten !!}
        </div>
    </div>

    {{-- Soal List --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Soal Terkait ({{ $narasi->soalList->count() }})</h2>
        </div>

        @if($narasi->soalList->count() > 0)
        <div class="space-y-3">
            @foreach($narasi->soalList as $idx => $soal)
            <div class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-7 h-7 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">
                        {{ $soal->urutan_dalam_narasi ?: $idx + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full uppercase">{{ $soal->tipe_soal }}</span>
                            @if($soal->tingkat_kesulitan)
                            <span class="text-xs text-gray-500">{{ $soal->tingkat_kesulitan }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-800 line-clamp-2">{!! Str::limit(strip_tags($soal->pertanyaan), 150) !!}</div>
                        @if($soal->opsiJawaban->count() > 0)
                        <div class="mt-2 grid grid-cols-2 gap-1">
                            @foreach($soal->opsiJawaban as $opsi)
                            <div class="text-xs {{ $opsi->is_correct ? 'text-green-700 font-semibold' : 'text-gray-500' }}">
                                {{ $opsi->label }}. {!! Str::limit(strip_tags($opsi->teks), 50) !!}
                                @if($opsi->is_correct) ✓ @endif
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <a href="{{ route('pembuat-soal.soal.edit', $soal->id) }}"
                       class="text-xs text-blue-600 hover:text-blue-800 font-medium flex-shrink-0">Edit</a>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-400 text-sm text-center py-6">
            Belum ada soal yang terkait dengan narasi ini.<br>
            <a href="{{ route('pembuat-soal.soal.create') }}" class="text-blue-600 hover:underline">Tambah soal baru</a> dan pilih narasi ini.
        </p>
        @endif
    </div>

</div>
@endsection
