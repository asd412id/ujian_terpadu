@extends('layouts.admin')

@section('title', 'Detail Sekolah')

@section('breadcrumb')
    <a href="{{ route('dinas.sekolah.index') }}" class="text-gray-500 hover:text-gray-700">Sekolah</a>
    <span class="mx-1 text-gray-400">/</span>
    <span class="text-gray-800 font-semibold">{{ $sekolah->nama }}</span>
@endsection

@section('page-content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $sekolah->nama }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $sekolah->jenjang }} · NPSN: {{ $sekolah->npsn ?? '-' }}</p>
        </div>
        <a href="{{ route('dinas.sekolah.edit', $sekolah->id) }}"
           class="btn-primary inline-flex items-center gap-2">
            Edit Sekolah
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Sekolah Info --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Informasi Sekolah</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Nama</dt>
                        <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ $sekolah->nama }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Jenjang</dt>
                        <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ $sekolah->jenjang }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Alamat</dt>
                        <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ $sekolah->alamat ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Kota</dt>
                        <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ $sekolah->kota ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Telepon</dt>
                        <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ $sekolah->telepon ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">Status</dt>
                        <dd class="mt-0.5">
                            @if($sekolah->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Nonaktif</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Quick Stats --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Statistik</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-blue-700">{{ $sekolah->peserta->count() }}</p>
                        <p class="text-xs text-blue-600 mt-0.5">Peserta</p>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold text-purple-700">{{ $sekolah->paketUjian->count() }}</p>
                        <p class="text-xs text-purple-600 mt-0.5">Paket Ujian</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Side --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Paket Ujian --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Paket Ujian</h2>
                </div>
                @forelse($sekolah->paketUjian as $paket)
                <div class="border border-gray-100 rounded-xl p-4 mb-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ $paket->nama }}</p>
                            <p class="text-sm text-gray-500 mt-0.5">{{ $paket->jenjang }} · {{ $paket->jenis_ujian_label }} · {{ $paket->durasi_menit }} menit</p>
                        </div>
                        <span class="text-xs font-semibold px-2 py-1 rounded-full
                            {{ $paket->status === 'aktif' ? 'bg-green-100 text-green-800' : ($paket->status === 'selesai' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ ucfirst($paket->status) }}
                        </span>
                    </div>
                    @if($paket->sesi->count() > 0)
                    <div class="mt-3 space-y-1">
                        @foreach($paket->sesi as $sesi)
                        <div class="flex items-center justify-between text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-1.5">
                            <span>{{ $sesi->nama_sesi }}</span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full
                                {{ $sesi->status === 'berlangsung' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($sesi->status) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @empty
                <p class="text-sm text-gray-400 text-center py-4">Belum ada paket ujian</p>
                @endforelse
            </div>

            {{-- Peserta --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Peserta ({{ $sekolah->peserta->count() }})</h2>
                </div>
                @forelse($sekolah->peserta->take(10) as $p)
                <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-bold text-blue-700">{{ substr($p->nama, 0, 1) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $p->nama }}</p>
                        <p class="text-xs text-gray-500">Kelas {{ $p->kelas }} · {{ $p->nis }}</p>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $p->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                @empty
                <p class="text-sm text-gray-400 text-center py-4">Belum ada peserta terdaftar</p>
                @endforelse
                @if($sekolah->peserta->count() > 10)
                <p class="text-xs text-gray-400 text-center mt-3">... dan {{ $sekolah->peserta->count() - 10 }} peserta lainnya</p>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection
