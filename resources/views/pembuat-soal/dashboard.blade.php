@extends('layouts.admin')

@section('title', 'Dashboard Pembuat Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Dashboard</span>
@endsection

@section('page-content')
<div class="space-y-5">

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Soal --}}
        <div class="card flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $totalSoal }}</p>
                <p class="text-xs text-gray-500">Total Soal</p>
            </div>
        </div>

        {{-- Terverifikasi --}}
        <div class="card flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-600">{{ $soalVerified }}</p>
                <p class="text-xs text-gray-500">Terverifikasi</p>
            </div>
        </div>

        {{-- Menunggu Verifikasi --}}
        <div class="card flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-amber-600">{{ $soalPending }}</p>
                <p class="text-xs text-gray-500">Menunggu Verifikasi</p>
            </div>
        </div>

        {{-- Total Kategori --}}
        <div class="card flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $perKategori->count() }}</p>
                <p class="text-xs text-gray-500">Total Kategori</p>
            </div>
        </div>
    </div>

    {{-- Per Kategori & Per Tipe --}}
    <div class="grid lg:grid-cols-2 gap-5">

        {{-- Soal per Kategori --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Soal per Kategori</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-2.5 text-left">Kategori</th>
                        <th class="px-5 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($perKategori as $nama => $total)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-gray-700">{{ $nama }}</td>
                        <td class="px-5 py-2.5 text-right font-medium text-gray-900">{{ $total }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-5 py-6 text-center text-gray-400">Belum ada data.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Soal per Tipe --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Soal per Tipe</h2>
            </div>
            @php
                $tipeNames = [
                    'pg' => 'Pilihan Ganda',
                    'pilihan_ganda' => 'Pilihan Ganda',
                    'pg_kompleks' => 'PG Kompleks',
                    'pilihan_ganda_kompleks' => 'PG Kompleks',
                    'menjodohkan' => 'Menjodohkan',
                    'isian' => 'Isian',
                    'essay' => 'Essay',
                    'benar_salah' => 'Benar / Salah',
                ];
            @endphp
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-2.5 text-left">Tipe Soal</th>
                        <th class="px-5 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($perTipe as $tipe => $total)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-gray-700">{{ $tipeNames[$tipe] ?? ucfirst($tipe) }}</td>
                        <td class="px-5 py-2.5 text-right font-medium text-gray-900">{{ $total }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-5 py-6 text-center text-gray-400">Belum ada data.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Soal --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Soal Terbaru</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-2.5 text-left">Pertanyaan</th>
                        <th class="px-5 py-2.5 text-left hidden md:table-cell">Kategori</th>
                        <th class="px-5 py-2.5 text-center hidden sm:table-cell">Tipe</th>
                        <th class="px-5 py-2.5 text-center">Status</th>
                        <th class="px-5 py-2.5 text-right hidden sm:table-cell">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentSoal as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 max-w-xs">
                            <p class="text-gray-900 line-clamp-1">{{ Str::limit(strip_tags($item->pertanyaan), 80) }}</p>
                        </td>
                        <td class="px-5 py-2.5 hidden md:table-cell text-gray-600 text-xs">
                            {{ $item->kategori->nama ?? '—' }}
                        </td>
                        <td class="px-5 py-2.5 text-center hidden sm:table-cell">
                            @php
                                $tipeLabel = [
                                    'pg' => ['PG', 'blue'], 'pilihan_ganda' => ['PG', 'blue'],
                                    'pg_kompleks' => ['PGK', 'purple'], 'pilihan_ganda_kompleks' => ['PGK', 'purple'],
                                    'benar_salah' => ['B/S', 'indigo'],
                                    'isian' => ['Isian', 'green'],
                                    'essay' => ['Essay', 'amber'],
                                    'menjodohkan' => ['Jodoh', 'pink'],
                                ];
                                [$label, $color] = $tipeLabel[$item->tipe_soal] ?? [$item->tipe_soal, 'gray'];
                            @endphp
                            <span class="text-xs font-semibold bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 rounded-full">
                                {{ $label }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5 text-center">
                            @if($item->is_verified)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Terverifikasi</span>
                            @else
                                <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Menunggu</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-right hidden sm:table-cell text-gray-500 text-xs">
                            {{ $item->created_at->format('d M Y') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-gray-400">
                            Belum ada soal.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
