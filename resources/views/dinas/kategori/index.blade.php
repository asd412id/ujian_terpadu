@extends('layouts.admin')

@section('title', 'Kategori Soal')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Kategori Soal</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="kategoriApp()">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Kategori Soal</h1>
        <button @click="openModal()"
                class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Kategori
        </button>
    </div>

    <div class="card overflow-hidden p-0">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Nama Kategori</th>
                    <th class="px-5 py-3 text-left hidden sm:table-cell">Kelompok</th>
                    <th class="px-5 py-3 text-center hidden md:table-cell">Jenjang</th>
                    <th class="px-5 py-3 text-center hidden md:table-cell">Kurikulum</th>
                    <th class="px-5 py-3 text-center">Jumlah Soal</th>
                    <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($kategoris as $kat)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-900">{{ $kat->nama }}</p>
                        @if($kat->kode)
                        <p class="text-xs text-gray-500 font-mono">{{ $kat->kode }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden sm:table-cell text-gray-600">{{ $kat->kelompok ?? '—' }}</td>
                    <td class="px-5 py-3 text-center hidden md:table-cell">
                        @if($kat->jenjang)
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $kat->jenjang }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center hidden md:table-cell text-gray-600 text-xs">{{ $kat->kurikulum ?? '—' }}</td>
                    <td class="px-5 py-3 text-center font-medium text-gray-700">{{ $kat->soal_count ?? 0 }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="editKategori('{{ $kat->id }}', '{{ addslashes($kat->nama) }}', '{{ addslashes($kat->kode ?? '') }}', '{{ $kat->jenjang ?? '' }}', '{{ addslashes($kat->kelompok ?? '') }}', '{{ addslashes($kat->kurikulum ?? '') }}')"
                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            <form action="{{ route('dinas.kategori.destroy', $kat->id) }}" method="POST"
                                  onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @click.self="closeModal()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900" x-text="editId ? 'Edit Kategori' : 'Tambah Kategori'"></h2>
                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form :action="editId ? `/dinas/kategori/${editId}` : '{{ route('dinas.kategori.store') }}'"
                  method="POST" class="px-6 py-5 space-y-4">
                @csrf
                <input type="hidden" name="_method" x-bind:value="editId ? 'PUT' : 'POST'">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" x-model="form.nama" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode</label>
                        <input type="text" name="kode" x-model="form.kode"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenjang <span class="text-red-500">*</span></label>
                        <select name="jenjang" x-model="form.jenjang" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih Jenjang</option>
                            <option value="SD">SD</option>
                            <option value="SMP">SMP</option>
                            <option value="SMA">SMA</option>
                            <option value="SMK">SMK</option>
                            <option value="MA">MA</option>
                            <option value="MTs">MTs</option>
                            <option value="MI">MI</option>
                            <option value="SEMUA">Semua</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelompok</label>
                        <input type="text" name="kelompok" x-model="form.kelompok"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kurikulum <span class="text-red-500">*</span></label>
                        <input type="text" name="kurikulum" x-model="form.kurikulum" required placeholder="e.g. K13, Merdeka"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 btn-primary">Simpan</button>
                    <button type="button" @click="closeModal()"
                            class="flex-1 btn-secondary">Batal</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function kategoriApp() {
    return {
        showModal: false,
        editId: null,
        form: { nama: '', kode: '', jenjang: '', kelompok: '', kurikulum: '' },

        openModal() { this.editId = null; this.form = { nama: '', kode: '', jenjang: '', kelompok: '', kurikulum: '' }; this.showModal = true; },
        closeModal() { this.showModal = false; },
        editKategori(id, nama, kode, jenjang, kelompok, kurikulum) {
            this.editId = id;
            this.form = { nama, kode, jenjang, kelompok, kurikulum };
            this.showModal = true;
        }
    };
}
</script>
@endsection
