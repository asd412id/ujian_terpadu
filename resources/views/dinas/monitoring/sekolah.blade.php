@extends('layouts.admin')

@section('title', 'Monitoring Per Sekolah')

@section('breadcrumb')
    <a href="{{ route('dinas.monitoring') }}" class="text-gray-500 hover:text-blue-600">Monitoring</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Per Sekolah</span>
@endsection

@section('page-content')
<div x-data="monitorSekolah()" x-init="init()">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600" x-text="summary.total_sekolah">–</p>
            <p class="text-sm text-gray-500 mt-1">Sekolah Terdaftar</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600" x-text="summary.sekolah_aktif">–</p>
            <p class="text-sm text-gray-500 mt-1">Sedang Ujian</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-indigo-600" x-text="summary.total_peserta_aktif">–</p>
            <p class="text-sm text-gray-500 mt-1">Peserta Online</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-orange-500" x-text="summary.total_selesai">–</p>
            <p class="text-sm text-gray-500 mt-1">Selesai Hari Ini</p>
        </div>
    </div>

    {{-- Filter & Search --}}
    <div class="card mb-5">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-52">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Cari sekolah..."
                       class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <select x-model="filterStatus"
                    class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Status</option>
                <option value="aktif">Sedang Ujian</option>
                <option value="selesai">Selesai</option>
                <option value="belum">Belum Mulai</option>
            </select>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span>Auto-refresh 10 detik</span>
            </div>
        </div>
    </div>

    {{-- Tabel Sekolah --}}
    <div class="card overflow-hidden p-0">
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Sekolah</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Sesi Aktif</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600">Peserta Online</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Selesai</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Cheating</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Status</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="sekolah-tbody">
                    <template x-if="loading">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Memuat data...
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && filteredSekolah.length === 0">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                Tidak ada data sekolah
                            </td>
                        </tr>
                    </template>
                    <template x-for="s in filteredSekolah" :key="s.id">
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900" x-text="s.nama_sekolah"></p>
                                <p class="text-xs text-gray-400" x-text="s.kode_sekolah"></p>
                            </td>
                            <td class="px-4 py-3 text-center hidden md:table-cell">
                                <span class="font-semibold text-blue-600" x-text="s.sesi_aktif"></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"
                                          :class="s.peserta_online > 0 ? 'animate-pulse' : 'opacity-0'"></span>
                                    <span class="font-semibold" x-text="s.peserta_online"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-700 hidden lg:table-cell" x-text="s.peserta_selesai"></td>
                            <td class="px-4 py-3 text-center hidden lg:table-cell">
                                <span class="font-semibold"
                                      :class="s.cheating_count > 0 ? 'text-red-600' : 'text-gray-400'"
                                      x-text="s.cheating_count"></span>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      :class="{
                                          'bg-green-100 text-green-800': s.status === 'aktif',
                                          'bg-gray-100 text-gray-600':  s.status === 'selesai',
                                          'bg-yellow-100 text-yellow-800': s.status === 'belum'
                                      }"
                                      x-text="s.status === 'aktif' ? 'Sedang Ujian' : s.status === 'selesai' ? 'Selesai' : 'Belum Mulai'">
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a :href="`/dinas/monitoring/sesi?sekolah_id=${s.id}`"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium hover:underline">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            <template x-if="loading">
                <div class="px-4 py-8 text-center text-gray-400">
                    <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Memuat data...
                </div>
            </template>
            <template x-if="!loading && filteredSekolah.length === 0">
                <div class="px-4 py-8 text-center text-gray-400">Tidak ada data sekolah</div>
            </template>
            <template x-for="s in filteredSekolah" :key="s.id">
                <div class="px-4 py-3">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm" x-text="s.nama_sekolah"></p>
                            <p class="text-xs text-gray-400" x-text="s.kode_sekolah"></p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0"
                              :class="{
                                  'bg-green-100 text-green-800': s.status === 'aktif',
                                  'bg-gray-100 text-gray-600':  s.status === 'selesai',
                                  'bg-yellow-100 text-yellow-800': s.status === 'belum'
                              }"
                              x-text="s.status === 'aktif' ? 'Sedang Ujian' : s.status === 'selesai' ? 'Selesai' : 'Belum Mulai'">
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs mb-2">
                        <div class="bg-blue-50 rounded-lg p-1.5">
                            <p class="font-bold text-blue-700" x-text="s.sesi_aktif"></p>
                            <p class="text-gray-500">Sesi</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-1.5">
                            <p class="font-bold text-green-700" x-text="s.peserta_online"></p>
                            <p class="text-gray-500">Online</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-1.5">
                            <p class="font-bold text-gray-700" x-text="s.peserta_selesai"></p>
                            <p class="text-gray-500">Selesai</p>
                        </div>
                    </div>
                    <a :href="`/dinas/monitoring/sesi?sekolah_id=${s.id}`"
                       class="block text-center text-blue-600 text-xs font-medium">Lihat Detail →</a>
                </div>
            </template>
        </div>
    </div>

    {{-- Detail Sesi (expanded) --}}
    <template x-if="selectedSekolah">
        <div class="card mt-5" x-transition>
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900" x-text="'Sesi Aktif — ' + selectedSekolah.nama_sekolah"></h3>
                <button type="button" @click="selectedSekolah = null" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-3 py-2.5 font-medium text-gray-600">Sesi</th>
                            <th class="text-left px-3 py-2.5 font-medium text-gray-600">Paket Ujian</th>
                            <th class="text-center px-3 py-2.5 font-medium text-gray-600">Total</th>
                            <th class="text-center px-3 py-2.5 font-medium text-gray-600">Hadir</th>
                            <th class="text-center px-3 py-2.5 font-medium text-gray-600">Selesai</th>
                            <th class="text-left px-3 py-2.5 font-medium text-gray-600">Waktu Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="sesi in selectedSekolah.sesi" :key="sesi.id">
                            <tr>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-gray-900" x-text="sesi.nama_sesi"></p>
                                    <p class="text-xs text-gray-400" x-text="sesi.ruang"></p>
                                </td>
                                <td class="px-3 py-2.5 text-gray-700" x-text="sesi.nama_paket"></td>
                                <td class="px-3 py-2.5 text-center" x-text="sesi.total_peserta"></td>
                                <td class="px-3 py-2.5 text-center text-blue-600 font-medium" x-text="sesi.hadir"></td>
                                <td class="px-3 py-2.5 text-center text-green-600 font-medium" x-text="sesi.selesai"></td>
                                <td class="px-3 py-2.5">
                                    <span class="font-mono text-sm"
                                          :class="sesi.menit_sisa <= 10 ? 'text-red-600 font-bold' : 'text-gray-700'"
                                          x-text="sesi.menit_sisa !== null ? sesi.menit_sisa + ' menit' : '–'">
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

</div>

<script>
function monitorSekolah() {
    return {
        loading: true,
        sekolahList: [],
        search: '',
        filterStatus: '',
        summary: { total_sekolah: 0, sekolah_aktif: 0, total_peserta_aktif: 0, total_selesai: 0 },
        selectedSekolah: null,
        timer: null,

        init() {
            this.fetchData();
            this.timer = setInterval(() => this.fetchData(), 10000);
            this.$watch('search', () => {});
            this.$watch('filterStatus', () => {});
        },

        destroy() {
            clearInterval(this.timer);
        },

        async fetchData() {
            try {
                const res = await fetch('{{ route('dinas.monitoring.sekolah.api') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.sekolahList = data.sekolah || [];
                this.summary     = data.summary || this.summary;
                this.loading     = false;
            } catch (e) {
                this.loading = false;
            }
        },

        get filteredSekolah() {
            return this.sekolahList.filter(s => {
                const matchSearch = !this.search ||
                    s.nama_sekolah.toLowerCase().includes(this.search.toLowerCase()) ||
                    s.kode_sekolah.toLowerCase().includes(this.search.toLowerCase());
                const matchStatus = !this.filterStatus || s.status === this.filterStatus;
                return matchSearch && matchStatus;
            });
        },

        async showDetail(sekolah) {
            if (this.selectedSekolah?.id === sekolah.id) {
                this.selectedSekolah = null;
                return;
            }
            this.selectedSekolah = { ...sekolah, sesi: [] };
            try {
                const res = await fetch(`/dinas/monitoring/sesi?sekolah_id=${sekolah.id}&json=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.selectedSekolah.sesi = data.sesi || [];
            } catch (e) { console.warn('monitoring:detail', e); }
        }
    };
}
</script>
@endsection
