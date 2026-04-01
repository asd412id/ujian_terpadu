@extends('layouts.admin')

@section('title', 'Kelola Peserta Sesi')

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <a href="{{ route('dinas.paket.show', $paket->id) }}" class="text-gray-500 hover:text-blue-600">{{ Str::limit($paket->nama, 30) }}</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Peserta Sesi</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="pesertaSesiApp()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $sesi->nama_sesi }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $paket->nama }} · {{ $paket->jenjang }}
                @if($paket->sekolah) · {{ $paket->sekolah->nama }} @else · Semua Sekolah @endif
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($sesi->is_peserta_override)
            <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">Override Manual</span>
            @if($sesi->status === 'persiapan')
            <form action="{{ route('dinas.paket.sesi.peserta.reset', [$paket->id, $sesi->id]) }}" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Reset Auto-Sync',message:'Reset ke auto-sync? Semua peserta yang di-override akan diganti sesuai filter paket.',confirmText:'Ya, Reset',danger:true})) $el.submit()">
                @csrf
                <button type="submit"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium border border-blue-300 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                    Reset Auto-Sync
                </button>
            </form>
            @endif
            @else
            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Auto-Sync Aktif</span>
            @endif

            @if($available->total() > 0 && $sesi->status === 'persiapan')
            <form action="{{ route('dinas.paket.sesi.peserta.add-all', [$paket->id, $sesi->id]) }}" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Tambah Semua Peserta',message:'Tambahkan {{ $available->total() }} peserta {{ ($search || $sekolahFilter) ? 'sesuai filter saat ini' : 'yang tersedia' }} ke sesi ini? Aksi ini akan mengubah sesi ke override manual.',confirmText:'Ya, Tambahkan'})) $el.submit()">
                @csrf
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="sekolah_id" value="{{ $sekolahFilter }}">
                <button type="submit"
                        class="text-sm text-blue-700 hover:text-blue-900 font-medium border border-blue-300 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                    {{ ($search || $sekolahFilter) ? 'Tambah Semua Hasil Filter' : 'Tambah Semua Peserta' }}
                </button>
            </form>
            @endif

            @if($totalAvailable > 0 && $sesi->status === 'persiapan')
            <form action="{{ route('dinas.paket.sesi.peserta.sync', [$paket->id, $sesi->id]) }}" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Sinkron Peserta Baru',message:'Daftarkan {{ $totalAvailable }} peserta baru yang belum terdaftar ke sesi ini?',confirmText:'Ya, Sinkronkan'})) $el.submit()">
                @csrf
                <button type="submit"
                        class="text-sm text-green-700 hover:text-green-900 font-medium border border-green-300 bg-green-50 hover:bg-green-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sinkron Peserta Baru
                </button>
            </form>
            @endif

            <a href="{{ route('dinas.paket.show', $paket->id) }}"
               class="btn-secondary">
                Kembali
            </a>
        </div>
    </div>

    {{-- Info Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-blue-50 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ $totalEnrolled }}</p>
            <p class="text-xs text-blue-600">Terdaftar</p>
        </div>
        <div class="bg-green-50 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-green-700">{{ $totalAvailable }}</p>
            <p class="text-xs text-green-600">Tersedia</p>
        </div>
        <div class="bg-gray-50 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-700">{{ $sesi->kapasitas ?? '∞' }}</p>
            <p class="text-xs text-gray-600">Kapasitas</p>
        </div>
        <div class="bg-purple-50 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-purple-700">{{ $sesi->is_peserta_override ? 'Manual' : 'Auto' }}</p>
            <p class="text-xs text-purple-600">Mode</p>
        </div>
    </div>

    @if($sesi->status !== 'persiapan')
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm text-amber-700">
            <strong>Sesi terkunci untuk perubahan peserta.</strong>
            Status sesi saat ini adalah <strong>{{ ucfirst($sesi->status) }}</strong>, sehingga tambah/hapus/reset peserta dinonaktifkan.
        </p>
    </div>
    @endif

    {{-- Search & Filter --}}
    <div>
        <form method="GET" action="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
              class="flex flex-col sm:flex-row sm:items-end gap-3">
            <div class="flex-1 min-w-0">
                <label class="block text-xs text-gray-600 mb-1">Cari Peserta</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Nama, NISN, NIS, kelas, jurusan, atau sekolah..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            @if(!$paket->sekolah_id && $sekolahList->count() > 1)
            <div class="w-full sm:w-[220px] flex-shrink-0">
                <label class="block text-xs text-gray-600 mb-1">Sekolah</label>
                <x-searchable-select
                    name="sekolah_id"
                    :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => $s->nama])"
                    :value="$sekolahFilter"
                    placeholder="Semua Sekolah"
                    size="md" />
            </div>
            @endif
            <div class="flex items-center gap-2 flex-shrink-0">
                <button type="submit" class="btn-primary whitespace-nowrap">Filter</button>
                @if($search || $sekolahFilter)
                <a href="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
                   class="text-sm text-gray-500 hover:text-gray-700 whitespace-nowrap px-2 py-2">Reset</a>
                @endif
            </div>
        </form>
        <p class="text-[11px] text-gray-500 mt-1.5">Pencarian berlaku untuk daftar peserta terdaftar dan peserta tersedia.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Peserta Terdaftar --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Peserta Terdaftar ({{ $totalEnrolled }})</h2>
                @if($enrolled->where('pivot.status', 'terdaftar')->count() > 0 && $sesi->status === 'persiapan')
                <div class="flex items-center gap-3">
                    <button type="button" @click="selectAllEnrolled()" class="text-xs text-red-600 hover:text-red-800 font-medium">
                        <span x-text="allEnrolledSelected ? 'Batal Pilih' : 'Pilih Semua'"></span>
                    </button>
                    @if($totalEnrolled > 0)
                    <form action="{{ route('dinas.paket.sesi.peserta.remove-all', [$paket->id, $sesi->id]) }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Semua Peserta',message:'Hapus semua {{ $totalEnrolled }} peserta terdaftar dari sesi ini? Aksi ini tidak dapat dibatalkan.',confirmText:'Ya, Hapus Semua',danger:true})) $el.submit()">
                        @csrf
                        <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-300 hover:bg-red-50 px-2 py-0.5 rounded-lg transition-colors">
                            Hapus Semua
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </div>

            @if($enrolled->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">Belum ada peserta terdaftar di sesi ini.</p>
            @else
            <form action="{{ route('dinas.paket.sesi.peserta.remove', [$paket->id, $sesi->id]) }}" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Peserta',message:'Hapus peserta terpilih dari sesi ini?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                @csrf
                <div class="space-y-1.5 max-h-[500px] overflow-y-auto">
                    @foreach($enrolled as $p)
                    @php $canRemove = $p->pivot->status === 'terdaftar'; @endphp
                    <label class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ $canRemove ? 'hover:bg-red-50 cursor-pointer' : 'bg-gray-50' }}">
                        @if($canRemove && $sesi->status === 'persiapan')
                        <input type="checkbox" name="peserta_ids[]" value="{{ $p->id }}"
                               x-model="enrolledSelected"
                               class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        @else
                        <span class="w-4 h-4 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                            </svg>
                        </span>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $p->nisn ?? $p->nis }} · {{ $p->sekolah->nama ?? '-' }}</p>
                        </div>
                        @php
                            $statusMeta = match($p->pivot->status) {
                                'terdaftar'   => ['Terdaftar', 'blue'],
                                'belum_login' => ['Belum Login', 'gray'],
                                'login'       => ['Login', 'yellow'],
                                'mengerjakan' => ['Mengerjakan', 'amber'],
                                'submit'      => ['Submit', 'green'],
                                'dinilai'     => ['Dinilai', 'purple'],
                                'tidak_hadir' => ['Tidak Hadir', 'red'],
                                default       => [ucfirst($p->pivot->status), 'gray'],
                            };
                        @endphp
                        <span class="flex-shrink-0 text-xs font-semibold bg-{{ $statusMeta[1] }}-100 text-{{ $statusMeta[1] }}-700 px-2 py-0.5 rounded-full">
                            {{ $statusMeta[0] }}
                        </span>
                    </label>
                    @endforeach
                </div>
                @if($sesi->status === 'persiapan')
                <div x-show="enrolledSelected.length > 0" x-transition class="mt-3 pt-3 border-t">
                    <button type="submit"
                            class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Hapus <span x-text="enrolledSelected.length"></span> Peserta dari Sesi
                    </button>
                </div>
                @endif
            </form>
            @if($enrolled->hasPages())
            <div class="mt-3 pt-3 border-t">
                {{ $enrolled->appends(request()->query())->fragment('enrolled')->links() }}
            </div>
            @endif
            @endif
        </div>

        {{-- Peserta Tersedia --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Peserta Tersedia ({{ $totalAvailable }})</h2>
                @if($available->count() > 0 && $sesi->status === 'persiapan')
                <button type="button" @click="selectAllAvailable()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    <span x-text="allAvailableSelected ? 'Batal Pilih' : 'Pilih Semua'"></span>
                </button>
                @endif
            </div>

            @if($available->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">
                @if($search || $sekolahFilter)
                Tidak ada peserta tersedia sesuai filter.
                @else
                Semua peserta yang memenuhi syarat sudah terdaftar.
                @endif
            </p>
            @else
            <form action="{{ route('dinas.paket.sesi.peserta.add', [$paket->id, $sesi->id]) }}" method="POST">
                @csrf
                <div class="space-y-1.5 max-h-[500px] overflow-y-auto">
                    @foreach($available as $p)
                    <label class="flex items-center gap-3 {{ $sesi->status === 'persiapan' ? 'hover:bg-blue-50 cursor-pointer' : 'bg-gray-50' }} rounded-xl px-3 py-2.5">
                        @if($sesi->status === 'persiapan')
                        <input type="checkbox" name="peserta_ids[]" value="{{ $p->id }}"
                               x-model="availableSelected"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        @else
                        <span class="w-4 h-4 flex items-center justify-center text-gray-400">•</span>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $p->nisn ?? $p->nis }} · {{ $p->sekolah->nama ?? '-' }}</p>
                        </div>
                        <span class="flex-shrink-0 text-xs text-gray-400">{{ $p->kelas ?? '' }}</span>
                    </label>
                    @endforeach
                </div>
                @if($sesi->status === 'persiapan')
                <div x-show="availableSelected.length > 0" x-transition class="mt-3 pt-3 border-t">
                    <button type="submit"
                            class="btn-primary w-full">
                        Tambah <span x-text="availableSelected.length"></span> Peserta ke Sesi
                    </button>
                </div>
                @endif
            </form>
            @if($available->hasPages())
            <div class="mt-3 pt-3 border-t">
                {{ $available->appends(request()->query())->fragment('available')->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>

    @if(!$sesi->is_peserta_override)
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-sm text-blue-700">
            <strong>Mode Auto-Sync:</strong> Peserta otomatis ditambahkan berdasarkan filter paket
            ({{ $paket->jenjang }}{{ $paket->sekolah ? ' · ' . $paket->sekolah->nama : ' · Semua Sekolah' }}).
            Menambah atau menghapus peserta secara manual akan mengalihkan sesi ke mode <strong>Override Manual</strong>.
        </p>
    </div>
    @endif
</div>

@push('scripts')
<script>
function pesertaSesiApp() {
    const enrolledRemovable = @json($enrolled->filter(fn($p) => $p->pivot->status === 'terdaftar')->pluck('id')->values());
    const availableIds = @json($available->pluck('id')->values());
    const enrolledIdsUrl = @json(route('dinas.paket.sesi.peserta.enrolled-ids', [$paket->id, $sesi->id]));
    let allEnrolledIds = null;

    return {
        enrolledSelected: [],
        availableSelected: [],
        loadingAllEnrolled: false,

        get allEnrolledSelected() {
            if (allEnrolledIds) {
                return allEnrolledIds.length > 0 && this.enrolledSelected.length === allEnrolledIds.length;
            }
            return enrolledRemovable.length > 0 && this.enrolledSelected.length === enrolledRemovable.length;
        },
        get allAvailableSelected() {
            return availableIds.length > 0 && this.availableSelected.length === availableIds.length;
        },

        async selectAllEnrolled() {
            if (this.allEnrolledSelected) {
                this.enrolledSelected = [];
                return;
            }
            if (allEnrolledIds) {
                this.enrolledSelected = allEnrolledIds.map(String);
                return;
            }
            this.loadingAllEnrolled = true;
            try {
                const resp = await fetch(enrolledIdsUrl, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json();
                allEnrolledIds = data.ids;
                this.enrolledSelected = allEnrolledIds.map(String);
            } catch (e) {
                this.enrolledSelected = enrolledRemovable.map(String);
            } finally {
                this.loadingAllEnrolled = false;
            }
        },
        selectAllAvailable() {
            if (this.allAvailableSelected) {
                this.availableSelected = [];
            } else {
                this.availableSelected = availableIds.map(String);
            }
        }
    };
}
</script>
@endpush
@endsection
