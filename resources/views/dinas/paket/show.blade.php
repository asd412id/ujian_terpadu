@extends('layouts.admin')

@section('title', 'Kelola Soal Paket: ' . $paket->nama)

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold truncate">{{ $paket->nama }}</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="paketSoalApp()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $paket->nama }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $paket->jenjang }} · {{ $paket->durasi_menit }} menit ·
                <span class="font-medium text-gray-700">{{ $paket->paketSoal->count() }}</span> soal dipilih
            </p>
        </div>
        <a href="{{ route('dinas.paket.edit', $paket->id) }}"
           class="flex-shrink-0 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
            Edit Info Paket
        </a>
    </div>

    {{-- Soal Terpilih --}}
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-3">Soal Terpilih ({{ $paket->paketSoal->count() }})</h2>
        @if($paket->paketSoal->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center">Belum ada soal dalam paket ini. Tambahkan dari bank soal di bawah.</p>
        @else
        <div class="space-y-2" id="soal-terpilih">
            @foreach($paket->paketSoal->sortBy('nomor_urut') as $ps)
            <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full text-xs font-bold text-blue-700 flex items-center justify-center">
                    {{ $ps->nomor_urut }}
                </span>
                <p class="flex-1 text-sm text-gray-800 line-clamp-1">{{ strip_tags($ps->soal->pertanyaan) }}</p>
                @php
                    $jenisColors = ['pg'=>'blue','pg_kompleks'=>'purple','isian'=>'green','essay'=>'amber','menjodohkan'=>'pink'];
                    $jColor = $jenisColors[$ps->soal->tipe_soal] ?? 'gray';
                @endphp
                <span class="flex-shrink-0 text-xs font-semibold bg-{{ $jColor }}-100 text-{{ $jColor }}-700 px-2 py-0.5 rounded-full">
                    {{ ucfirst(str_replace('_', ' ', $ps->soal->tipe_soal)) }}
                </span>
                <span class="flex-shrink-0 text-xs text-gray-500">Bobot: {{ $ps->soal->bobot }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Bank Soal - Tambah Soal --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
            <h2 class="font-semibold text-gray-900">Tambah dari Bank Soal</h2>
            <div class="flex items-center gap-2">
                <input type="text" x-model="search" placeholder="Cari soal..."
                       class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select x-model="filterJenis"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Jenis</option>
                    <option value="pg">Pilihan Ganda</option>
                    <option value="pg_kompleks">PG Kompleks</option>
                    <option value="isian">Isian</option>
                    <option value="essay">Essay</option>
                    <option value="menjodohkan">Menjodohkan</option>
                </select>
            </div>
        </div>

        <div class="space-y-2">
            @php $terpilihIds = $paket->paketSoal->pluck('soal_id')->toArray(); @endphp
            @forelse($bankSoal as $soal)
            <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0"
                 x-show="(!search || '{{ strtolower(strip_tags($soal->pertanyaan)) }}'.includes(search.toLowerCase()))
                         && (!filterJenis || filterJenis === '{{ $soal->tipe_soal }}')">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 line-clamp-1">{{ strip_tags($soal->pertanyaan) }}</p>
                    <p class="text-xs text-gray-500">{{ $soal->kategori->nama ?? '' }} · Bobot {{ $soal->bobot }}</p>
                </div>
                @if(in_array($soal->id, $terpilihIds))
                    <span class="flex-shrink-0 text-xs text-green-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Ditambahkan
                    </span>
                @else
                <form action="{{ route('dinas.paket.soal.add', $paket->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="soal_id" value="{{ $soal->id }}">
                    <button type="submit"
                            class="flex-shrink-0 text-blue-600 hover:text-blue-800 text-xs font-medium border border-blue-200 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                        + Tambah
                    </button>
                </form>
                @endif
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-6">Bank soal kosong.</p>
            @endforelse
        </div>

        @if($bankSoal->hasPages())
        <div class="mt-4">{{ $bankSoal->links() }}</div>
        @endif
    </div>

</div>

<script>
function paketSoalApp() {
    return { search: '', filterJenis: '' };
}
</script>
@endsection
