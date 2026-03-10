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
        <div class="flex items-center gap-2">
            @if($paket->status === 'draft')
            <form action="{{ route('dinas.paket.publish', $paket->id) }}" method="POST"
                  onsubmit="return confirm('Publikasikan paket ujian ini?')">
                @csrf
                <button type="submit"
                        class="flex-shrink-0 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Publish Paket
                </button>
            </form>
            @endif
            <a href="{{ route('dinas.paket.edit', $paket->id) }}"
               class="flex-shrink-0 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
                Edit Info Paket
            </a>
        </div>
    </div>

    {{-- Sesi Ujian --}}
    <div class="card" x-data="{ showForm: {{ $errors->has('nama_sesi') || session('_old_input') ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-gray-900">Sesi Ujian ({{ $paket->sesi->count() }})</h2>
            <button type="button" @click="showForm = !showForm"
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Sesi
            </button>
        </div>

        {{-- Add Sesi Form --}}
        <div x-show="showForm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="mb-4">
            <form action="{{ route('dinas.paket.sesi.store', $paket->id) }}" method="POST"
                  class="bg-gray-50 rounded-xl p-4 border border-gray-200 space-y-3">
                @csrf
                <p class="text-sm font-medium text-gray-700 mb-1">Tambah Sesi Baru</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nama Sesi <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" required
                               placeholder="Sesi 1 Pagi / Ruang A"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nama_sesi') border-red-400 @enderror">
                        @error('nama_sesi') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Ruangan</label>
                        <input type="text" name="ruangan" value="{{ old('ruangan') }}"
                               placeholder="Ruang Komputer 1"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Waktu Mulai</label>
                        <input type="datetime-local" name="waktu_mulai" value="{{ old('waktu_mulai') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Waktu Selesai</label>
                        <input type="datetime-local" name="waktu_selesai" value="{{ old('waktu_selesai') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Pengawas</label>
                        <select name="pengawas_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Pilih Pengawas --</option>
                            @foreach($pengawas as $p)
                            <option value="{{ $p->id }}" @selected(old('pengawas_id') === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Kapasitas Peserta</label>
                        <input type="number" name="kapasitas" value="{{ old('kapasitas') }}" min="1" max="999"
                               placeholder="Kosongkan = tidak terbatas"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Simpan Sesi
                    </button>
                    <button type="button" @click="showForm = false"
                            class="border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        {{-- Sesi List --}}
        @if($paket->sesi->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">Belum ada sesi untuk paket ini.</p>
        @else
        <div class="space-y-2">
            @foreach($paket->sesi->sortBy('waktu_mulai') as $sesi)
            @php
                $statusColor = match($sesi->status) {
                    'berlangsung' => ['bg' => 'green', 'label' => 'Berlangsung'],
                    'selesai'     => ['bg' => 'gray',  'label' => 'Selesai'],
                    default       => ['bg' => 'blue',  'label' => 'Persiapan'],
                };
            @endphp
            <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $sesi->nama_sesi }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-2">
                        @if($sesi->ruangan)
                        <span>📍 {{ $sesi->ruangan }}</span>
                        @endif
                        @if($sesi->waktu_mulai)
                        <span>🕐 {{ $sesi->waktu_mulai->isoFormat('D MMM YYYY, HH:mm') }}</span>
                        @endif
                        @if($sesi->pengawas)
                        <span>👤 {{ $sesi->pengawas->name }}</span>
                        @endif
                        <span>👥 {{ $sesi->sesiPeserta->count() }}{{ $sesi->kapasitas ? '/'.$sesi->kapasitas : '' }} peserta</span>
                    </p>
                </div>
                <span class="flex-shrink-0 text-xs font-semibold bg-{{ $statusColor['bg'] }}-100 text-{{ $statusColor['bg'] }}-700 px-2 py-0.5 rounded-full">
                    {{ $statusColor['label'] }}
                </span>
                <a href="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
                   class="flex-shrink-0 text-gray-500 hover:text-green-600 hover:bg-green-50 p-1.5 rounded-lg transition-colors"
                   title="Kelola Peserta">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </a>
                <a href="{{ route('dinas.paket.sesi.edit', [$paket->id, $sesi->id]) }}"
                   class="flex-shrink-0 text-gray-500 hover:text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition-colors"
                   title="Edit sesi">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
                @if($sesi->status !== 'berlangsung')
                <form action="{{ route('dinas.paket.sesi.destroy', [$paket->id, $sesi->id]) }}" method="POST"
                      onsubmit="return confirm('Hapus sesi \'{{ addslashes($sesi->nama_sesi) }}\'? Peserta yang terdaftar juga akan dihapus.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="flex-shrink-0 text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-lg transition-colors"
                            title="Hapus sesi">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Soal Terpilih --}}
    <div class="card">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-gray-900">Soal Terpilih (<span x-text="selectedIds.length"></span>)</h2>
            <template x-if="selectedIds.length > 0">
                <button @click="showConfirmClear = true"
                        class="text-xs text-red-500 hover:text-red-700 font-medium">
                    Hapus Semua
                </button>
            </template>
        </div>

        <template x-if="selectedIds.length === 0">
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada soal dalam paket ini. Pilih dari bank soal di bawah.</p>
        </template>

        <template x-if="selectedIds.length > 0">
            <div class="space-y-1.5">
                <template x-for="(soal, idx) in allSoal.filter(s => selectedIds.includes(s.id))" :key="soal.id">
                    <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-2.5">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full text-xs font-bold text-blue-700 flex items-center justify-center"
                              x-text="idx + 1"></span>
                        <p class="flex-1 text-sm text-gray-800 line-clamp-1" x-text="soal.pertanyaan"></p>
                        <span class="flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
                              :class="tipeBadge(soal.tipe_soal)" x-text="tipeLabel(soal.tipe_soal)"></span>
                        <span class="flex-shrink-0 text-xs text-gray-400" x-text="soal.kategori"></span>
                        <button type="button" @click="toggleSoal(soal.id)"
                                class="flex-shrink-0 text-red-400 hover:text-red-600 hover:bg-red-50 p-1.5 rounded-lg transition-colors" title="Hapus dari paket">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Bank Soal - Multi Select --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
            <h2 class="font-semibold text-gray-900">Bank Soal</h2>
            <div class="flex items-center gap-2 flex-wrap">
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
                <select x-model="filterKategori"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Kategori</option>
                    @foreach($kategoriList as $kat)
                    <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <button type="button" @click="selectAllFiltered()"
                    class="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                ✓ Pilih Semua Terfilter
            </button>
            <button type="button" @click="deselectAllFiltered()"
                    class="text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">
                ✗ Batal Pilih Terfilter
            </button>
            <span class="text-xs text-gray-400">|</span>
            <span class="text-xs text-gray-500" x-text="filteredSoal().length + ' soal ditampilkan'"></span>
        </div>

        {{-- Grouped by Kategori --}}
        <div class="space-y-4">
            <template x-for="group in groupedByKategori()" :key="group.kategoriId">
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2.5 flex items-center justify-between cursor-pointer"
                         @click="toggleKategoriCollapse(group.kategoriId)">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   :checked="isAllKategoriSelected(group)"
                                   :indeterminate.prop="isSomeKategoriSelected(group) && !isAllKategoriSelected(group)"
                                   @click.stop="toggleKategori(group)"
                                   @change.stop>
                            <span class="text-sm font-semibold text-gray-700" x-text="group.kategori"></span>
                            <span class="text-xs text-gray-400" x-text="'(' + group.soal.length + ' soal)'"></span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="collapsedKategori.includes(group.kategoriId) && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div x-show="!collapsedKategori.includes(group.kategoriId)" x-transition>
                        <template x-for="soal in group.soal" :key="soal.id">
                            <label class="flex items-center gap-3 px-4 py-2.5 border-t border-gray-100 hover:bg-blue-50/50 cursor-pointer transition-colors"
                                   :class="selectedIds.includes(soal.id) && 'bg-blue-50'">
                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       :checked="selectedIds.includes(soal.id)"
                                       @change="toggleSoal(soal.id)">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-800 line-clamp-1" x-text="soal.pertanyaan"></p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        Bobot: <span x-text="soal.bobot"></span>
                                    </p>
                                </div>
                                <span class="flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
                                      :class="tipeBadge(soal.tipe_soal)" x-text="tipeLabel(soal.tipe_soal)"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="filteredSoal().length === 0">
                <p class="text-sm text-gray-400 text-center py-6">Tidak ada soal yang cocok dengan filter.</p>
            </template>
        </div>
    </div>

    {{-- Floating Save Bar --}}
    <div x-show="hasChanges" x-transition
         class="sticky bottom-4 z-30">
        <form action="{{ route('dinas.paket.soal.sync', $paket->id) }}" method="POST"
              class="bg-white border border-gray-200 shadow-lg rounded-2xl px-6 py-3.5 flex items-center justify-between max-w-3xl mx-auto">
            @csrf @method('PUT')
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="soal_ids[]" :value="id">
            </template>
            <div class="text-sm text-gray-700">
                <span class="font-semibold text-blue-600" x-text="selectedIds.length"></span> soal dipilih
                <span class="text-gray-400 mx-1">·</span>
                <span class="text-green-600 font-medium" x-text="addedCount + ' ditambah'"></span>,
                <span class="text-red-500 font-medium" x-text="removedCount + ' dihapus'"></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="resetSelection()"
                        class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                    Batal
                </button>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-6 py-2 rounded-lg transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    {{-- Confirm Clear Dialog --}}
    <div x-show="showConfirmClear" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showConfirmClear = false">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
            <p class="text-sm text-gray-800 font-medium mb-4">Hapus semua soal dari paket ini?</p>
            <div class="flex justify-end gap-2">
                <button @click="showConfirmClear = false" class="text-sm text-gray-500 px-4 py-2 rounded-lg hover:bg-gray-100">Batal</button>
                <button @click="clearAll(); showConfirmClear = false" class="text-sm bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Hapus Semua</button>
            </div>
        </div>
    </div>

</div>

<script>
function paketSoalApp() {
    const allSoal = @json($bankSoalJson);
    const initialSelected = @json($terpilihIds);

    return {
        search: '',
        filterJenis: '',
        filterKategori: '',
        allSoal,
        selectedIds: [...initialSelected],
        originalIds: [...initialSelected],
        collapsedKategori: [],
        showConfirmClear: false,

        get hasChanges() {
            if (this.selectedIds.length !== this.originalIds.length) return true;
            return this.selectedIds.some(id => !this.originalIds.includes(id))
                || this.originalIds.some(id => !this.selectedIds.includes(id));
        },

        get addedCount() {
            return this.selectedIds.filter(id => !this.originalIds.includes(id)).length;
        },

        get removedCount() {
            return this.originalIds.filter(id => !this.selectedIds.includes(id)).length;
        },

        filteredSoal() {
            return this.allSoal.filter(s => {
                if (this.search && !s.pertanyaan.toLowerCase().includes(this.search.toLowerCase())) return false;
                if (this.filterJenis && s.tipe_soal !== this.filterJenis) return false;
                if (this.filterKategori && s.kategoriId !== this.filterKategori) return false;
                return true;
            });
        },

        groupedByKategori() {
            const filtered = this.filteredSoal();
            const groups = {};
            filtered.forEach(s => {
                const key = s.kategoriId;
                if (!groups[key]) groups[key] = { kategoriId: key, kategori: s.kategori, soal: [] };
                groups[key].soal.push(s);
            });
            return Object.values(groups).sort((a, b) => a.kategori.localeCompare(b.kategori));
        },

        toggleSoal(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx >= 0) this.selectedIds.splice(idx, 1);
            else this.selectedIds.push(id);
        },

        toggleKategori(group) {
            if (this.isAllKategoriSelected(group)) {
                const groupIds = group.soal.map(s => s.id);
                this.selectedIds = this.selectedIds.filter(id => !groupIds.includes(id));
            } else {
                group.soal.forEach(s => {
                    if (!this.selectedIds.includes(s.id)) this.selectedIds.push(s.id);
                });
            }
        },

        isAllKategoriSelected(group) {
            return group.soal.every(s => this.selectedIds.includes(s.id));
        },

        isSomeKategoriSelected(group) {
            return group.soal.some(s => this.selectedIds.includes(s.id));
        },

        toggleKategoriCollapse(id) {
            const idx = this.collapsedKategori.indexOf(id);
            if (idx >= 0) this.collapsedKategori.splice(idx, 1);
            else this.collapsedKategori.push(id);
        },

        selectAllFiltered() {
            this.filteredSoal().forEach(s => {
                if (!this.selectedIds.includes(s.id)) this.selectedIds.push(s.id);
            });
        },

        deselectAllFiltered() {
            const filteredIds = this.filteredSoal().map(s => s.id);
            this.selectedIds = this.selectedIds.filter(id => !filteredIds.includes(id));
        },

        clearAll() {
            this.selectedIds = [];
        },

        resetSelection() {
            this.selectedIds = [...this.originalIds];
        },

        tipeLabel(tipe) {
            const map = { pg: 'PG', pg_kompleks: 'PGK', isian: 'Isian', essay: 'Essay', menjodohkan: 'Jodoh' };
            return map[tipe] || tipe;
        },

        tipeBadge(tipe) {
            const map = {
                pg: 'bg-blue-100 text-blue-700', pg_kompleks: 'bg-purple-100 text-purple-700',
                isian: 'bg-green-100 text-green-700', essay: 'bg-amber-100 text-amber-700',
                menjodohkan: 'bg-pink-100 text-pink-700',
            };
            return map[tipe] || 'bg-gray-100 text-gray-700';
        },
    };
}
</script>
@endsection
