@extends('layouts.admin')

@section('title', 'Analisis Soal - ' . $paket->nama)

@section('breadcrumb')
    <a href="{{ route('dinas.laporan') }}" class="text-gray-500 hover:text-blue-600">Laporan</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Analisis Soal</span>
@endsection

@section('page-content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Analisis Kualitas Soal</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $paket->nama }} · {{ $summary['total_peserta'] }} peserta</p>
        </div>
        <a href="{{ route('dinas.laporan') }}" class="btn-secondary">Kembali ke Laporan</a>
    </div>

    @if(empty($analisis))
    <div class="card text-center py-12 text-gray-400">Belum ada peserta yang menyelesaikan ujian pada paket ini.</div>
    @else

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $summary['total_soal'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Soal</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $summary['total_peserta'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Peserta</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $summary['soal_mudah'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Soal Mudah</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $summary['soal_sedang'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Soal Sedang</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $summary['soal_sulit'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Soal Sulit</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $summary['daya_beda_baik'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Daya Beda Baik</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $summary['daya_beda_buruk'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Daya Beda Buruk</p>
        </div>
    </div>

    {{-- Info --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
        <strong>Keterangan:</strong>
        <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
            <li><strong>Tingkat Kesulitan (TK)</strong>: proporsi peserta yang menjawab benar. 0.0–0.3 = Sulit, 0.3–0.7 = Sedang, 0.7–1.0 = Mudah.</li>
            <li><strong>Daya Beda (DB)</strong>: kemampuan soal membedakan peserta pandai dan kurang. ≥0.4 = Sangat Baik, 0.3–0.4 = Baik, 0.2–0.3 = Cukup, &lt;0.2 = Buruk.</li>
            <li>Soal yang baik memiliki TK 0.3–0.7 (Sedang) dan DB ≥ 0.3 (Baik/Sangat Baik).</li>
        </ul>
    </div>

    {{-- Analisis Table --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-center">No</th>
                        <th class="px-4 py-3 text-left">Soal</th>
                        <th class="px-4 py-3 text-center hidden sm:table-cell">Tipe</th>
                        <th class="px-4 py-3 text-center hidden md:table-cell">% Benar</th>
                        <th class="px-4 py-3 text-center hidden md:table-cell">% Salah</th>
                        <th class="px-4 py-3 text-center hidden lg:table-cell">% Kosong</th>
                        <th class="px-4 py-3 text-center">TK</th>
                        <th class="px-4 py-3 text-center">Kesulitan</th>
                        <th class="px-4 py-3 text-center">DB</th>
                        <th class="px-4 py-3 text-center">Daya Beda</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($analisis as $item)
                    <tr class="hover:bg-gray-50" x-data="{ open: false }">
                        <td class="px-4 py-3 text-center text-gray-400 text-xs">{{ $item['nomor'] }}</td>
                        <td class="px-4 py-3">
                            <button @click="open = !open" class="text-left text-gray-900 text-xs hover:text-blue-600 max-w-xs truncate block">
                                {{ $item['pertanyaan'] ?: '(tanpa teks)' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center hidden sm:table-cell">
                            @php
                                $tipeLabel = match($item['tipe']) {
                                    'pilihan_ganda' => 'PG',
                                    'pilihan_ganda_kompleks' => 'PGK',
                                    'menjodohkan' => 'Jodoh',
                                    'benar_salah' => 'BS',
                                    'isian' => 'Isian',
                                    'essay' => 'Essay',
                                    default => $item['tipe'],
                                };
                            @endphp
                            <span class="text-xs font-medium bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tipeLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-green-600 font-medium hidden md:table-cell">{{ $item['pct_benar'] }}%</td>
                        <td class="px-4 py-3 text-center text-red-500 font-medium hidden md:table-cell">{{ $item['pct_salah'] }}%</td>
                        <td class="px-4 py-3 text-center text-gray-400 hidden lg:table-cell">{{ $item['pct_kosong'] }}%</td>
                        <td class="px-4 py-3 text-center font-bold text-gray-900">{{ $item['tingkat_kesulitan'] !== null ? number_format($item['tingkat_kesulitan'], 2) : '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $kesColor = match($item['kategori_kesulitan']) {
                                    'Mudah' => 'green',
                                    'Sedang' => 'amber',
                                    'Sulit' => 'red',
                                    default => 'gray',
                                };
                            @endphp
                            <span class="text-xs font-semibold bg-{{ $kesColor }}-100 text-{{ $kesColor }}-700 px-2 py-0.5 rounded-full">{{ $item['kategori_kesulitan'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-gray-900">{{ $item['daya_beda'] !== null ? number_format($item['daya_beda'], 2) : '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $dbColor = match($item['kategori_daya_beda']) {
                                    'Sangat Baik' => 'green',
                                    'Baik' => 'blue',
                                    'Cukup' => 'amber',
                                    'Buruk' => 'red',
                                    default => 'gray',
                                };
                            @endphp
                            <span class="text-xs font-semibold bg-{{ $dbColor }}-100 text-{{ $dbColor }}-700 px-2 py-0.5 rounded-full">{{ $item['kategori_daya_beda'] }}</span>
                        </td>
                    </tr>
                    @if(!empty($item['distractors']))
                    <tr x-show="open" x-transition class="bg-gray-50">
                        <td colspan="10" class="px-6 py-3">
                            <p class="text-xs font-semibold text-gray-600 mb-2">Distribusi Pilihan Opsi:</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach($item['distractors'] as $d)
                                <div class="flex items-center gap-2 text-xs {{ $d['is_benar'] ? 'text-green-700 font-bold' : 'text-gray-600' }}">
                                    <span class="w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold {{ $d['is_benar'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $d['label'] }}</span>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="{{ $d['is_benar'] ? 'bg-green-500' : 'bg-gray-400' }} h-2 rounded-full" style="width: {{ min($d['pct'], 100) }}%"></div>
                                    </div>
                                    <span>{{ $d['pct'] }}% ({{ $d['dipilih'] }})</span>
                                    @if($d['teks'])
                                    <span class="text-gray-400 truncate max-w-[100px]">{{ $d['teks'] }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>
@endsection
