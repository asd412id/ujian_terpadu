@extends('layouts.admin')

@section('title', 'Sampah - Bank Soal')

@section('breadcrumb')
    <a href="{{ route('pembuat-soal.soal.index') }}" class="text-blue-600 hover:underline">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-800 font-semibold">Sampah</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="trashManager()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Sampah Soal</h1>
            <p class="text-sm text-gray-500 mt-1">Soal yang dihapus dapat dipulihkan atau dihapus permanen.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($trashedSoal->total() > 0)
            <form action="{{ route('pembuat-soal.soal.empty-trash') }}" method="POST"
                  @submit.prevent="if(await $store.confirmModal.open({title:'Kosongkan Sampah',message:'SEMUA soal di sampah ({{ $trashedSoal->total() }} soal) akan DIHAPUS PERMANEN beserta opsi jawaban dan data terkait.\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Kosongkan Sampah',danger:true})) $el.submit()">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Kosongkan Sampah ({{ $trashedSoal->total() }})
                </button>
            </form>
            @endif
            <a href="{{ route('pembuat-soal.soal.index') }}" class="btn-secondary inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Bulk action bar --}}
    <div x-show="selected.length > 0" x-cloak
         class="card bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3">
        <span class="text-sm font-medium text-blue-800" x-text="selected.length + ' soal dipilih'"></span>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('pembuat-soal.soal.bulk-restore') }}"
                  @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Soal',message:'Pulihkan ' + selected.length + ' soal yang dipilih?',confirmText:'Ya, Pulihkan'})) submitBulk($el)">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button type="submit" class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Pulihkan Dipilih
                </button>
            </form>
            <form method="POST" action="{{ route('pembuat-soal.soal.bulk-force-delete') }}"
                  @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen',message:'HAPUS PERMANEN ' + selected.length + ' soal yang dipilih?\n\nTindakan ini TIDAK DAPAT dibatalkan!',confirmText:'Ya, Hapus Permanen',danger:true})) submitBulk($el)">
                @csrf @method('DELETE')
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button type="submit" class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Permanen Dipilih
                </button>
            </form>
        </div>
    </div>

    {{-- Filter --}}
    @if($trashedSoal->total() > 0 || request('trash_search') || request('trash_kategori'))
    <form method="GET" action="{{ route('pembuat-soal.soal.trash') }}" class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="trash_search" value="{{ request('trash_search') }}" placeholder="Cari soal..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="trash_kategori" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kategori</option>
            @foreach($kategori as $kat)
            <option value="{{ $kat->id }}" {{ request('trash_kategori') == $kat->id ? 'selected' : '' }}>{{ $kat->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary px-6">Cari</button>
    </form>
    @endif

    {{-- Tabel --}}
    @if($trashedSoal->count() > 0)
    <div class="card overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               @change="toggleAll($event)" :checked="allSelected" :indeterminate.prop="someSelected">
                    </th>
                    <th class="px-4 py-3">PERTANYAAN</th>
                    <th class="px-4 py-3">KATEGORI</th>
                    <th class="px-4 py-3">JENIS</th>
                    <th class="px-4 py-3">DIHAPUS</th>
                    <th class="px-4 py-3 text-right">AKSI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($trashedSoal as $soal)
                <tr class="hover:bg-gray-50 transition-colors" :class="selected.includes('{{ $soal->id }}') && 'bg-blue-50'">
                    <td class="px-4 py-3">
                        <input type="checkbox" value="{{ $soal->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               @change="toggle('{{ $soal->id }}')" :checked="selected.includes('{{ $soal->id }}')">
                    </td>
                    <td class="px-4 py-3 max-w-md">
                        <div class="text-gray-800 line-clamp-2 text-sm">{!! strip_tags($soal->pertanyaan) !!}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $soal->kategori->nama ?? '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @php
                            $tipeLabels = [
                                'pg' => ['PG', 'bg-blue-100 text-blue-700'],
                                'pg_kompleks' => ['PG Kompleks', 'bg-purple-100 text-purple-700'],
                                'benar_salah' => ['B/S', 'bg-yellow-100 text-yellow-700'],
                                'menjodohkan' => ['Jodoh', 'bg-green-100 text-green-700'],
                                'isian' => ['Isian', 'bg-orange-100 text-orange-700'],
                                'essay' => ['Essay', 'bg-gray-100 text-gray-700'],
                            ];
                            [$tipeLabel, $tipeClass] = $tipeLabels[$soal->tipe_soal] ?? [$soal->tipe_soal, 'bg-gray-100 text-gray-600'];
                        @endphp
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $tipeClass }}">{{ $tipeLabel }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ $soal->deleted_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            <form action="{{ route('pembuat-soal.soal.restore', $soal->id) }}" method="POST"
                                  @submit.prevent="if(await $store.confirmModal.open({title:'Pulihkan Soal',message:'Pulihkan soal ini ke Bank Soal?',confirmText:'Ya, Pulihkan'})) $el.submit()">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-800 hover:bg-green-50 px-2 py-1 rounded text-xs font-medium transition-colors">Pulihkan</button>
                            </form>
                            <form action="{{ route('pembuat-soal.soal.force-delete', $soal->id) }}" method="POST"
                                  @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Permanen',message:'HAPUS PERMANEN soal ini? Tindakan ini tidak dapat dibatalkan!',confirmText:'Ya, Hapus Permanen',danger:true})) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-xs font-medium transition-colors">Hapus Permanen</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($trashedSoal->hasPages())
    <div>{{ $trashedSoal->links() }}</div>
    @endif

    @else
    <div class="card text-center py-16">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <p class="text-gray-500 mb-4">Sampah kosong.</p>
        <a href="{{ route('pembuat-soal.soal.index') }}" class="text-blue-600 hover:underline text-sm">Kembali ke Bank Soal</a>
    </div>
    @endif

</div>

@push('scripts')
<script>
function trashManager() {
    return {
        selected: [],
        pageIds: @json($trashedSoal->pluck('id')),
        get allSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)); },
        get someSelected() { return this.selected.length > 0 && !this.allSelected; },
        toggle(id) {
            const i = this.selected.indexOf(id);
            i === -1 ? this.selected.push(id) : this.selected.splice(i, 1);
        },
        toggleAll(e) {
            if (e.target.checked) {
                this.pageIds.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
            } else {
                this.selected = this.selected.filter(id => !this.pageIds.includes(id));
            }
        },
        submitBulk(form) { form.submit(); }
    };
}
</script>
@endpush
@endsection
