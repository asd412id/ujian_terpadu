@extends('layouts.admin')

@section('title', 'Import Soal')

@section('breadcrumb')
    <a href="{{ route('dinas.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Import Soal</span>
@endsection

@section('page-content')
<div class="space-y-6" x-data="importSoal()">

    {{-- Upload Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Import Soal dari File</h2>
        <p class="text-sm text-gray-500 mb-6">Upload file Word (.docx) atau ZIP (.zip, untuk soal bergambar) berisi data soal untuk diimport secara massal.</p>

        {{-- Tab Selector --}}
        <div class="flex rounded-lg bg-gray-100 p-1 mb-6 w-fit">
            <button type="button" @click="activeTab = 'word'"
                    :class="activeTab === 'word' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Word (.docx)
                </span>
            </button>
            <button type="button" @click="activeTab = 'zip'"
                    :class="activeTab === 'zip' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    ZIP + Gambar (.zip)
                </span>
            </button>
        </div>

        {{-- Tab Description --}}
        <div class="mb-4">
            <template x-if="activeTab === 'word'">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                    <strong>Word (.docx)</strong> — Untuk soal tanpa gambar atau dengan gambar yang langsung disisipkan di dokumen Word (Insert > Pictures).
                </div>
            </template>
            <template x-if="activeTab === 'zip'">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                    <strong>ZIP (.zip)</strong> — Untuk soal dengan gambar. ZIP berisi file .docx dan folder <code class="bg-amber-100 px-1 rounded">gambar/</code> berisi file-file gambar yang direferensikan di Word.
                </div>
            </template>
        </div>

        {{-- Kategori --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Soal <span class="text-red-500">*</span></label>
            <select x-model="kategoriId"
                    class="w-full sm:w-72 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Pilih Kategori</option>
                @foreach($kategori as $kat)
                <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                @endforeach
            </select>
        </div>

        {{-- Drag & Drop Zone --}}
        <div class="relative border-2 border-dashed rounded-xl p-8 text-center transition-colors"
             :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50 hover:border-gray-400'"
             @dragover.prevent="dragOver = true"
             @dragleave.prevent="dragOver = false"
             @drop.prevent="handleDrop($event)">

            <template x-if="!selectedFile">
                <div>
                    <svg class="mx-auto w-12 h-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-sm text-gray-600 mb-1">Drag & drop file di sini, atau</p>
                    <label class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        Pilih file
                        <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" :accept="activeTab === 'word' ? '.docx' : '.zip'" class="hidden">
                    </label>
                    <p class="text-xs text-gray-400 mt-2" x-text="activeTab === 'word' ? 'Format: .docx (maks 50MB)' : 'Format: .zip berisi .docx + folder gambar/ (maks 100MB)'"></p>
                </div>
            </template>

            <template x-if="selectedFile">
                <div class="flex items-center justify-between bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="activeTab === 'word' ? 'bg-blue-100' : 'bg-amber-100'">
                            <svg class="w-5 h-5" :class="activeTab === 'word' ? 'text-blue-600' : 'text-amber-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900" x-text="selectedFile.name"></p>
                            <p class="text-xs text-gray-500" x-text="formatSize(selectedFile.size)"></p>
                        </div>
                    </div>
                    <button @click="clearFile" class="text-gray-400 hover:text-red-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Upload Button --}}
        <div class="mt-4 flex items-center gap-3">
            <button @click="startUpload"
                    :disabled="!selectedFile || !kategoriId || uploading"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                <template x-if="!uploading">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </template>
                <template x-if="uploading">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </template>
                <span x-text="uploading ? 'Mengupload...' : 'Mulai Import'"></span>
            </button>
            <p class="text-xs text-gray-400" x-show="!kategoriId && selectedFile">Pilih kategori terlebih dahulu.</p>
        </div>

        {{-- Progress Section --}}
        <template x-if="jobId">
            <div class="mt-6 border-t pt-5 space-y-3">
                <div class="flex items-center gap-2">
                    <template x-if="jobStatus === 'processing'">
                        <span class="inline-flex items-center gap-1.5 text-blue-700 bg-blue-50 px-2.5 py-1 rounded-full text-xs font-medium">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Memproses...
                        </span>
                    </template>
                    <template x-if="jobStatus === 'selesai'">
                        <span class="inline-flex items-center gap-1.5 text-green-700 bg-green-50 px-2.5 py-1 rounded-full text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Import Selesai
                        </span>
                    </template>
                    <template x-if="jobStatus === 'gagal'">
                        <span class="inline-flex items-center gap-1.5 text-red-700 bg-red-50 px-2.5 py-1 rounded-full text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            Import Gagal
                        </span>
                    </template>
                    <span class="text-xs text-gray-500" x-show="jobTotal > 0" x-text="jobProcessed + ' / ' + jobTotal + ' soal'"></span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden" x-show="jobStatus === 'processing'">
                    <div class="bg-blue-600 h-full rounded-full transition-all duration-300" :style="'width:' + jobProgress + '%'"></div>
                </div>

                <p class="text-sm text-gray-600" x-show="jobMessage" x-text="jobMessage"></p>

                <template x-if="jobErrors.length > 0">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-red-800 mb-1">Error:</p>
                        <ul class="text-xs text-red-700 space-y-0.5 list-disc list-inside">
                            <template x-for="err in jobErrors" :key="err">
                                <li x-text="err"></li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Download Template --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Download Template</h3>
        <p class="text-sm text-gray-500 mb-4">Gunakan template berikut agar format file sesuai dan import berjalan lancar.</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dinas.soal.import.template.word') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Template Word (.docx)
            </a>
            <a href="{{ route('dinas.soal.import.template.zip') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Template ZIP + Gambar (.zip)
            </a>
        </div>
    </div>

    {{-- Format Guide --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Panduan Format Penulisan Soal</h3>
        <div class="grid gap-4 md:grid-cols-2">

            {{-- PG --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span> Pilihan Ganda
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>1. Apa ibu kota Indonesia?</strong></p>
                    <p class="pl-4">a. Bandung</p>
                    <p class="pl-4">b. Surabaya</p>
                    <p class="pl-4">c. Jakarta</p>
                    <p class="pl-4">d. Yogyakarta</p>
                    <p class="pl-4">Jawaban: C</p>
                </div>
            </div>

            {{-- PG Gambar Opsi --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span> PG + Gambar Opsi (ZIP)
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>2. Manakah bendera Indonesia?</strong></p>
                    <p class="pl-4 text-amber-600">a. Jepang | gambar: bendera_jp.png</p>
                    <p class="pl-4 text-amber-600">b. Indonesia | gambar: bendera_id.png</p>
                    <p class="pl-4 text-amber-600">c. Thailand | gambar: bendera_th.png</p>
                    <p class="pl-4">Jawaban: B</p>
                </div>
            </div>

            {{-- PG Kompleks --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-indigo-500 rounded-full"></span> PG Kompleks (banyak jawaban benar)
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>3. [PG_KOMPLEKS] Bilangan prima?</strong></p>
                    <p class="pl-4">a. 2</p>
                    <p class="pl-4">b. 4</p>
                    <p class="pl-4">c. 7</p>
                    <p class="pl-4">d. 9</p>
                    <p class="pl-4">Jawaban: A,C</p>
                </div>
            </div>

            {{-- Menjodohkan --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-pink-500 rounded-full"></span> Menjodohkan
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>4. [MENJODOHKAN] Jodohkan negara:</strong></p>
                    <p class="pl-4">Indonesia = Jakarta</p>
                    <p class="pl-4">Jepang = Tokyo</p>
                    <p class="pl-4">Thailand = Bangkok</p>
                </div>
            </div>

            {{-- Isian --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span> Isian Singkat
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>5. [ISIAN] Ibu kota Jepang adalah ___</strong></p>
                    <p class="pl-4">Jawaban: Tokyo</p>
                </div>
            </div>

            {{-- Essay --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-amber-500 rounded-full"></span> Essay
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>6. [ESSAY] Jelaskan proses hujan!</strong></p>
                    <p class="pl-4">Jawaban: Evaporasi, kondensasi, presipitasi.</p>
                </div>
            </div>

            {{-- Benar/Salah --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-indigo-500 rounded-full"></span> Benar / Salah
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-700 space-y-1 border">
                    <p><strong>7. [BENAR_SALAH] Tentukan benar/salah:</strong></p>
                    <p class="pl-4">1) Air mendidih pada 100°C (BENAR)</p>
                    <p class="pl-4">2) Es lebih berat dari air (SALAH)</p>
                    <p class="pl-4">3) H2O adalah garam dapur (SALAH)</p>
                </div>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                <strong>Catatan:</strong>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    <li>Tandai jenis soal dengan tag <code class="bg-blue-100 px-1 rounded">[PG_KOMPLEKS]</code>, <code class="bg-blue-100 px-1 rounded">[MENJODOHKAN]</code>, <code class="bg-blue-100 px-1 rounded">[ISIAN]</code>, <code class="bg-blue-100 px-1 rounded">[ESSAY]</code>, atau <code class="bg-blue-100 px-1 rounded">[BENAR_SALAH]</code> setelah nomor soal.</li>
                    <li>Soal tanpa tag dan memiliki opsi a/b/c/d otomatis dianggap Pilihan Ganda.</li>
                    <li>Soal tanpa tag dan tanpa opsi otomatis dianggap Essay.</li>
                    <li>Untuk menjodohkan, gunakan tanda <code class="bg-blue-100 px-1 rounded">=</code> untuk memisahkan pasangan kiri dan kanan.</li>
                    <li>Untuk Benar/Salah, gunakan format: <code class="bg-blue-100 px-1 rounded">1) Pernyataan (BENAR)</code> atau <code class="bg-blue-100 px-1 rounded">1) Pernyataan (SALAH)</code></li>
                    <li>Tag opsional tingkat kesulitan: <code class="bg-blue-100 px-1 rounded">[tingkat: mudah]</code>, <code class="bg-blue-100 px-1 rounded">[tingkat: sedang]</code>, atau <code class="bg-blue-100 px-1 rounded">[tingkat: sulit]</code> &mdash; default: sedang.</li>
                    <li>Tag opsional bobot nilai: <code class="bg-blue-100 px-1 rounded">[bobot: 2]</code> &mdash; default: 1. Bisa ditaruh di baris soal atau baris terpisah.</li>
                </ul>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                <strong>Soal Bergambar (format ZIP):</strong>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    <li>Gambar soal: tulis <code class="bg-amber-100 px-1 rounded">[gambar: namafile.png]</code> di baris pertanyaan.</li>
                    <li>Gambar opsi: tulis <code class="bg-amber-100 px-1 rounded">a. Teks opsi | gambar: namafile.png</code></li>
                    <li>Masukkan file gambar ke folder <code class="bg-amber-100 px-1 rounded">gambar/</code> dalam ZIP.</li>
                    <li>Struktur ZIP: <code class="bg-amber-100 px-1 rounded">soal.zip > template_soal.docx + gambar/...</code></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Import History --}}
    @if($importJobs->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Riwayat Import</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600">File</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600">Tipe</th>
                        <th class="px-4 py-2.5 text-center font-medium text-gray-600">Status</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600">Progress</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($importJobs as $job)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-900 font-medium text-xs">{{ Str::limit($job->filename, 30) }}</td>
                        <td class="px-4 py-2.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $job->tipe === 'soal_word' ? 'bg-blue-50 text-blue-700' : 'bg-gray-50 text-gray-700' }}">
                                {{ $job->tipe === 'soal_word' ? 'Word' : $job->tipe }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @switch($job->status)
                                @case('pending')
                                    <span class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full text-xs font-medium">Antrian</span>
                                    @break
                                @case('processing')
                                    <span class="inline-flex items-center gap-1 text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full text-xs font-medium">Diproses</span>
                                    @break
                                @case('selesai')
                                    <span class="inline-flex items-center gap-1 text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Selesai</span>
                                    @break
                                @case('gagal')
                                    <span class="inline-flex items-center gap-1 text-red-700 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Gagal</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $job->processed_rows ?? 0 }}/{{ $job->total_rows ?? 0 }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $job->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
function importSoal() {
    return {
        activeTab: 'word',
        kategoriId: '',
        selectedFile: null,
        dragOver: false,
        uploading: false,
        jobId: null,
        jobStatus: null,
        jobProgress: 0,
        jobTotal: 0,
        jobProcessed: 0,
        jobMessage: '',
        jobErrors: [],
        pollInterval: null,

        handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) this.selectedFile = file;
        },

        handleDrop(e) {
            this.dragOver = false;
            const file = e.dataTransfer.files[0];
            if (file) {
                this.selectedFile = file;
                this.$refs.fileInput.files = e.dataTransfer.files;
            }
        },

        clearFile() {
            this.selectedFile = null;
            this.$refs.fileInput.value = '';
        },

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        async startUpload() {
            if (!this.selectedFile || !this.kategoriId) return;
            this.uploading = true;
            this.jobId = null;
            this.jobStatus = null;
            this.jobProgress = 0;
            this.jobErrors = [];

            const formData = new FormData();
            formData.append('file', this.selectedFile);
            formData.append('kategori_soal_id', this.kategoriId);

            const url = this.activeTab === 'zip'
                ? '{{ route("dinas.soal.import.zip") }}'
                : '{{ route("dinas.soal.import.word") }}';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Upload gagal');
                }

                this.jobId = data.job_id;
                this.jobStatus = 'processing';
                this.startPolling();

            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                this.uploading = false;
            }
        },

        startPolling() {
            this.pollInterval = setInterval(() => this.checkStatus(), 2000);
        },

        async checkStatus() {
            if (!this.jobId) return;

            try {
                const response = await fetch(`{{ url('dinas/soal/import/status') }}/${this.jobId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                this.jobStatus = data.status;
                this.jobProgress = data.progress || 0;
                this.jobTotal = data.total_rows || 0;
                this.jobProcessed = data.processed_rows || 0;
                this.jobMessage = data.message || '';
                this.jobErrors = data.errors || [];

                if (data.status === 'selesai' || data.status === 'gagal') {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }
    }
}
</script>
@endpush
