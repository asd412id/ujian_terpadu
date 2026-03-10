@extends('layouts.admin')

@section('title', 'Laporan Ujian')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Laporan</span>
@endsection

@section('page-content')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-bold text-gray-900">Laporan Ujian</h1>
        <p class="text-sm text-gray-500 mt-0.5">Rekap dan unduh hasil ujian per sekolah atau per paket.</p>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.laporan') }}" class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Filter Laporan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Paket Ujian</label>
                <select name="paket_id"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Paket</option>
                    @foreach($paketList as $p)
                    <option value="{{ $p->id }}" {{ request('paket_id') == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah</label>
                <select name="sekolah_id"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Sekolah</option>
                    @foreach($sekolahList as $s)
                    <option value="{{ $s->id }}" {{ request('sekolah_id') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select name="status"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua</option>
                    <option value="lulus" {{ request('status') === 'lulus' ? 'selected' : '' }}>Lulus</option>
                    <option value="tidak_lulus" {{ request('status') === 'tidak_lulus' ? 'selected' : '' }}>Tidak Lulus</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="btn-primary">
                Tampilkan
            </button>
            @if(request()->hasAny(['paket_id', 'sekolah_id', 'status']))
            <a href="{{ route('dinas.laporan') }}"
               class="btn-secondary">Reset</a>
            @endif
            {{-- Export --}}
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('dinas.laporan.export', request()->query()) }}"
                   class="btn-success inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export Excel (.xlsx)
                </a>
            </div>
        </div>
    </form>

    {{-- Rekap Statistik --}}
    @if(isset($rekap))
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $rekap['total_peserta'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total Peserta</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $rekap['sudah_ujian'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sudah Ujian</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $rekap['lulus'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Lulus</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $rekap['tidak_lulus'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Tidak Lulus</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $rekap['rata_rata'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Rata-rata</p>
        </div>
    </div>
    @endif

    {{-- Tabel Hasil --}}
    @if(isset($data) && is_object($data) && $data->count() > 0)
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center">Skor</th>
                        <th class="px-5 py-3 text-center">Nilai</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($data as $hasil)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $data->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $hasil->peserta->nama ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $hasil->peserta->nis ?? $hasil->peserta->nisn ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-3 hidden lg:table-cell text-gray-600 text-xs">{{ $hasil->peserta->sekolah->nama ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell text-gray-600">{{ $hasil->peserta->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center font-bold text-gray-900">{{ $hasil->nilai_akhir ?? '-' }}</td>
                        <td class="px-5 py-3 text-center">
                            @php $nilai = $hasil->nilai_akhir ?? 0; @endphp
                            <span class="font-bold {{ $nilai >= 80 ? 'text-green-600' : ($nilai >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ $nilai }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($hasil->status === 'dinilai')
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Dinilai</span>
                            @elseif($hasil->status === 'submit')
                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Submit</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ ucfirst($hasil->status) }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($data->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $data->withQueryString()->links() }}
        </div>
        @endif
    </div>
    @elseif(request()->hasAny(['paket_id', 'sekolah_id', 'status']))
    <div class="card text-center py-12 text-gray-400">Tidak ada data yang sesuai filter.</div>
    @else
    <div class="card text-center py-12 text-gray-400">
        Belum ada peserta yang menyelesaikan ujian.
    </div>
    @endif

</div>
@endsection
