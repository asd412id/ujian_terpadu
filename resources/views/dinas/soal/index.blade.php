@extends('layouts.admin')

@section('title', 'Bank Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Bank Soal</span>
@endsection

@section('page-content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Bank Soal</h1>
        <a href="{{ route('dinas.soal.create') }}"
           class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Soal
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.soal.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari soal..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="kategori"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kategori</option>
            @foreach($kategori as $kat)
            <option value="{{ $kat->id }}" {{ request('kategori') == $kat->id ? 'selected' : '' }}>
                {{ $kat->nama }}
            </option>
            @endforeach
        </select>
        <select name="tipe"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Tipe</option>
            <option value="pilihan_ganda" {{ request('tipe') === 'pilihan_ganda' ? 'selected' : '' }}>Pilihan Ganda</option>
            <option value="pilihan_ganda_kompleks" {{ request('tipe') === 'pilihan_ganda_kompleks' ? 'selected' : '' }}>PG Kompleks</option>
            <option value="isian" {{ request('tipe') === 'isian' ? 'selected' : '' }}>Isian</option>
            <option value="essay" {{ request('tipe') === 'essay' ? 'selected' : '' }}>Essay</option>
            <option value="menjodohkan" {{ request('tipe') === 'menjodohkan' ? 'selected' : '' }}>Menjodohkan</option>
        </select>
        <select name="kesulitan"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kesulitan</option>
            <option value="mudah" {{ request('kesulitan') === 'mudah' ? 'selected' : '' }}>Mudah</option>
            <option value="sedang" {{ request('kesulitan') === 'sedang' ? 'selected' : '' }}>Sedang</option>
            <option value="sulit" {{ request('kesulitan') === 'sulit' ? 'selected' : '' }}>Sulit</option>
        </select>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Cari
        </button>
        @if(request()->hasAny(['search', 'kategori', 'tipe', 'kesulitan']))
        <a href="{{ route('dinas.soal.index') }}"
           class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors text-center">
            Reset
        </a>
        @endif
    </form>

    {{-- Stats --}}
    <div class="flex flex-wrap gap-2">
        <span class="text-sm text-gray-500 flex items-center">
            {{ $soal->total() }} soal ditemukan
        </span>
    </div>

    {{-- Tabel --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left w-8">#</th>
                        <th class="px-5 py-3 text-left">Pertanyaan</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Kategori</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Jenis</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Tingkat</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Bobot</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($soal as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $soal->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3 max-w-xs">
                            <p class="text-gray-900 line-clamp-2">{{ strip_tags($item->pertanyaan) }}</p>
                            @if($item->gambar_soal)
                            <span class="text-xs text-blue-500 mt-0.5 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Ada gambar
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 hidden lg:table-cell text-gray-600 text-xs">
                            {{ $item->kategori->nama ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                             @php
                                $tipeLabel = [
                                    'pg' => ['PG', 'blue'], 'pilihan_ganda' => ['PG', 'blue'],
                                    'pg_kompleks' => ['PGK', 'purple'], 'pilihan_ganda_kompleks' => ['PGK', 'purple'],
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
                        <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600">{{ ucfirst($item->tingkat_kesulitan ?? '—') }}</td>
                        <td class="px-5 py-3 text-center hidden md:table-cell font-medium text-gray-900">{{ $item->bobot }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('dinas.soal.edit', $item->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('dinas.soal.destroy', $item->id) }}" method="POST"
                                      onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Belum ada soal. <a href="{{ route('dinas.soal.create') }}" class="text-blue-600 hover:underline">Tambah soal baru</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($soal->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $soal->withQueryString()->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
