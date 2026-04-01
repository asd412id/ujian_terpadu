@extends('layouts.admin')

@section('title', 'Penugasan: ' . $user->name)

@section('breadcrumb')
    <a href="{{ route('dinas.penugasan.index') }}" class="text-gray-500 hover:text-blue-600">Penugasan Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ $user->name }}</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="penugasanApp()">

    {{-- User info header --}}
    <div class="card">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-teal-700 text-lg font-bold">{{ substr($user->name, 0, 1) }}</span>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ $user->name }}</h1>
                <p class="text-sm text-gray-500">{{ $user->email }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Section 1: Kategori Assignment --}}
    <div class="card">
        <form action="{{ route('dinas.penugasan.update-kategori', $user->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Penugasan per Kategori
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">Centang kategori → semua soal di kategori tersebut akan bisa diakses pembuat soal ini</p>
                </div>
            </div>

            @if($allKategori->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">Belum ada kategori soal yang aktif.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    @foreach($allKategori as $kat)
                    <label class="relative flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all
                        {{ in_array($kat->id, $assignedKategoriIds) ? 'border-blue-300 bg-blue-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                        <input type="checkbox" name="kategori_ids[]" value="{{ $kat->id }}"
                               {{ in_array($kat->id, $assignedKategoriIds) ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $kat->nama }}</p>
                            <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                @if($kat->kode)
                                <span class="text-xs text-gray-500 font-mono">{{ $kat->kode }}</span>
                                @endif
                                @if($kat->jenjang)
                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold">{{ $kat->jenjang }}</span>
                                @endif
                                @if($kat->kelompok)
                                <span class="text-xs text-gray-500">{{ $kat->kelompok }}</span>
                                @endif
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                    <button type="submit" class="btn-primary inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Penugasan Kategori
                    </button>
                    <span class="text-xs text-gray-400" x-text="'{{ count($assignedKategoriIds) }} kategori ditugaskan'"></span>
                </div>
            @endif
        </form>
    </div>

    {{-- Section 2: Individual Soal Assignment --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Penugasan Soal Individual
                </h2>
                <p class="text-xs text-gray-500 mt-1">Tugaskan soal tertentu secara individual di luar penugasan kategori</p>
            </div>
            <button type="button" @click="showSearchModal = true"
                    class="btn-primary inline-flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Soal
            </button>
        </div>

        @php
            $tipeLabel = [
                'pg' => ['Pilihan Ganda', 'blue'], 'pilihan_ganda' => ['Pilihan Ganda', 'blue'],
                'pg_kompleks' => ['PG Kompleks', 'purple'], 'pilihan_ganda_kompleks' => ['PG Kompleks', 'purple'],
                'benar_salah' => ['Benar / Salah', 'indigo'],
                'isian' => ['Isian Singkat', 'green'], 'essay' => ['Essay', 'amber'],
                'menjodohkan' => ['Menjodohkan', 'pink'],
            ];
        @endphp

        @if($assignments['soal']->isEmpty())
            <x-empty-state icon="document" title="Belum ada soal" subtitle="Pembuat soal belum menambahkan soal untuk penugasan ini." />
        @else
            <div class="overflow-x-auto -mx-4 sm:-mx-6">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 sm:px-5 py-2.5 text-left">Soal</th>
                            <th class="px-4 sm:px-5 py-2.5 text-left hidden sm:table-cell">Kategori</th>
                            <th class="px-4 sm:px-5 py-2.5 text-center hidden sm:table-cell">Tipe</th>
                            <th class="px-4 sm:px-5 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($assignments['soal'] as $soal)
                        @php [$tLabel, $tColor] = $tipeLabel[$soal->tipe_soal] ?? [$soal->tipe_soal, 'gray']; @endphp
                        <tr class="hover:bg-gray-50" id="soal-row-{{ $soal->id }}">
                            <td class="px-4 sm:px-5 py-3">
                                <p class="text-gray-900 text-sm line-clamp-2">{{ \App\Support\HtmlDisplay::plainText($soal->pertanyaan, 150) }}</p>
                                <div class="flex items-center gap-2 mt-1 sm:hidden">
                                    <span class="text-xs text-gray-500">{{ $soal->kategori->nama ?? '—' }}</span>
                                    <span class="text-xs font-semibold bg-{{ $tColor }}-100 text-{{ $tColor }}-700 px-1.5 py-0.5 rounded-full">{{ $tLabel }}</span>
                                </div>
                            </td>
                            <td class="px-4 sm:px-5 py-3 hidden sm:table-cell text-gray-600 text-xs">{{ $soal->kategori->nama ?? '—' }}</td>
                            <td class="px-4 sm:px-5 py-3 text-center hidden sm:table-cell">
                                <span class="text-xs font-semibold bg-{{ $tColor }}-100 text-{{ $tColor }}-700 px-2 py-0.5 rounded-full">{{ $tLabel }}</span>
                            </td>
                            <td class="px-4 sm:px-5 py-3 text-right">
                                <button type="button" @click="removeSoal('{{ $soal->id }}')"
                                        class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Back button --}}
    <div>
        <a href="{{ route('dinas.penugasan.index') }}" class="btn-secondary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
    </div>

    {{-- Modal: Search & Add Soal --}}
    <div x-show="showSearchModal" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-20 bg-black/50"
         @mousedown.self="showSearchModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[70vh] flex flex-col" @click.stop>
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                <h3 class="font-semibold text-gray-900">Cari & Tambah Soal</h3>
                <button @click="showSearchModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-3 border-b border-gray-100 flex flex-col sm:flex-row gap-2 flex-shrink-0">
                <input type="text" x-model="searchQuery" @input.debounce.400ms="searchSoal()"
                       placeholder="Cari pertanyaan..."
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select x-model="searchKategori" @change="searchSoal()"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Kategori</option>
                    @foreach($allKategori as $kat)
                    <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-3">
                <template x-if="searchLoading">
                    <div class="py-8 text-center text-gray-400">
                        <svg class="animate-spin h-6 w-6 mx-auto text-blue-500 mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Mencari...
                    </div>
                </template>
                <template x-if="!searchLoading && searchResults.length === 0">
                    <div class="py-8 text-center text-gray-400 text-sm">
                        <template x-if="searchQuery || searchKategori">
                            <span>Tidak ada soal ditemukan.</span>
                        </template>
                        <template x-if="!searchQuery && !searchKategori">
                            <span>Ketik untuk mencari soal...</span>
                        </template>
                    </div>
                </template>
                <div class="space-y-2">
                    <template x-for="soal in searchResults" :key="soal.id">
                        <div class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 line-clamp-2" x-text="soal.pertanyaan_plain"></p>
                                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                    <span class="text-xs text-gray-500" x-text="soal.kategori_nama"></span>
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full"
                                          :class="tipeClass(soal.tipe_soal)"
                                          x-text="soal.tipe_soal_label"></span>
                                    <span class="text-xs text-gray-400" x-show="soal.pembuat_nama" x-text="'oleh ' + soal.pembuat_nama"></span>
                                </div>
                            </div>
                            <button type="button" @click="addSoal(soal.id)"
                                    class="flex-shrink-0 btn-primary text-xs px-3 py-1.5"
                                    :disabled="addedSoalIds.includes(soal.id)"
                                    x-text="addedSoalIds.includes(soal.id) ? 'Ditambahkan' : 'Tambah'">
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function penugasanApp() {
    return {
        showSearchModal: false,
        searchQuery: '',
        searchKategori: '',
        searchResults: [],
        searchLoading: false,
        addedSoalIds: @json($assignments['soal']->pluck('id')),

        tipeClass(tipe) {
            const map = {
                'pg': 'bg-blue-100 text-blue-700', 'pilihan_ganda': 'bg-blue-100 text-blue-700',
                'pg_kompleks': 'bg-purple-100 text-purple-700', 'pilihan_ganda_kompleks': 'bg-purple-100 text-purple-700',
                'benar_salah': 'bg-indigo-100 text-indigo-700',
                'isian': 'bg-green-100 text-green-700', 'essay': 'bg-amber-100 text-amber-700',
                'menjodohkan': 'bg-pink-100 text-pink-700',
            };
            return map[tipe] || 'bg-gray-100 text-gray-600';
        },

        async searchSoal() {
            if (!this.searchQuery && !this.searchKategori) {
                this.searchResults = [];
                return;
            }
            this.searchLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery) params.append('search', this.searchQuery);
                if (this.searchKategori) params.append('kategori_id', this.searchKategori);

                const res = await fetch(`{{ route('dinas.penugasan.api.search-soal') }}?${params}`);
                const json = await res.json();
                this.searchResults = json.data || [];
            } catch (e) {
                console.error(e);
            } finally {
                this.searchLoading = false;
            }
        },

        async addSoal(soalId) {
            try {
                const res = await fetch(`{{ url('/dinas/penugasan') }}/{{ $user->id }}/soal/add`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ soal_ids: [soalId] }),
                });
                if (res.ok) {
                    this.addedSoalIds.push(soalId);
                }
            } catch (e) {
                console.error(e);
            }
        },

        async removeSoal(soalId) {
            if (!await $store.confirmModal.open({
                title: 'Hapus Penugasan',
                message: 'Hapus penugasan soal ini dari pembuat soal?',
                confirmText: 'Ya, Hapus',
                danger: true
            })) return;

            try {
                const res = await fetch(`{{ url('/dinas/penugasan') }}/{{ $user->id }}/soal/remove`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ soal_ids: [soalId] }),
                });
                if (res.ok) {
                    const row = document.getElementById('soal-row-' + soalId);
                    if (row) row.remove();
                    this.addedSoalIds = this.addedSoalIds.filter(id => id !== soalId);
                }
            } catch (e) {
                console.error(e);
            }
        },
    };
}
</script>
@endsection
