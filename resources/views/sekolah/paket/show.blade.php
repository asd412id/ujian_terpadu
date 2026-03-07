@extends('layouts.admin')

@section('title', 'Detail Paket Ujian')

@section('breadcrumb')
    <a href="{{ route('sekolah.paket') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ $paket->nama }}</span>
@endsection

@section('page-content')
<div class="space-y-6">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Paket</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-500">Nama:</span> <span class="font-medium">{{ $paket->nama }}</span></div>
            <div><span class="text-gray-500">Jenis Ujian:</span> <span class="font-medium">{{ $paket->jenis_ujian }}</span></div>
            <div><span class="text-gray-500">Durasi:</span> <span class="font-medium">{{ $paket->durasi_menit }} menit</span></div>
            <div><span class="text-gray-500">Jumlah Soal:</span> <span class="font-medium">{{ $paket->paketSoal->count() }}</span></div>
            <div><span class="text-gray-500">Status:</span> <span class="font-medium">{{ ucfirst($paket->status) }}</span></div>
        </div>
    </div>

    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Sesi Ujian</h2>
        @forelse($paket->sesi as $sesi)
        <div class="border border-gray-200 rounded-lg p-4 mb-3">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-medium text-gray-900">{{ $sesi->nama_sesi }}</h3>
                    <p class="text-sm text-gray-500">Status: {{ ucfirst($sesi->status) }} · {{ $sesi->sesiPeserta->count() }} peserta</p>
                </div>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-500">Belum ada sesi ujian.</p>
        @endforelse
    </div>
</div>
@endsection
