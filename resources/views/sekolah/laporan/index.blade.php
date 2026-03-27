@extends('layouts.admin')

@section('title', 'Laporan Nilai')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Laporan Nilai</span>
@endsection

@section('page-content')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-bold text-gray-900">Laporan Nilai</h1>
        <p class="text-sm text-gray-500 mt-0.5">Rekap dan unduh hasil ujian untuk sekolah ini.</p>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('sekolah.laporan') }}" class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Filter Laporan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select name="status"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua</option>
                    <option value="sangat_baik" {{ request('status') === 'sangat_baik' ? 'selected' : '' }}>Sangat Baik</option>
                    <option value="baik" {{ request('status') === 'baik' ? 'selected' : '' }}>Baik</option>
                    <option value="cukup" {{ request('status') === 'cukup' ? 'selected' : '' }}>Cukup</option>
                    <option value="kurang" {{ request('status') === 'kurang' ? 'selected' : '' }}>Kurang</option>
                    <option value="sangat_kurang" {{ request('status') === 'sangat_kurang' ? 'selected' : '' }}>Sangat Kurang</option>
                </select>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <button type="submit" class="btn-primary">
                Tampilkan
            </button>
            @if(request()->hasAny(['paket_id', 'status']))
            <a href="{{ route('sekolah.laporan') }}" class="btn-secondary">Reset</a>
            @endif
            <div class="sm:ml-auto"></div>
        </div>
    </form>

    {{-- Rekap Statistik --}}
    @if(isset($rekap))
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $rekap['sangat_baik'] ?? 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">Sangat Baik</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $rekap['baik'] ?? 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">Baik</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $rekap['cukup'] ?? 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">Cukup</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-orange-600">{{ $rekap['kurang'] ?? 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">Kurang</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-red-600">{{ $rekap['sangat_kurang'] ?? 0 }}</p>
            <p class="text-sm text-gray-500 mt-1">Sangat Kurang</p>
        </div>
    </div>
    @endif

    {{-- Tabel Hasil --}}
    @if(isset($data) && is_object($data) && $data->count() > 0)
    <div class="card overflow-hidden p-0">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between px-5 py-3 border-b border-gray-100 gap-2">
            <p class="text-sm text-gray-500">Menampilkan {{ $data->count() }} dari {{ $data->total() }} data</p>
        </div>
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Paket / Sesi</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Benar</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Salah</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Kosong</th>
                        <th class="px-5 py-3 text-center">Nilai</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($data as $hasil)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $data->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $hasil->peserta->nama }}</p>
                            <p class="text-xs text-gray-400">{{ $hasil->peserta->nis ?? $hasil->peserta->nisn }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell">
                            <p class="font-medium text-gray-900 text-xs">{{ $hasil->sesi->paket->nama ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $hasil->sesi->nama_sesi ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600">{{ $hasil->peserta->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center font-bold text-green-600 hidden md:table-cell">{{ $hasil->jumlah_benar ?? 0 }}</td>
                        <td class="px-5 py-3 text-center font-bold text-red-500 hidden md:table-cell">{{ $hasil->jumlah_salah ?? 0 }}</td>
                        <td class="px-5 py-3 text-center text-gray-400 hidden lg:table-cell">{{ $hasil->jumlah_kosong ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @php $nilai = $hasil->nilai_akhir ?? 0; @endphp
                            <span class="font-bold {{ \App\Support\NilaiStatus::textClass($nilai) }}">
                                {{ $nilai }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($hasil->nilai_akhir !== null)
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ \App\Support\NilaiStatus::badgeClass($hasil->nilai_akhir) }}">{{ \App\Support\NilaiStatus::label($hasil->nilai_akhir) }}</span>
                            @elseif(in_array($hasil->status, ['submit', 'dinilai']))
                                <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Menghitung...</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ ucfirst(str_replace('_', ' ', $hasil->status)) }}</span>
                            @endif
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach($data as $hasil)
            @php $nilai = $hasil->nilai_akhir ?? 0; @endphp
            <div class="block px-4 py-3 hover:bg-gray-50">
                <div class="flex items-start justify-between gap-2 mb-1.5">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 text-sm">{{ $hasil->peserta->nama }}</p>
                        <p class="text-xs text-gray-500">{{ $hasil->peserta->nis ?? $hasil->peserta->nisn }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $hasil->sesi->paket->nama ?? '' }}{{ $hasil->sesi->nama_sesi ? ' · ' . $hasil->sesi->nama_sesi : '' }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-lg font-bold {{ \App\Support\NilaiStatus::textClass($nilai) }}">{{ $nilai }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs text-gray-500">{{ $hasil->peserta->kelas ?? '—' }}</span>
                    @if($hasil->nilai_akhir !== null)
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ \App\Support\NilaiStatus::badgeClass($hasil->nilai_akhir) }}">{{ \App\Support\NilaiStatus::label($hasil->nilai_akhir) }}</span>
                    @elseif(in_array($hasil->status, ['submit', 'dinilai']))
                        <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Menghitung...</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ ucfirst(str_replace('_', ' ', $hasil->status)) }}</span>
                    @endif
                    <span class="text-xs text-gray-400 ml-auto">B:{{ $hasil->jumlah_benar ?? 0 }} S:{{ $hasil->jumlah_salah ?? 0 }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @if($data->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $data->withQueryString()->links() }}
        </div>
        @endif
    </div>
    @elseif(request()->hasAny(['paket_id', 'status']))
    <div class="card text-center py-12 text-gray-400">Tidak ada data yang sesuai filter.</div>
    @else
    <div class="card text-center py-12 text-gray-400">
        Belum ada peserta yang menyelesaikan ujian.
    </div>
    @endif

</div>
@endsection
