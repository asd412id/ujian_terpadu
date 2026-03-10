@extends('layouts.admin')

@section('title', 'Import Peserta')

@section('breadcrumb')
    <a href="{{ route('dinas.peserta.index') }}" class="text-gray-500 hover:text-blue-600">Peserta</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Import Excel</span>
@endsection

@section('page-content')
<div class="space-y-5 max-w-2xl" x-data="{ mode: 'update', confirm_replace: false }">

    <h1 class="text-xl font-bold text-gray-900">Import Data Peserta</h1>

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
                <p class="text-xs text-blue-600 mt-0.5">Gunakan template ini agar format kolom sesuai.</p>
                <a href="{{ route('dinas.peserta.import.template') }}"
                   class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-blue-700 hover:text-blue-900 underline">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Unduh Template Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Format Kolom --}}
    <div class="card">
        <p class="text-sm font-semibold text-gray-800 mb-2">Format Kolom</p>
        <div class="overflow-x-auto">
            <table class="text-xs w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-left px-3 py-2 border border-gray-200">Kolom</th>
                        <th class="text-left px-3 py-2 border border-gray-200">Keterangan</th>
                        <th class="text-left px-3 py-2 border border-gray-200">Wajib?</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['nama',         'Nama lengkap peserta',               'Ya'],
                        ['nis',          'Nomor Induk Siswa (jadi username)',   'Tidak'],
                        ['nisn',         'NISN 10 digit',                      'Tidak'],
                        ['kelas',        'Nama kelas, misal: XII IPA 1',       'Tidak'],
                        ['jurusan',      'Jurusan / program studi',            'Tidak'],
                        ['jenis_kelamin','L atau P',                           'Tidak'],
                        ['tanggal_lahir','Format: YYYY-MM-DD (2006-05-20)',    'Tidak'],
                    ] as [$col, $ket, $wajib])
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 border border-gray-200 font-mono text-blue-700">{{ $col }}</td>
                        <td class="px-3 py-1.5 border border-gray-200 text-gray-600">{{ $ket }}</td>
                        <td class="px-3 py-1.5 border border-gray-200 font-medium {{ $wajib === 'Ya' ? 'text-red-600' : 'text-gray-400' }}">{{ $wajib }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Progress bar (muncul jika ada job aktif) --}}
    @if(session('import_job_id'))
    @php
        $importJobStatusUrl = route('dinas.peserta.import.status', session('import_job_id'));
        $importRedirectUrl  = route('dinas.peserta.index', array_filter(['sekolah_id' => $selectedSekolahId ?? null]));
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

    {{-- Upload Form --}}
    <form action="{{ route('dinas.peserta.import.post') }}" method="POST" enctype="multipart/form-data"
          class="card space-y-5">
        @csrf

        {{-- Pilih Sekolah --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah <span class="text-red-500">*</span></label>
            <select name="sekolah_id" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('sekolah_id') border-red-400 @enderror">
                <option value="">— Pilih Sekolah —</option>
                @foreach($sekolahList as $s)
                <option value="{{ $s->id }}" {{ ($selectedSekolahId == $s->id || old('sekolah_id') == $s->id) ? 'selected' : '' }}>
                    [{{ $s->jenjang }}] {{ $s->nama }}
                </option>
                @endforeach
            </select>
            @error('sekolah_id')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Mode Import --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode Import</label>
            <div class="space-y-2">
                <label class="flex items-start gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors"
                       :class="mode === 'update' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                       @click="mode = 'update'">
                    <input type="radio" name="mode" value="update" x-model="mode" class="mt-0.5 text-blue-600">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Tambah / Perbarui</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Peserta baru ditambahkan. Jika NIS sudah ada, baris dilewati (tidak dihapus).
                        </p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors"
                       :class="mode === 'replace_all' ? 'border-red-500 bg-red-50' : 'border-gray-200'"
                       @click="mode = 'replace_all'">
                    <input type="radio" name="mode" value="replace_all" x-model="mode" class="mt-0.5 text-red-600">
                    <div>
                        <p class="text-sm font-medium text-red-700">Ganti Semua Data</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Seluruh peserta sekolah terpilih dihapus, kemudian diganti data dari file Excel.
                        </p>
                    </div>
                </label>
            </div>

            {{-- Konfirmasi replace_all --}}
            <div x-show="mode === 'replace_all'" x-cloak
                 class="flex items-start gap-2 p-3 mt-2 bg-red-50 border border-red-200 rounded-xl">
                <input type="checkbox" id="confirm_replace" x-model="confirm_replace"
                       class="mt-0.5 w-4 h-4 rounded border-red-300 text-red-600 focus:ring-red-500 shrink-0">
                <label for="confirm_replace" class="text-xs text-red-700 cursor-pointer leading-relaxed">
                    Saya mengerti bahwa <strong>seluruh data peserta sekolah terpilih akan dihapus permanen</strong>
                    dan diganti dengan data dari file Excel ini.
                </label>
            </div>
        </div>

        {{-- Upload File --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">File Excel (.xlsx / .xls)</label>
            <input type="file" name="file" accept=".xlsx,.xls" required
                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                          file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100 border border-gray-300 rounded-xl p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('file')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-400 mt-1">Maks. 10 MB</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    :disabled="mode === 'replace_all' && !confirm_replace"
                    class="btn-primary flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Mulai Import
            </button>
            <a href="{{ route('dinas.peserta.index') }}"
               class="btn-secondary">
                Kembali
            </a>
        </div>
    </form>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('importProgressCard', () => ({
        progress: 0,
        statusLabel: 'Menunggu...',
        statusText: '',
        errors: [],
        polling: null,
        redirectUrl: null,
        startPolling(statusUrl, redirectUrl) {
            this.redirectUrl = redirectUrl || null;
            this.polling = setInterval(() => this.checkStatus(statusUrl), 1500);
        },
        async checkStatus(url) {
            try {
                const res  = await fetch(url);
                const data = await res.json();
                this.errors = data.errors || [];

                if (data.status === 'selesai') {
                    clearInterval(this.polling);
                    this.progress    = 100;
                    this.statusLabel = 'Selesai';
                    this.statusText  = `Berhasil: ${data.success_rows} baris. Gagal: ${data.error_rows} baris.`;
                    // Auto-redirect ke halaman peserta setelah 2 detik agar data sudah tersedia
                    if (this.redirectUrl && data.error_rows === 0) {
                        setTimeout(() => { window.location.href = this.redirectUrl; }, 2000);
                    }
                } else if (data.status === 'gagal') {
                    clearInterval(this.polling);
                    this.statusLabel = 'Gagal';
                    this.statusText  = 'Import gagal. Lihat detail error di bawah.';
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
