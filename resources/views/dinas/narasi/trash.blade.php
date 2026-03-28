@extends('layouts.admin')

@section('title', 'Sampah - Narasi')

@section('breadcrumb')
    <a href="{{ route('dinas.soal.index', ['tab' => 'narasi']) }}" class="text-blue-600 hover:underline">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Sampah Narasi</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="trashManager(@js($trashedNarasi->pluck('id')->values()), @js($allFilteredIds ?? []), 'narasi')">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Sampah Narasi</h1>
            <p class="text-sm text-gray-500 mt-1">Narasi yang dihapus dapat dipulihkan atau dihapus permanen.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($trashedNarasi->total() > 0)
            <form action="{{ route('dinas.narasi.empty-trash') }}" method="POST"
                  @submit.prevent="if(await $store.confirmModal.open({title:'Kosongkan Sampah Narasi',message:'SEMUA narasi di sampah (' + filteredTotal + ' narasi) akan DIHAPUS PERMANEN beserta soal terkait dan asetnya.\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Kosongkan Sampah',danger:true})) $el.submit()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger inline-flex items-center gap-1.5">Kosongkan Sampah (<span x-text="filteredTotal"></span>)</button>
            </form>
            @endif
            <a href="{{ route('dinas.soal.index', ['tab' => 'narasi']) }}" class="btn-secondary inline-flex items-center gap-1.5">Kembali</a>
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
                <form method="POST" action="{{ route('dinas.narasi.bulk-restore') }}"
                      @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Narasi',message:'Pulihkan ' + selectionCount + ' narasi terpilih beserta soal terkait yang masih di sampah?',confirmText:'Ya, Pulihkan'})) submitBulk($el)">
                    @csrf
                    <template x-for="id in selectedIds()" :key="'restore-' + id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">Pulihkan Dipilih</button>
                </form>
                <form method="POST" action="{{ route('dinas.narasi.bulk-force-delete') }}"
                      @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen Narasi',message:'HAPUS PERMANEN ' + selectionCount + ' narasi terpilih beserta soal terkait dan asetnya?\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Hapus Permanen',danger:true})) submitBulk($el)">
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
            <span class="text-blue-600">Klik checkbox/item untuk mengecualikan atau memasukkan kembali item tertentu.</span>
        </div>
    </div>

    @if($trashedNarasi->total() > 0 || request('trash_search') || request('trash_kategori'))
    <form method="GET" action="{{ route('dinas.narasi.trash') }}" class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="trash_search" value="{{ request('trash_search') }}" placeholder="Cari narasi..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="trash_kategori" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kategori</option>
            @foreach($kategoris as $kat)
            <option value="{{ $kat->id }}" {{ request('trash_kategori') == $kat->id ? 'selected' : '' }}>{{ $kat->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary px-6">Cari</button>
    </form>
    @endif

    @if($trashedNarasi->count() > 0)
    <div class="card overflow-x-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-100 bg-gray-50 text-sm">
            <label class="inline-flex items-center gap-2 text-gray-700">
                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       @change="togglePage($event)" :checked="allPageSelected" :indeterminate.prop="somePageSelected">
                <span>Pilih halaman ini ({{ $trashedNarasi->count() }})</span>
            </label>
            <button type="button" class="text-blue-600 hover:underline text-left sm:text-right" @click="selectAllFiltered()">
                Pilih semua hasil filter (<span x-text="filteredTotal"></span>)
            </button>
        </div>

        <table class="w-full text-sm text-left">
            <thead>
                <tr class="border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 w-10"></th>
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-center">Soal</th>
                    <th class="px-4 py-3">Dihapus</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($trashedNarasi as $narasi)
                <tr class="hover:bg-gray-50 transition-colors" :class="rowClass('{{ $narasi->id }}')">
                    <td class="px-4 py-3">
                        <input type="checkbox" value="{{ $narasi->id }}"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               @change="toggle('{{ $narasi->id }}')" :checked="isSelected('{{ $narasi->id }}')">
                    </td>
                    <td class="px-4 py-3 max-w-md">
                        <div class="font-medium text-gray-900">{{ $narasi->judul }}</div>
                        <div class="text-xs text-gray-500 line-clamp-2">{{ \App\Support\HtmlDisplay::plainText($narasi->konten, 120) }}</div>
                        <button x-show="selectAllFilteredMode" x-cloak type="button"
                                class="mt-1 text-xs font-medium"
                                :class="isExcluded('{{ $narasi->id }}') ? 'text-green-600 hover:underline' : 'text-amber-600 hover:underline'"
                                @click="toggle('{{ $narasi->id }}')"
                                x-text="isExcluded('{{ $narasi->id }}') ? 'Masukkan lagi item ini' : 'Kecualikan item ini'"></button>
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $narasi->kategori->nama ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-700">{{ $narasi->soal_list_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ $narasi->deleted_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            <form action="{{ route('dinas.narasi.restore', $narasi->id) }}" method="POST"
                                  @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Narasi',message:'Pulihkan narasi ini beserta soal terkait yang masih di sampah?',confirmText:'Ya, Pulihkan'})) $el.submit()">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-800 hover:bg-green-50 px-2 py-1 rounded text-xs font-medium transition-colors">Pulihkan</button>
                            </form>
                            <form action="{{ route('dinas.narasi.force-delete', $narasi->id) }}" method="POST"
                                  @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen Narasi',message:'HAPUS PERMANEN narasi ini beserta soal terkait dan asetnya? Tindakan ini tidak dapat dibatalkan!',confirmText:'Ya, Hapus Permanen',danger:true})) $el.submit()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-xs font-medium transition-colors">Hapus Permanen</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($trashedNarasi->hasPages())
    <div>{{ $trashedNarasi->links() }}</div>
    @endif
    @else
    <div class="card text-center py-16">
        <p class="text-gray-500 mb-4">Sampah narasi kosong.</p>
        <a href="{{ route('dinas.soal.index', ['tab' => 'narasi']) }}" class="text-blue-600 hover:underline text-sm">Kembali ke Bank Soal</a>
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
            if (this.isExcluded(id)) return 'bg-amber-50';
            if (this.isSelected(id)) return 'bg-blue-50';
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
