@extends('layouts.admin')

@section('title', 'Bank Soal Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Bank Soal</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Bank Soal</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('sekolah.soal.import') }}"
               class="flex items-center gap-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import
            </a>
            <a href="{{ route('sekolah.soal.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Soal
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('sekolah.soal.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari soal..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="jenis"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Jenis</option>
            <option value="pilihan_ganda" {{ request('jenis') === 'pilihan_ganda' ? 'selected' : '' }}>Pilihan Ganda</option>
            <option value="pilihan_ganda_kompleks" {{ request('jenis') === 'pilihan_ganda_kompleks' ? 'selected' : '' }}>PG Kompleks</option>
            <option value="isian" {{ request('jenis') === 'isian' ? 'selected' : '' }}>Isian</option>
            <option value="essay" {{ request('jenis') === 'essay' ? 'selected' : '' }}>Essay</option>
            <option value="menjodohkan" {{ request('jenis') === 'menjodohkan' ? 'selected' : '' }}>Menjodohkan</option>
        </select>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Cari</button>
        @if(request()->hasAny(['q', 'jenis']))
        <a href="{{ route('sekolah.soal.index') }}"
           class="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg text-center">Reset</a>
        @endif
    </form>

    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3.5 border-b border-gray-100 text-sm text-gray-500">
            {{ $soals->total() }} soal ditemukan
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Pertanyaan</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Jenis</th>
                        <th class="px-5 py-3 text-center hidden md:table-cell">Bobot</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($soals as $soal)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $soals->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3 max-w-xs">
                            <p class="text-gray-900 line-clamp-2">{{ strip_tags($soal->pertanyaan) }}</p>
                        </td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                            @php
                                $jenisLabel = [
                                    'pilihan_ganda' => ['PG', 'blue'],
                                    'pilihan_ganda_kompleks' => ['PGK', 'purple'],
                                    'isian' => ['Isian', 'green'],
                                    'essay' => ['Essay', 'amber'],
                                    'menjodohkan' => ['Jodoh', 'pink'],
                                ];
                                [$label, $color] = $jenisLabel[$soal->tipe_soal] ?? [$soal->tipe_soal, 'gray'];
                            @endphp
                            <span class="text-xs font-semibold bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 rounded-full">{{ $label }}</span>
                        </td>
                        <td class="px-5 py-3 text-center hidden md:table-cell font-medium text-gray-700">{{ $soal->bobot }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('sekolah.soal.edit', $soal->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('sekolah.soal.destroy', $soal->id) }}" method="POST"
                                      onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                            Belum ada soal. <a href="{{ route('sekolah.soal.create') }}" class="text-blue-600 hover:underline">Tambah soal baru</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($soals->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $soals->withQueryString()->links() }}</div>
        @endif
    </div>

</div>
@endsection
