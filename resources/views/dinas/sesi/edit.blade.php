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

@if(session('error'))
<div class="max-w-2xl mb-4">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
        {{ session('error') }}
    </div>
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
                    <select name="status" id="sesi-status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="persiapan"  @selected(old('status', $sesi->status) === 'persiapan')>Persiapan</option>
                        <option value="berlangsung" @selected(old('status', $sesi->status) === 'berlangsung')>Berlangsung</option>
                        <option value="selesai"    @selected(old('status', $sesi->status) === 'selesai')>Selesai</option>
                    </select>
                    @if($activePesertaCount > 0)
                    <p class="text-xs text-amber-600 mt-1 font-medium">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            {{ $activePesertaCount }} peserta sedang aktif (login/mengerjakan)
                        </span>
                    </p>
                    @elseif($sesi->status === 'berlangsung')
                    <p class="text-xs text-amber-600 mt-1">Sesi sedang berlangsung. Tidak ada peserta aktif saat ini.</p>
                    @endif
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit"
                        class="btn-primary">
                    Simpan Perubahan
                </button>
                <a href="{{ route('dinas.paket.show', $paket->id) }}"
                   class="btn-secondary">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const statusSelect = document.getElementById('sesi-status');
    const originalStatus = @json($sesi->status);
    const activePeserta = {{ $activePesertaCount }};

    form.addEventListener('submit', function(e) {
        const newStatus = statusSelect.value;

        if (activePeserta > 0 && newStatus === 'selesai' && originalStatus === 'berlangsung') {
            e.preventDefault();
            if (confirm('Ada ' + activePeserta + ' peserta yang sedang aktif (login/mengerjakan).\n\nMengubah status ke "Selesai" akan OTOMATIS mengumpulkan ujian semua peserta tersebut dan menghitung nilai mereka.\n\nLanjutkan?')) {
                form.submit();
            }
        }
    });
});
</script>
@endpush
