@extends('layouts.admin')

@section('title', 'Dashboard Pengawas')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Dashboard Pengawas</span>
@endsection

@section('page-content')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-bold text-gray-900">Dashboard Pengawas</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ auth()->user()->sekolah->nama_sekolah ?? '' }} · {{ now()->isoFormat('dddd, D MMMM Y, HH:mm') }}
        </p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_sesi'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total Sesi</p>
        </div>
        <div class="card p-5 text-center {{ $stats['sesi_berlangsung'] > 0 ? 'bg-green-50 border-green-200' : '' }}">
            <p class="text-2xl font-bold {{ $stats['sesi_berlangsung'] > 0 ? 'text-green-600' : 'text-gray-900' }}">{{ $stats['sesi_berlangsung'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sesi Berlangsung</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $stats['peserta_online'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Peserta Online</p>
        </div>
    </div>

    {{-- Sesi yang Diawasi --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Sesi Ujian</h2>
        </div>
        @forelse($sesiList as $sesi)
        <div class="flex items-center gap-4 py-3 border-b border-gray-100 last:border-0">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 text-sm">{{ $sesi->nama_sesi }}</p>
                <p class="text-xs text-gray-500">{{ $sesi->paket->nama ?? '—' }} · {{ $sesi->paket->durasi_menit ?? 0 }} menit</p>
            </div>
            <div class="text-right flex-shrink-0">
                @if($sesi->status === 'berlangsung')
                    <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                        Live
                    </span>
                @elseif($sesi->status === 'menunggu')
                    <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">Menunggu</span>
                @else
                    <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-1 rounded-full">Selesai</span>
                @endif
            </div>
            <a href="{{ route('pengawas.sesi', $sesi->id) }}"
               class="flex-shrink-0 text-blue-600 hover:text-blue-800 text-sm font-medium">
                Monitor →
            </a>
        </div>
        @empty
        <div class="py-8 text-center text-gray-400 text-sm">
            Tidak ada sesi ujian yang ditugaskan kepada Anda.
        </div>
        @endforelse
    </div>

</div>
@endsection
