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
        <div class="flex items-center gap-2">
            @if($sesi->is_peserta_override)
            <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">Override Manual</span>
            <form action="{{ route('dinas.paket.sesi.peserta.reset', [$paket->id, $sesi->id]) }}" method="POST"
                  onsubmit="return confirm('Reset ke auto-sync? Semua peserta yang di-override akan diganti sesuai filter paket.')">
                @csrf
                <button type="submit"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium border border-blue-300 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                    Reset Auto-Sync
                </button>
            </form>
            @else
            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Auto-Sync Aktif</span>
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

    {{-- Search & Filter --}}
    <form method="GET" action="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
          class="flex flex-wrap gap-2 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-600 mb-1">Cari Peserta</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Nama atau NISN..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        @if(!$paket->sekolah_id && $sekolahList->count() > 1)
        <div class="min-w-[180px]">
            <label class="block text-xs text-gray-600 mb-1">Sekolah</label>
            <select name="sekolah_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Sekolah</option>
                @foreach($sekolahList as $s)
                <option value="{{ $s->id }}" @selected($sekolahFilter == $s->id)>{{ $s->nama }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <button type="submit"
                class="btn-primary">
            Filter
        </button>
        @if($search || $sekolahFilter)
        <a href="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
           class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2">Reset</a>
        @endif
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Peserta Terdaftar --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Peserta Terdaftar ({{ $totalEnrolled }})</h2>
                @if($enrolled->where('pivot.status', 'terdaftar')->count() > 0)
                <button type="button" @click="selectAllEnrolled()" class="text-xs text-red-600 hover:text-red-800 font-medium">
                    <span x-text="allEnrolledSelected ? 'Batal Pilih' : 'Pilih Semua'"></span>
                </button>
                @endif
            </div>

            @if($enrolled->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">Belum ada peserta terdaftar di sesi ini.</p>
            @else
            <form action="{{ route('dinas.paket.sesi.peserta.remove', [$paket->id, $sesi->id]) }}" method="POST"
                  onsubmit="return confirm('Hapus peserta terpilih dari sesi ini?')">
                @csrf
                <div class="space-y-1.5 max-h-[500px] overflow-y-auto">
                    @foreach($enrolled as $p)
                    @php $canRemove = $p->pivot->status === 'terdaftar'; @endphp
                    <label class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ $canRemove ? 'hover:bg-red-50 cursor-pointer' : 'bg-gray-50' }}">
                        @if($canRemove)
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
                <div x-show="enrolledSelected.length > 0" x-transition class="mt-3 pt-3 border-t">
                    <button type="submit"
                            class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Hapus <span x-text="enrolledSelected.length"></span> Peserta dari Sesi
                    </button>
                </div>
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
                @if($available->count() > 0)
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
                    <label class="flex items-center gap-3 hover:bg-blue-50 rounded-xl px-3 py-2.5 cursor-pointer">
                        <input type="checkbox" name="peserta_ids[]" value="{{ $p->id }}"
                               x-model="availableSelected"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $p->nisn ?? $p->nis }} · {{ $p->sekolah->nama ?? '-' }}</p>
                        </div>
                        <span class="flex-shrink-0 text-xs text-gray-400">{{ $p->kelas ?? '' }}</span>
                    </label>
                    @endforeach
                </div>
                <div x-show="availableSelected.length > 0" x-transition class="mt-3 pt-3 border-t">
                    <button type="submit"
                            class="btn-primary w-full">
                        Tambah <span x-text="availableSelected.length"></span> Peserta ke Sesi
                    </button>
                </div>
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

    return {
        enrolledSelected: [],
        availableSelected: [],

        get allEnrolledSelected() {
            return enrolledRemovable.length > 0 && this.enrolledSelected.length === enrolledRemovable.length;
        },
        get allAvailableSelected() {
            return availableIds.length > 0 && this.availableSelected.length === availableIds.length;
        },

        selectAllEnrolled() {
            if (this.allEnrolledSelected) {
                this.enrolledSelected = [];
            } else {
                this.enrolledSelected = enrolledRemovable.map(String);
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
