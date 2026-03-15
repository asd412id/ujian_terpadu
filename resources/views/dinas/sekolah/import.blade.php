@extends('layouts.admin')

@section('title', 'Import Data Sekolah')

@section('breadcrumb')
    <a href="{{ route('dinas.sekolah.index') }}" class="text-gray-500 hover:text-blue-600">Data Sekolah</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Import Excel</span>
@endsection

@section('page-content')
<div class="space-y-5 max-w-2xl" x-data="{ mode: 'update' }">

    <h1 class="text-xl font-bold text-gray-900">Import Data Sekolah</h1>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">
        {{ session('error') }}
    </div>
    @endif

    {{-- Progress bar (muncul jika ada job aktif) --}}
    @if(session('import_job_id'))
    @php
        $importJobStatusUrl = route('dinas.sekolah.import.status', session('import_job_id'));
        $importRedirectUrl  = route('dinas.sekolah.index');
    @endphp
    <div class="card bg-amber-50 border-amber-200 space-y-3"
         x-data="importProgressCard"
         data-status-url="{{ $importJobStatusUrl }}"
         data-redirect-url="{{ $importRedirectUrl }}"
         x-init="startPolling($el.dataset.statusUrl, $el.dataset.redirectUrl)">
        <div class="flex items-center justify-between text-sm font-semibold text-amber-800">
            <span>Proses Import</span>
            <span x-text="statusLabel"></span>
        </div>
        <div class="w-full bg-amber-200 rounded-full h-2.5">
            <div class="bg-amber-500 h-2.5 rounded-full transition-all duration-300"
                 :style="'width:' + progress + '%'"></div>
        </div>
        <p class="text-xs text-amber-700" x-text="statusText"></p>
        <template x-if="errors.length">
            <div class="bg-white border border-amber-200 rounded-xl p-3 space-y-1">
                <p class="text-xs font-semibold text-red-600">Detail error:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <template x-for="err in errors.slice(0, 20)" :key="err.baris">
                        <li class="text-xs text-red-500">Baris <span x-text="err.baris"></span>: <span x-text="err.pesan"></span></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>
    @endif

    {{-- Template Download --}}
    <div class="card bg-blue-50 border-blue-200">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-blue-800">Template Excel</p>
                <p class="text-xs text-blue-600 mt-0.5">Gunakan template ini agar format kolom sesuai</p>
                <a href="{{ route('dinas.sekolah.import.template') }}"
                   class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-blue-700 hover:text-blue-900 underline">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download Template
                </a>
            </div>
        </div>
    </div>

    {{-- Format Kolom --}}
    <div class="card">
        <p class="text-sm font-semibold text-gray-700 mb-3">Format Kolom Excel</p>
        <div class="overflow-x-auto">
            <table class="text-xs w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-600">Kolom</th>
                        <th class="border border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-600">Nama Field</th>
                        <th class="border border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-600">Wajib</th>
                        <th class="border border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-600">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">A</td><td class="border border-gray-200 px-3 py-1.5">nama</td><td class="border border-gray-200 px-3 py-1.5 text-red-600 font-medium">Ya</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Nama lengkap sekolah</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">B</td><td class="border border-gray-200 px-3 py-1.5">npsn</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Nomor Pokok Sekolah Nasional (8 digit). <span class="font-semibold text-amber-700">Wajib untuk mode Update</span></td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">C</td><td class="border border-gray-200 px-3 py-1.5">jenjang</td><td class="border border-gray-200 px-3 py-1.5 text-red-600 font-medium">Ya</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">SD / SMP / SMA / SMK / MA / MTs / MI</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">D</td><td class="border border-gray-200 px-3 py-1.5">alamat</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Alamat sekolah</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">E</td><td class="border border-gray-200 px-3 py-1.5">kota</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Kota / Kabupaten</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">F</td><td class="border border-gray-200 px-3 py-1.5">telepon</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Nomor telepon sekolah</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">G</td><td class="border border-gray-200 px-3 py-1.5">email</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Email sekolah</td></tr>
                    <tr><td class="border border-gray-200 px-3 py-1.5 font-mono font-bold text-blue-600">H</td><td class="border border-gray-200 px-3 py-1.5">kepala_sekolah</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">—</td><td class="border border-gray-200 px-3 py-1.5 text-gray-500">Nama kepala sekolah</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">Baris pertama adalah header, data dimulai dari baris ke-2.</p>
    </div>

    {{-- Form Upload --}}
    <div class="card">
        <form method="POST" action="{{ route('dinas.sekolah.import.post') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- Mode Import --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">Mode Import</label>
                <div class="space-y-3">

                    <label class="flex items-start gap-3 p-3 border-2 rounded-xl cursor-pointer transition"
                           :class="mode === 'update' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="mode" value="update" x-model="mode" class="mt-0.5 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Update / Tambah Baru</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Jika NPSN sudah ada → data sekolah diperbarui.<br>
                                Jika NPSN belum ada → sekolah baru ditambahkan.<br>
                                <span class="text-amber-600 font-medium">Baris tanpa NPSN akan dilewati.</span>
                            </p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 border-2 rounded-xl cursor-pointer transition"
                           :class="mode === 'replace_all' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="mode" value="replace_all" x-model="mode" class="mt-0.5 text-red-600">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Hapus Semua & Import Baru</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Seluruh data sekolah yang ada dihapus, lalu diisi ulang dari file Excel.
                            </p>
                        </div>
                    </label>

                </div>
            </div>

            {{-- Peringatan mode replace_all --}}
            <div x-show="mode === 'replace_all'" x-transition
                 class="bg-red-50 border border-red-300 rounded-xl p-4 text-sm text-red-800">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-bold">PERINGATAN: Tindakan Tidak Dapat Dibatalkan</p>
                        <p class="mt-1">Mode ini akan <span class="font-bold underline">MENGHAPUS PERMANEN</span> seluruh data berikut:</p>
                        <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
                            <li>Semua data sekolah</li>
                            <li>Semua data peserta ujian beserta akun login mereka</li>
                            <li>Semua sesi ujian dan hasil jawaban peserta</li>
                        </ul>
                        <p class="mt-2 font-semibold">Pastikan Anda telah memiliki backup data sebelum melanjutkan.</p>
                    </div>
                </div>
            </div>

            {{-- File Upload --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    File Excel <span class="text-red-500">*</span>
                </label>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2
                              file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0
                              file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700
                              hover:file:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Format: .xlsx atau .xls, maks. 10 MB</p>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="btn-primary inline-flex items-center gap-2"
                        x-bind:class="mode === 'replace_all' ? '!bg-red-600 hover:!bg-red-700' : ''">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span x-text="mode === 'replace_all' ? 'Hapus Semua & Import' : 'Mulai Import'"></span>
                </button>
                <a href="{{ route('dinas.sekolah.index') }}" class="btn-secondary">Batal</a>
            </div>

        </form>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('importProgressCard', () => ({
        progress: 0,
        statusLabel: 'Menunggu...',
        statusText: '',
        catatan: '',
        errors: [],
        polling: null,
        redirectUrl: null,
        startPolling(statusUrl, redirectUrl) {
            this.redirectUrl = redirectUrl || null;
            this.polling = setInterval(() => this.checkStatus(statusUrl), 1500);
        },
        destroy() {
            clearInterval(this.polling);
        },
        async checkStatus(url) {
            try {
                const res  = await fetch(url);
                if (!res.ok) return;
                const data = await res.json();
                this.errors  = data.errors || [];
                this.catatan = data.catatan || '';

                if (data.status === 'selesai') {
                    clearInterval(this.polling);
                    this.progress    = 100;
                    this.statusLabel = 'Selesai';
                    this.statusText  = `Berhasil: ${data.success_rows} baris. Gagal: ${data.error_rows} baris. User operator otomatis dibuat.`;
                    if (this.redirectUrl && data.error_rows === 0) {
                        setTimeout(() => { window.location.href = this.redirectUrl; }, 2000);
                    }
                } else if (data.status === 'gagal') {
                    clearInterval(this.polling);
                    this.progress    = 100;
                    this.statusLabel = 'Gagal';
                    this.statusText  = this.catatan || 'Import gagal. Periksa file Excel Anda dan coba lagi.';
                } else if (data.status === 'processing') {
                    const pct        = data.total_rows > 0
                        ? Math.round((data.processed_rows / data.total_rows) * 100)
                        : 10;
                    this.progress    = pct;
                    this.statusLabel = 'Memproses...';
                    this.statusText  = `${data.processed_rows} / ${data.total_rows} baris`;
                } else {
                    this.statusLabel = 'Antrian...';
                    this.statusText  = 'Menunggu proses dimulai';
                    this.progress    = 5;
                }
            } catch (e) {
                // silent
            }
        }
    }));
});
</script>
@endpush
@endsection
