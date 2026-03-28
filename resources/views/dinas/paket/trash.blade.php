@extends('layouts.admin')

@section('title', 'Sampah - Paket Ujian')

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-blue-600 hover:underline">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Sampah</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="trashManager(@js($paket->pluck('id')->values()), @js($allFilteredIds ?? []), 'paket')">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Sampah Paket Ujian</h1>
            <p class="text-sm text-gray-500 mt-1">Paket yang dihapus dapat dipulihkan atau dihapus permanen.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($paket->count() > 0)
            <form action="{{ route('dinas.paket.empty-trash') }}" method="POST"
                  @submit.prevent="if(await $store.confirmModal.open({title:'Kosongkan Sampah',message:'SEMUA paket ujian terhapus (' + filteredTotal + ' paket) akan DIHAPUS PERMANEN beserta seluruh sesi, jawaban peserta, dan log aktivitas.\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Kosongkan Sampah',danger:true})) $el.submit()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger inline-flex items-center gap-1.5">Kosongkan Sampah (<span x-text="filteredTotal"></span>)</button>
            </form>
            @endif
            <a href="{{ route('dinas.paket.index') }}" class="btn-secondary inline-flex items-center gap-1.5">Kembali</a>
        </div>
    </div>

    <div x-show="selectionCount > 0" x-cloak class="sticky top-20 z-30 card bg-blue-50 border border-blue-200 shadow-sm flex flex-col gap-3 p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="space-y-1">
                <div class="text-sm font-medium text-blue-800" x-text="selectionLabel()"></div>
                <div class="flex items-center gap-3 text-xs">
                    <span x-show="selectAllFilteredMode" x-cloak class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-2.5 py-1 font-medium">Mode semua hasil filter aktif</span>
                    <button type="button" class="text-blue-700 hover:underline" @click="clearSelection()">Reset pilihan</button>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <form method="POST" action="{{ route('dinas.paket.bulk-restore') }}" @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Paket',message:'Pulihkan ' + selectionCount + ' paket terpilih?',confirmText:'Ya, Pulihkan'})) submitBulk($el)">
                    @csrf
                    <template x-for="id in selectedIds()" :key="'restore-' + id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">Pulihkan Dipilih</button>
                </form>
                <form method="POST" action="{{ route('dinas.paket.bulk-force-delete') }}" @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen',message:'HAPUS PERMANEN ' + selectionCount + ' paket terpilih beserta semua sesi, jawaban, dan log?\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Hapus Permanen',danger:true})) submitBulk($el)">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selectedIds()" :key="'delete-' + id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">Hapus Permanen Dipilih</button>
                </form>
            </div>
        </div>

        <div x-show="selectAllFilteredMode" x-cloak class="rounded-xl border border-blue-200 bg-white/80 px-3 py-2 text-xs text-blue-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <span>
                Semua hasil filter sedang dipilih.
                <span x-show="excludedCount > 0" x-cloak>
                    <strong x-text="excludedCount"></strong> item dikecualikan dari aksi bulk.
                </span>
            </span>
            <span class="text-blue-600">Klik checkbox paket untuk mengecualikan atau memasukkan kembali item tertentu.</span>
        </div>
    </div>

    @if($paket->count() > 0)
    <div class="card flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 bg-gray-50 text-sm">
        <label class="inline-flex items-center gap-2 text-gray-700">
            <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                   @change="togglePage($event)" :checked="allPageSelected" :indeterminate.prop="somePageSelected">
            <span>Pilih halaman ini ({{ $paket->count() }})</span>
        </label>
        <button type="button" class="text-blue-600 hover:underline text-left sm:text-right" @click="selectAllFiltered()">
            Pilih semua hasil filter (<span x-text="filteredTotal"></span>)
        </button>
    </div>

    @forelse($paket as $item)
    <div class="card border-l-4 border-l-red-200" :class="rowClass('{{ $item->id }}')">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-3 flex-1 min-w-0">
                <input type="checkbox" value="{{ $item->id }}" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       @change="toggle('{{ $item->id }}')" :checked="isSelected('{{ $item->id }}')">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h2 class="text-base font-semibold text-gray-900">{{ $item->nama }}</h2>
                        <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Dihapus</span>
                        @if($item->jenjang)
                        <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $item->jenjang }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mb-2">{{ $item->deskripsi }}</p>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                        <span>{{ $item->paket_soal_count ?? 0 }} soal</span>
                        <span>{{ $item->durasi_menit }} menit</span>
                        <span>{{ $item->sesi_count ?? 0 }} sesi</span>
                        <span class="text-red-500">Dihapus {{ $item->deleted_at->diffForHumans() }}</span>
                    </div>
                    <button x-show="selectAllFilteredMode" x-cloak type="button"
                            class="mt-2 text-xs font-medium"
                            :class="isExcluded('{{ $item->id }}') ? 'text-green-600 hover:underline' : 'text-amber-600 hover:underline'"
                            @click="toggle('{{ $item->id }}')"
                            x-text="isExcluded('{{ $item->id }}') ? 'Masukkan lagi item ini' : 'Kecualikan item ini'"></button>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <form action="{{ route('dinas.paket.restore', $item->id) }}" method="POST"
                      @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Paket',message:'Pulihkan paket ujian ini?',confirmText:'Ya, Pulihkan'})) $el.submit()">
                    @csrf
                    <button type="submit" class="border border-green-300 hover:bg-green-50 text-green-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">Pulihkan</button>
                </form>
                <form action="{{ route('dinas.paket.force-delete', $item->id) }}" method="POST"
                      @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen',message:'HAPUS PERMANEN paket ujian ini?\n\nSemua sesi, jawaban peserta, dan log aktivitas akan DIHAPUS PERMANEN dan tidak dapat dikembalikan!',confirmText:'Ya, Hapus Permanen',danger:true})) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="border border-red-300 hover:bg-red-50 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">Hapus Permanen</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="card text-center py-16">
        <p class="text-gray-500 mb-4">Sampah kosong.</p>
        <a href="{{ route('dinas.paket.index') }}" class="text-blue-600 hover:underline text-sm">Kembali ke Paket Ujian</a>
    </div>
    @endforelse

    @if($paket->hasPages())
    <div>{{ $paket->links() }}</div>
    @endif
    @else
    <div class="card text-center py-16">
        <p class="text-gray-500 mb-4">Sampah kosong.</p>
        <a href="{{ route('dinas.paket.index') }}" class="text-blue-600 hover:underline text-sm">Kembali ke Paket Ujian</a>
    </div>
    @endif
</div>

@push('scripts')
<script>
function trashManager(pageIds, filteredIds, label) {
    return {
        label,
        pageIds,
        filteredIds,
        selected: [],
        excluded: [],
        selectAllFilteredMode: false,
        get filteredTotal() { return this.filteredIds.length; },
        get excludedCount() { return this.excluded.length; },
        get selectionCount() { return this.selectAllFilteredMode ? Math.max(this.filteredTotal - this.excludedCount, 0) : this.selected.length; },
        get allPageSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.isSelected(id)); },
        get somePageSelected() { return !this.allPageSelected && this.pageIds.some(id => this.isSelected(id)); },
        selectedIds() { return this.selectAllFilteredMode ? this.filteredIds.filter(id => !this.excluded.includes(id)) : this.selected; },
        isSelected(id) { return this.selectAllFilteredMode ? this.filteredIds.includes(id) && !this.excluded.includes(id) : this.selected.includes(id); },
        isExcluded(id) { return this.selectAllFilteredMode && this.excluded.includes(id); },
        rowClass(id) {
            if (this.isExcluded(id)) return '!bg-amber-50 !border-l-amber-300';
            if (this.isSelected(id)) return '!bg-blue-50 !border-l-blue-400';
            return '';
        },
        selectionLabel() {
            if (this.selectAllFilteredMode) {
                return this.excludedCount > 0
                    ? `Semua ${this.filteredTotal} ${this.label} hasil filter dipilih (${this.excludedCount} dikecualikan)`
                    : `Semua ${this.filteredTotal} ${this.label} hasil filter dipilih`;
            }
            return `${this.selected.length} ${this.label} dipilih`;
        },
        toggle(id) {
            if (this.selectAllFilteredMode) {
                const index = this.excluded.indexOf(id);
                if (index === -1) {
                    this.excluded.push(id);
                } else {
                    this.excluded.splice(index, 1);
                }
                return;
            }

            const index = this.selected.indexOf(id);
            if (index === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(index, 1);
            }
        },
        togglePage(e) {
            if (this.selectAllFilteredMode) {
                if (e.target.checked) {
                    this.excluded = this.excluded.filter(id => !this.pageIds.includes(id));
                } else {
                    this.pageIds.forEach(id => {
                        if (this.filteredIds.includes(id) && !this.excluded.includes(id)) {
                            this.excluded.push(id);
                        }
                    });
                }
                return;
            }

            if (e.target.checked) {
                this.pageIds.forEach(id => {
                    if (!this.selected.includes(id)) {
                        this.selected.push(id);
                    }
                });
            } else {
                this.selected = this.selected.filter(id => !this.pageIds.includes(id));
            }
        },
        selectAllFiltered() {
            this.selectAllFilteredMode = true;
            this.selected = [];
            this.excluded = [];
        },
        clearSelection() {
            this.selectAllFilteredMode = false;
            this.selected = [];
            this.excluded = [];
        },
        submitBulk(form) {
            if (this.selectionCount === 0) {
                return;
            }
            form.submit();
        },
    }
}
</script>
@endpush
@endsection
