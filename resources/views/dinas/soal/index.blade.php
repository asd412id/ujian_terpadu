@extends('layouts.admin')

@section('title', 'Bank Soal')

@push('head')
@endpush

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Bank Soal</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="soalIndex()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Bank Soal</h1>
        <div class="flex items-center gap-2">
            @if($soal->total() > 0)
            <div x-data="{ openDel: false }" class="relative">
                <button @click="openDel = !openDel" type="button"
                        class="btn-danger-outline inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Hapus Soal
                    <svg class="w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openDel" x-cloak @click.outside="openDel = false" x-transition
                     class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-200 z-50 py-2">
                    {{-- Hapus semua --}}
                    <form action="{{ route('dinas.soal.destroy-all') }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Semua Soal',message:'Yakin ingin menghapus SEMUA soal? Tindakan ini tidak dapat dibatalkan.',confirmText:'Ya, Hapus Semua',danger:true})) { openDel=false; $el.submit() }">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-semibold">
                            Hapus Semua Soal
                        </button>
                    </form>
                    <div class="border-t border-gray-100 my-1"></div>
                    <p class="px-4 py-1 text-xs text-gray-400 uppercase tracking-wider">Per Kategori</p>
                    @foreach($kategori as $kat)
                    <form action="{{ route('dinas.soal.destroy-all') }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Soal Kategori',message:'Yakin ingin menghapus semua soal kategori &quot;{{ e($kat->nama) }}&quot;?',confirmText:'Ya, Hapus',danger:true})) { openDel=false; $el.submit() }">
                        @csrf @method('DELETE')
                        <input type="hidden" name="kategori" value="{{ $kat->id }}">
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                            {{ $kat->nama }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @endif
            @if($soal->total() > 0)
            <a href="{{ route('dinas.soal.preview-all', request()->only('kategori')) }}"
               class="btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview Semua
            </a>
            @endif
            <a href="{{ route('dinas.soal.import') }}"
               class="btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Soal
            </a>
            <a href="{{ route('dinas.soal.create') }}"
               class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Soal
            </a>
        </div>
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
            <option value="benar_salah" {{ request('tipe') === 'benar_salah' ? 'selected' : '' }}>Benar / Salah</option>
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
                class="btn-primary">
            Cari
        </button>
        @if(request()->hasAny(['search', 'kategori', 'tipe', 'kesulitan']))
        <a href="{{ route('dinas.soal.index') }}"
           class="btn-secondary text-center">
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
                            @if($item->narasi_id)
                            <span class="text-xs text-indigo-500 mt-0.5 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Bernarasi
                            </span>
                            @endif
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
                        <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600">{{ ucfirst($item->tingkat_kesulitan ?? '—') }}</td>
                        <td class="px-5 py-3 text-center hidden md:table-cell font-medium text-gray-900">{{ $item->bobot }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('dinas.soal.show', $item->id) }}"
                                   class="text-gray-500 hover:text-gray-700 text-xs font-medium"
                                   @click.prevent="openPreview('{{ $item->id }}')">Preview</a>
                                <a href="{{ route('dinas.soal.edit', $item->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('dinas.soal.destroy', $item->id) }}" method="POST"
                                      x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Soal',message:'Hapus soal ini?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
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

    {{-- Preview Modal --}}
    <template x-teleport="body">
    <div x-show="showPreview" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showPreview = false">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" @click="showPreview = false" x-show="showPreview"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        {{-- Modal Panel --}}
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col"
             x-show="showPreview"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-900">Preview Soal</h2>
                <div class="flex items-center gap-2">
                    <a :href="`{{ url('dinas/soal') }}/${previewData?.id}/edit`"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-800"
                       x-show="previewData">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                {{-- Loading --}}
                <div x-show="previewLoading" class="flex items-center justify-center py-12">
                    <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>

                <template x-if="previewData && !previewLoading">
                    <div class="space-y-5">
                        {{-- Meta --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 rounded-xl p-4">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Jenis</p>
                                <span class="inline-block mt-1 text-xs font-semibold px-2.5 py-1 rounded-full"
                                      :class="tipeClass(previewData.tipe_soal)" x-text="tipeLabel(previewData.tipe_soal)"></span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Kategori</p>
                                <p class="mt-1 text-sm font-medium text-gray-900" x-text="previewData.kategori"></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Kesulitan</p>
                                <p class="mt-1 text-sm font-medium text-gray-900" x-text="previewData.tingkat_kesulitan"></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Bobot</p>
                                <p class="mt-1 text-sm font-medium text-gray-900" x-text="previewData.bobot"></p>
                            </div>
                        </div>

                        {{-- Pertanyaan --}}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Pertanyaan</h3>
                            <div class="text-sm text-gray-800 ck-content mathjax-process" x-safe-html="previewData.pertanyaan"></div>
                            <template x-if="previewData.gambar_soal">
                                <img :src="previewData.gambar_soal" class="mt-3 max-h-64 rounded-lg border">
                            </template>
                        </div>

                        {{-- Opsi PG / PGK --}}
                        <template x-if="['pg','pilihan_ganda','pg_kompleks','pilihan_ganda_kompleks'].includes(previewData.tipe_soal)">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Pilihan Jawaban</h3>
                                <template x-if="['pg_kompleks','pilihan_ganda_kompleks'].includes(previewData.tipe_soal)">
                                    <p class="text-xs text-purple-600 font-medium mb-1">Pilih lebih dari satu jawaban yang benar</p>
                                </template>
                                <div class="space-y-2">
                                    <template x-for="(opsi, opsiIdx) in previewData.opsi" :key="opsi.label">
                                        <div class="flex items-start gap-3 p-3 rounded-lg"
                                             :class="opsi.is_benar ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                                            {{-- PGK: checkbox icon --}}
                                            <template x-if="['pg_kompleks','pilihan_ganda_kompleks'].includes(previewData.tipe_soal) && opsi.is_benar">
                                                <span class="flex-shrink-0 w-7 h-7 rounded flex items-center justify-center bg-green-500">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </span>
                                            </template>
                                            <template x-if="['pg_kompleks','pilihan_ganda_kompleks'].includes(previewData.tipe_soal) && !opsi.is_benar">
                                                <span class="flex-shrink-0 w-7 h-7 rounded border-2 border-gray-300 flex items-center justify-center"></span>
                                            </template>
                                            {{-- PG regular: letter circle --}}
                                            <template x-if="!['pg_kompleks','pilihan_ganda_kompleks'].includes(previewData.tipe_soal)">
                                                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                                                      :class="opsi.is_benar ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'"
                                                      x-text="opsi.label"></span>
                                            </template>
                                            <div class="flex-1">
                                                <p class="text-sm text-gray-800 ck-content mathjax-process" x-show="opsi.teks" x-safe-html="opsi.teks"></p>
                                                <template x-if="opsi.gambar">
                                                    <img :src="opsi.gambar" class="max-h-32 rounded border" :class="opsi.teks ? 'mt-2' : ''">
                                                </template>
                                                <p class="text-sm text-gray-400 italic" x-show="!opsi.teks && !opsi.gambar">—</p>
                                            </div>
                                            <svg x-show="opsi.is_benar" class="w-5 h-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Menjodohkan --}}
                        <template x-if="previewData.tipe_soal === 'menjodohkan'">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Pasangan</h3>
                                <div class="space-y-2">
                                    <template x-for="(p, i) in previewData.pasangan" :key="i">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                            <span class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold" x-text="i + 1"></span>
                                            <div class="flex-1">
                                                <template x-if="p.kiri_gambar"><img :src="p.kiri_gambar" class="max-h-16 rounded border mb-1"></template>
                                                <span class="text-sm text-gray-800" x-text="p.kiri"></span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                            </svg>
                                            <div class="flex-1">
                                                <template x-if="p.kanan_gambar"><img :src="p.kanan_gambar" class="max-h-16 rounded border mb-1"></template>
                                                <span class="text-sm text-gray-800" x-text="p.kanan"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Benar / Salah --}}
                        <template x-if="previewData.tipe_soal === 'benar_salah'">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Pernyataan Benar / Salah</h3>
                                <div class="space-y-2">
                                    <template x-for="(opsi, opsiIdx) in previewData.opsi" :key="opsi.label">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                            <span class="flex-shrink-0 h-7 w-auto min-w-[1.75rem] px-1.5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold" x-text="String.fromCharCode(97 + opsiIdx) + '.'"></span>
                                            <div class="flex-1">
                                                <span class="text-sm text-gray-800 ck-content mathjax-process" x-safe-html="opsi.teks"></span>
                                                <template x-if="opsi.gambar"><img :src="opsi.gambar" class="mt-1 max-h-16 rounded border"></template>
                                            </div>
                                            <span x-show="opsi.is_benar" class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">BENAR</span>
                                            <span x-show="!opsi.is_benar" class="text-xs font-semibold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">SALAH</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Isian / Essay --}}
                        <template x-if="['isian','essay'].includes(previewData.tipe_soal)">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Kunci Jawaban</h3>
                                <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-sm text-gray-800" x-text="previewData.kunci_jawaban || '—'"></p>
                                </div>
                                <template x-if="previewData.pembahasan">
                                    <div class="mt-4">
                                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Pembahasan</h3>
                                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                            <p class="text-sm text-gray-800 whitespace-pre-line" x-text="previewData.pembahasan"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>

</div>

<script>
function soalIndex() {
    return {
        showPreview: false,
        previewLoading: false,
        previewData: null,

        openPreview(id) {
            this.showPreview = true;
            this.previewLoading = true;
            this.previewData = null;

            fetch(`{{ url('dinas/soal') }}/${id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(data => {
                this.previewData = data;
                this.previewLoading = false;
                // Re-typeset MathJax after Alpine renders the preview content
                this.$nextTick(() => {
                    if (window.MathJax && window.MathJax.typesetPromise) {
                        window.MathJax.typesetPromise();
                    }
                });
            })
            .catch(() => {
                this.previewLoading = false;
                this.showPreview = false;
                alert('Gagal memuat preview soal.');
            });
        },

        tipeLabel(tipe) {
            const map = {
                'pg': 'Pilihan Ganda', 'pilihan_ganda': 'Pilihan Ganda',
                'pg_kompleks': 'PG Kompleks', 'pilihan_ganda_kompleks': 'PG Kompleks',
                'benar_salah': 'Benar / Salah',
                'isian': 'Isian Singkat', 'essay': 'Essay', 'menjodohkan': 'Menjodohkan'
            };
            return map[tipe] || tipe;
        },

        tipeClass(tipe) {
            const map = {
                'pg': 'bg-blue-100 text-blue-700', 'pilihan_ganda': 'bg-blue-100 text-blue-700',
                'pg_kompleks': 'bg-purple-100 text-purple-700', 'pilihan_ganda_kompleks': 'bg-purple-100 text-purple-700',
                'benar_salah': 'bg-indigo-100 text-indigo-700',
                'isian': 'bg-green-100 text-green-700', 'essay': 'bg-amber-100 text-amber-700',
                'menjodohkan': 'bg-pink-100 text-pink-700'
            };
            return map[tipe] || 'bg-gray-100 text-gray-700';
        }
    };
}
</script>
@endsection
