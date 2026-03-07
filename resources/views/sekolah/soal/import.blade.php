@extends('layouts.admin')

@section('title', 'Import Soal')

@section('breadcrumb')
    <a href="{{ route('sekolah.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Import Soal</span>
@endsection

@section('page-content')
<div x-data="importSoal()" class="space-y-5">

    {{-- Header info --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-blue-800">
            <p class="font-semibold mb-1">Panduan Import Soal</p>
            <ul class="list-disc list-inside space-y-0.5 text-blue-700">
                <li>Format Excel (.xlsx): gunakan template resmi, satu baris per soal</li>
                <li>Format Word (.docx): gunakan template resmi dengan penanda <code class="bg-blue-100 px-1 rounded">[SOAL]</code>, <code class="bg-blue-100 px-1 rounded">[A]</code>–<code class="bg-blue-100 px-1 rounded">[E]</code>, <code class="bg-blue-100 px-1 rounded">[JAWABAN]</code></li>
                <li>Maksimal 500 soal per file</li>
                <li>Gambar dalam dokumen Word akan diimpor otomatis</li>
            </ul>
        </div>
    </div>

    {{-- Download Template --}}
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-4">Unduh Template</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            <a href="{{ asset('templates/template_soal.xlsx') }}" download
               class="flex items-center gap-3 border border-gray-200 hover:border-green-400 hover:bg-green-50 rounded-xl p-4 transition-colors group">
                <div class="w-10 h-10 bg-green-100 group-hover:bg-green-200 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Template Excel</p>
                    <p class="text-xs text-gray-500">template_soal.xlsx</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </a>

            <a href="{{ asset('templates/template_soal.docx') }}" download
               class="flex items-center gap-3 border border-gray-200 hover:border-blue-400 hover:bg-blue-50 rounded-xl p-4 transition-colors group">
                <div class="w-10 h-10 bg-blue-100 group-hover:bg-blue-200 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg class="w-5 h-5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Template Word</p>
                    <p class="text-xs text-gray-500">template_soal.docx</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Form Upload --}}
    <div class="card space-y-5">
        <h2 class="font-semibold text-gray-900">Upload File Soal</h2>

        @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-800 flex gap-2">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('sekolah.soal.import.excel') }}" method="POST" enctype="multipart/form-data"
              :action="fileExt === 'docx' || fileExt === 'doc' ? '{{ route('sekolah.soal.import.word') }}' : '{{ route('sekolah.soal.import.excel') }}'"
              @submit="submitting = true">
            @csrf

            {{-- Drop zone --}}
            <div class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                 :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
                 @dragover.prevent="dragging = true"
                 @dragleave="dragging = false"
                 @drop.prevent="handleDrop($event)">
                <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-sm text-gray-600 mb-1">
                    <span x-text="fileName || 'Seret file ke sini atau'"></span>
                    <template x-if="!fileName">
                        <label class="text-blue-600 hover:text-blue-800 cursor-pointer font-medium ml-1">
                            pilih file
                            <input type="file" name="file" accept=".xlsx,.xls,.docx,.doc" class="sr-only"
                                   @change="handleFile($event)" ref="fileInput">
                        </label>
                    </template>
                </p>
                <p class="text-xs text-gray-400">.xlsx, .xls, .docx — maks 10MB</p>
                <template x-if="fileName">
                    <button type="button" @click="clearFile()"
                            class="mt-2 text-xs text-red-500 hover:text-red-700 underline">
                        Ganti file
                    </button>
                </template>
            </div>

            {{-- Metadata --}}
            <div class="grid sm:grid-cols-2 gap-4 mt-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori Soal</label>
                    <select name="kategori_soal_id"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Tanpa Kategori —</option>
                        @foreach($kategoris as $kat)
                        <option value="{{ $kat->id }}">{{ $kat->nama_kategori }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Tingkat Kelas</label>
                    <select name="tingkat_kelas"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">—</option>
                        @foreach(['SD', 'SMP', 'SMA'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mata Pelajaran</label>
                    <input type="text" name="mata_pelajaran"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Misal: Matematika">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis Soal Default</label>
                    <select name="jenis_soal_default"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="pilihan_ganda">Pilihan Ganda</option>
                        <option value="pilihan_ganda_kompleks">PG Kompleks</option>
                        <option value="isian">Isian</option>
                        <option value="essay">Essay</option>
                        <option value="menjodohkan">Menjodohkan</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="!fileName || submitting"
                        class="btn-primary flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="!submitting">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </template>
                    <template x-if="submitting">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </template>
                    <span x-text="submitting ? 'Memproses...' : 'Import Soal'"></span>
                </button>
                <a href="{{ route('sekolah.soal.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>

    {{-- Riwayat Import --}}
    @if(isset($importJobs) && $importJobs->count())
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-4">Riwayat Import</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 font-medium text-gray-600">File</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Tipe</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600">Total</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600">Berhasil</th>
                        <th class="text-center px-4 py-3 font-medium text-gray-600">Gagal</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($importJobs as $job)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate">{{ $job->nama_file }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ $job->tipe_file === 'excel' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ strtoupper($job->tipe_file) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ $job->total_rows ?? '–' }}</td>
                        <td class="px-4 py-3 text-center text-green-700 font-medium">{{ $job->berhasil ?? '–' }}</td>
                        <td class="px-4 py-3 text-center text-red-600">{{ $job->gagal ?? '–' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusClasses = [
                                    'pending'    => 'bg-yellow-100 text-yellow-800',
                                    'processing' => 'bg-blue-100 text-blue-800',
                                    'done'       => 'bg-green-100 text-green-800',
                                    'failed'     => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClasses[$job->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($job->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $job->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

<script>
function importSoal() {
    return {
        fileName: '',
        fileExt: '',
        dragging: false,
        submitting: false,

        handleFile(e) {
            const file = e.target.files[0];
            if (file) {
                this.fileName = file.name;
                this.fileExt = file.name.split('.').pop().toLowerCase();
            }
        },
        handleDrop(e) {
            this.dragging = false;
            const file = e.dataTransfer.files[0];
            if (!file) return;
            const allowed = ['.xlsx', '.xls', '.docx', '.doc'];
            const ext = '.' + file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(ext)) {
                alert('Format file tidak didukung. Gunakan .xlsx, .xls, atau .docx');
                return;
            }
            this.fileName = file.name;
            this.fileExt = ext.replace('.', '');
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
        },
        clearFile() {
            this.fileName = '';
            this.fileExt = '';
            this.$refs.fileInput.value = '';
        }
    };
}
</script>
@endsection
