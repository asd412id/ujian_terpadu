@extends('layouts.admin')

@section('title', 'Edit Sesi Ujian')

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <a href="{{ route('dinas.paket.show', $paket->id) }}" class="text-gray-500 hover:text-blue-600">{{ Str::limit($paket->nama, 30) }}</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Edit Sesi</span>
@endsection

@section('page-content')

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 max-w-2xl mb-4">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="max-w-2xl">
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-4">Edit Sesi: <span class="text-blue-600">{{ $sesi->nama_sesi }}</span></h2>

        <form action="{{ route('dinas.paket.sesi.update', [$paket->id, $sesi->id]) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Nama Sesi --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Sesi <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_sesi" value="{{ old('nama_sesi', $sesi->nama_sesi) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nama_sesi') border-red-400 @enderror">
                    @error('nama_sesi') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Ruangan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruangan</label>
                    <input type="text" name="ruangan" value="{{ old('ruangan', $sesi->ruangan) }}"
                           placeholder="Ruang Komputer 1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Kapasitas --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kapasitas Peserta</label>
                    <input type="number" name="kapasitas" value="{{ old('kapasitas', $sesi->kapasitas) }}"
                           min="1" max="999" placeholder="Kosongkan = tidak terbatas"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Waktu Mulai --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Mulai</label>
                    <input type="datetime-local" name="waktu_mulai"
                           value="{{ old('waktu_mulai', $sesi->waktu_mulai?->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('waktu_mulai') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Waktu Selesai --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Selesai</label>
                    <input type="datetime-local" name="waktu_selesai"
                           value="{{ old('waktu_selesai', $sesi->waktu_selesai?->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('waktu_selesai') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Pengawas --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pengawas</label>
                    <select name="pengawas_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Tanpa Pengawas --</option>
                        @foreach($pengawas as $p)
                        <option value="{{ $p->id }}" @selected(old('pengawas_id', $sesi->pengawas_id) === $p->id)>
                            {{ $p->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="persiapan"  @selected(old('status', $sesi->status) === 'persiapan')>Persiapan</option>
                        <option value="berlangsung" @selected(old('status', $sesi->status) === 'berlangsung')>Berlangsung</option>
                        <option value="selesai"    @selected(old('status', $sesi->status) === 'selesai')>Selesai</option>
                    </select>
                    @if($sesi->status === 'berlangsung')
                    <p class="text-xs text-amber-600 mt-1">⚠ Sesi sedang berlangsung. Ubah status dengan hati-hati.</p>
                    @endif
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Simpan Perubahan
                </button>
                <a href="{{ route('dinas.paket.show', $paket->id) }}"
                   class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
