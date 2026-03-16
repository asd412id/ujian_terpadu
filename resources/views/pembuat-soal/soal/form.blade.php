@extends('layouts.admin')

@section('title', isset($soal) ? 'Edit Soal' : 'Tambah Soal')

@section('breadcrumb')
    <a href="{{ route('pembuat-soal.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($soal) ? 'Edit Soal' : 'Tambah Soal' }}</span>
@endsection

@section('page-content')
<div x-data="soalForm()" x-init="init()">
<form action="{{ isset($soal) ? route('pembuat-soal.soal.update', $soal->id) : route('pembuat-soal.soal.store') }}"
      method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if(isset($soal)) @method('PUT') @endif

    @if(isset($soal) && $soal->is_verified)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <strong>Perhatian:</strong> Soal ini sudah terverifikasi. Mengedit soal akan mereset status verifikasi dan perlu diverifikasi ulang oleh admin.
    </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-5">

        {{-- LEFT: Isi Soal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Pertanyaan --}}
            <div class="card space-y-4">
                <h2 class="font-semibold text-gray-900">Soal</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Pertanyaan <span class="text-red-500">*</span>
                    </label>
                    <div x-data="richEditor({
                        name: 'pertanyaan',
                        content: @js(old('pertanyaan', $soal->pertanyaan ?? '')),
                        placeholder: 'Tuliskan pertanyaan di sini... (Ctrl+V untuk paste gambar)',
                        uploadUrl: '{{ route('pembuat-soal.soal.upload-image') }}',
                        minimal: false
                    })">
                        <div class="ck-editor-wrap">
                            <div x-ref="editorEl"></div>
                        </div>
                        <input type="hidden" name="pertanyaan" x-ref="hiddenInput">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Paste gambar langsung dari clipboard (Ctrl+V). Mendukung LaTeX: tulis $x^2$ atau \(x^2\) untuk rumus matematika.</p>
                    <div class="mt-2">
                        @include('dinas.soal._panduan-rumus')
                    </div>
                </div>
            </div>

            {{-- Pilihan Jawaban (dinamis by jenis) --}}
            <div class="card space-y-4" x-show="jenis === 'pilihan_ganda' || jenis === 'pilihan_ganda_kompleks'" x-transition>
                <h2 class="font-semibold text-gray-900">Pilihan Jawaban</h2>
                <p x-show="jenis === 'pilihan_ganda'" class="text-xs text-gray-500">Pilih satu opsi yang benar.</p>
                <p x-show="jenis === 'pilihan_ganda_kompleks'" class="text-xs text-gray-500">Pilih satu atau lebih opsi yang benar.</p>

                <template x-for="(opsi, idx) in opsiList" :key="idx">
                    <div class="flex items-start gap-3">
                        {{-- Radio/Checkbox --}}
                        <div class="flex-shrink-0 mt-0.5">
                            <template x-if="jenis === 'pilihan_ganda'">
                                <input type="radio" :name="'jawaban_benar_pg'" :value="idx"
                                       :checked="opsi.benar"
                                       @change="setBenarPG(idx)"
                                       class="mt-2 w-4 h-4 text-blue-600 cursor-pointer">
                            </template>
                            <template x-if="jenis === 'pilihan_ganda_kompleks'">
                                <input type="checkbox" :name="`opsi[${idx}][benar]`" value="1"
                                       x-model="opsi.benar"
                                       class="mt-2 w-4 h-4 text-blue-600 rounded cursor-pointer">
                            </template>
                        </div>
                        {{-- Label Opsi --}}
                        <span class="flex-shrink-0 mt-2 w-6 h-6 bg-gray-100 rounded-full text-xs font-bold text-gray-600 flex items-center justify-center"
                              x-text="String.fromCharCode(65 + idx)"></span>
                        {{-- Rich text editor for opsi --}}
                        <div class="flex-1" x-data="richEditor({
                            name: `opsi[${idx}][teks]`,
                            content: opsi.teks || '',
                            placeholder: `Opsi ${String.fromCharCode(65 + idx)}... (Ctrl+V untuk paste gambar)`,
                            uploadUrl: '{{ route('pembuat-soal.soal.upload-image') }}',
                            minimal: true
                        })">
                            <div class="ck-editor-wrap-mini">
                                <div x-ref="editorEl"></div>
                            </div>
                            <input type="hidden" :name="`opsi[${idx}][teks]`" x-ref="hiddenInput">
                        </div>
                        {{-- Hapus opsi --}}
                        <button type="button" @click="removeOpsi(idx)"
                                x-show="opsiList.length > 2"
                                class="flex-shrink-0 mt-1.5 text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>

                {{-- Hidden inputs untuk jawaban benar PG --}}
                <template x-if="jenis === 'pilihan_ganda'">
                    <template x-for="(opsi, idx) in opsiList" :key="'benar-'+idx">
                        <input type="hidden" :name="`opsi[${idx}][benar]`" :value="opsi.benar ? '1' : '0'">
                    </template>
                </template>

                <button type="button" @click="addOpsi()"
                        x-show="opsiList.length < 6"
                        class="flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Opsi
                </button>
            </div>

            {{-- Kunci Jawaban Isian --}}
            <div class="card" x-show="jenis === 'isian'" x-transition>
                <h2 class="font-semibold text-gray-900 mb-3">Kunci Jawaban</h2>
                <input type="text" :name="jenis === 'isian' ? 'kunci_jawaban' : ''"
                       value="{{ old('kunci_jawaban', $soal->kunci_jawaban ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Jawaban yang benar (tepat sama, case-insensitive)">
            </div>

            {{-- Menjodohkan --}}
            <div class="card space-y-4" x-show="jenis === 'menjodohkan'" x-transition>
                <h2 class="font-semibold text-gray-900">Pasangan Soal</h2>
                <template x-for="(pair, idx) in pasanganList" :key="idx">
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full text-xs font-bold text-blue-700 flex items-center justify-center"
                                  x-text="idx + 1"></span>
                            <span class="text-xs text-gray-500 font-medium">Pasangan</span>
                            <div class="flex-1"></div>
                            <button type="button" @click="removePasangan(idx)"
                                    x-show="pasanganList.length > 2"
                                    class="flex-shrink-0 text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Kolom Kiri --}}
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-gray-500">Kiri</label>
                                <input type="text" :name="`pasangan[${idx}][kiri]`" x-model="pair.kiri"
                                       placeholder="Teks kiri"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <input type="hidden" :name="`pasangan[${idx}][kiri_gambar_existing]`" :value="pair.kiri_gambar || ''">
                                <template x-if="pair.kiri_gambar && !pair.kiri_preview">
                                    <div class="flex items-center gap-2">
                                        <img :src="'/storage/' + pair.kiri_gambar" class="h-10 w-10 rounded object-cover border">
                                        <span class="text-xs text-gray-400">Gambar kiri</span>
                                        <button type="button" @click="pair.kiri_gambar = null" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                    </div>
                                </template>
                                <template x-if="pair.kiri_preview">
                                    <div class="flex items-center gap-2">
                                        <img :src="pair.kiri_preview" class="h-10 w-10 rounded object-cover border">
                                        <span class="text-xs text-green-600">Gambar baru</span>
                                        <button type="button" @click="removePasanganImage(idx, 'kiri')" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                    </div>
                                </template>
                                <input type="file" :name="`pasangan[${idx}][kiri_gambar]`" accept="image/*"
                                       @change="handlePasanganImage($event, idx, 'kiri')"
                                       class="block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 cursor-pointer">
                            </div>
                            {{-- Kolom Kanan --}}
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-gray-500">Kanan</label>
                                <input type="text" :name="`pasangan[${idx}][kanan]`" x-model="pair.kanan"
                                       placeholder="Teks kanan"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <input type="hidden" :name="`pasangan[${idx}][kanan_gambar_existing]`" :value="pair.kanan_gambar || ''">
                                <template x-if="pair.kanan_gambar && !pair.kanan_preview">
                                    <div class="flex items-center gap-2">
                                        <img :src="'/storage/' + pair.kanan_gambar" class="h-10 w-10 rounded object-cover border">
                                        <span class="text-xs text-gray-400">Gambar kanan</span>
                                        <button type="button" @click="pair.kanan_gambar = null" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                    </div>
                                </template>
                                <template x-if="pair.kanan_preview">
                                    <div class="flex items-center gap-2">
                                        <img :src="pair.kanan_preview" class="h-10 w-10 rounded object-cover border">
                                        <span class="text-xs text-green-600">Gambar baru</span>
                                        <button type="button" @click="removePasanganImage(idx, 'kanan')" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                    </div>
                                </template>
                                <input type="file" :name="`pasangan[${idx}][kanan_gambar]`" accept="image/*"
                                       @change="handlePasanganImage($event, idx, 'kanan')"
                                       class="block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 cursor-pointer">
                            </div>
                        </div>
                    </div>
                </template>
                <button type="button" @click="addPasangan()"
                        class="flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Pasangan
                </button>
            </div>

            {{-- Benar / Salah --}}
            <div class="card space-y-4" x-show="jenis === 'benar_salah'" x-transition>
                <h2 class="font-semibold text-gray-900">Pernyataan Benar / Salah</h2>
                <p class="text-xs text-gray-500">Tambahkan pernyataan dan tentukan kunci jawaban (Benar/Salah) untuk setiap pernyataan.</p>

                <template x-for="(item, idx) in pernyataanBsList" :key="idx">
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="flex-shrink-0 mt-2 w-6 h-6 bg-indigo-100 rounded-full text-xs font-bold text-indigo-700 flex items-center justify-center"
                              x-text="idx + 1"></span>
                        <div class="flex-1 space-y-2">
                            {{-- Rich text editor for pernyataan B/S --}}
                            <div x-data="richEditor({
                                name: `pernyataan_bs[${idx}][teks]`,
                                content: item.teks || '',
                                placeholder: `Pernyataan ke-${idx + 1}... (Ctrl+V untuk paste gambar)`,
                                uploadUrl: '{{ route('pembuat-soal.soal.upload-image') }}',
                                minimal: true
                            })">
                                <div class="ck-editor-wrap-mini">
                                    <div x-ref="editorEl"></div>
                                </div>
                                <input type="hidden" :name="`pernyataan_bs[${idx}][teks]`" x-ref="hiddenInput">
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 font-medium">Kunci:</span>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" :name="`pernyataan_bs[${idx}][benar]`" value="1"
                                           :checked="item.benar"
                                           @change="item.benar = true"
                                           class="w-4 h-4 text-green-600 cursor-pointer">
                                    <span class="text-xs font-semibold text-green-700 px-1.5 py-0.5 bg-green-50 rounded">BENAR</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" :name="`pernyataan_bs[${idx}][benar]`" value="0"
                                           :checked="!item.benar"
                                           @change="item.benar = false"
                                           class="w-4 h-4 text-red-600 cursor-pointer">
                                    <span class="text-xs font-semibold text-red-700 px-1.5 py-0.5 bg-red-50 rounded">SALAH</span>
                                </label>
                            </div>
                        </div>
                        <button type="button" @click="removePernyataanBs(idx)"
                                x-show="pernyataanBsList.length > 2"
                                class="flex-shrink-0 mt-1.5 text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>

                <button type="button" @click="addPernyataanBs()"
                        class="flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Pernyataan
                </button>
            </div>

            {{-- Pembahasan (Essay) --}}
            <div class="card space-y-4" x-show="jenis === 'essay'" x-transition>
                <div>
                    <h2 class="font-semibold text-gray-900 mb-3">Kunci Jawaban</h2>
                    <div x-data="richEditor({
                        name: jenis === 'essay' ? 'kunci_jawaban' : '',
                        content: @js(old('kunci_jawaban', $soal->kunci_jawaban ?? '')),
                        placeholder: 'Jawaban yang diharapkan...',
                        uploadUrl: '{{ route('pembuat-soal.soal.upload-image') }}',
                        minimal: false
                    })">
                        <div class="ck-editor-wrap">
                            <div x-ref="editorEl"></div>
                        </div>
                        <input type="hidden" :name="jenis === 'essay' ? 'kunci_jawaban' : ''" x-ref="hiddenInput">
                    </div>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900 mb-3">Panduan Penilaian (Opsional)</h2>
                    <div x-data="richEditor({
                        name: 'pembahasan',
                        content: @js(old('pembahasan', $soal->pembahasan ?? '')),
                        placeholder: 'Tuliskan panduan atau rubrik penilaian...',
                        uploadUrl: '{{ route('pembuat-soal.soal.upload-image') }}',
                        minimal: false
                    })">
                        <div class="ck-editor-wrap">
                            <div x-ref="editorEl"></div>
                        </div>
                        <input type="hidden" name="pembahasan" x-ref="hiddenInput">
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Metadata --}}
        <div class="space-y-5">

            {{-- Jenis Soal --}}
            <div class="card space-y-4">
                <h2 class="font-semibold text-gray-900">Pengaturan</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Jenis Soal <span class="text-red-500">*</span>
                    </label>
                    <select name="jenis_soal" x-model="jenis"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="pilihan_ganda">Pilihan Ganda</option>
                        <option value="pilihan_ganda_kompleks">PG Kompleks</option>
                        <option value="benar_salah">Benar / Salah</option>
                        <option value="isian">Isian</option>
                        <option value="essay">Essay</option>
                        <option value="menjodohkan">Menjodohkan</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori Soal</label>
                    <select name="kategori_soal_id"
                            x-on:change="selectedKategoriId = $event.target.value; fetchNarasi()"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Tanpa Kategori —</option>
                        @foreach($kategoris as $kat)
                        <option value="{{ $kat->id }}" {{ old('kategori_soal_id', $soal->kategori_id ?? '') == $kat->id ? 'selected' : '' }}>
                            {{ $kat->nama }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Narasi (Passage) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Narasi / Teks Bacaan</label>
                    <select name="narasi_id" x-model="selectedNarasiId"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Tanpa Narasi —</option>
                        <template x-for="n in narasiList" :key="n.id">
                            <option :value="n.id" x-text="n.judul" :selected="n.id === selectedNarasiId"></option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Soal bernarasi akan menampilkan teks bacaan bersama.</p>
                </div>

                <div x-show="selectedNarasiId">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Urutan dalam Narasi</label>
                    <input type="number" name="urutan_dalam_narasi" min="1"
                           value="{{ old('urutan_dalam_narasi', $soal->urutan_dalam_narasi ?? 1) }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Tingkat Kelas</label>
                    <select name="tingkat_kelas"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">—</option>
                        @foreach(['SD', 'SMP', 'SMA'] as $t)
                        <option value="{{ $t }}" {{ old('tingkat_kelas', $soal->tingkat_kelas ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mata Pelajaran</label>
                    <input type="text" name="mata_pelajaran"
                           value="{{ old('mata_pelajaran', $soal->mata_pelajaran ?? '') }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Misal: Matematika">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Bobot</label>
                        <input type="number" name="bobot" min="1" max="100"
                               value="{{ old('bobot', $soal->bobot ?? 1) }}"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tingkat Kes.</label>
                        <select name="tingkat_kesulitan"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach(['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit'] as $val => $lab)
                            <option value="{{ $val }}" {{ old('tingkat_kesulitan', $soal->tingkat_kesulitan ?? 'sedang') === $val ? 'selected' : '' }}>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Tombol --}}
            <div class="flex flex-col gap-2">
                <button type="submit"
                        class="w-full btn-primary flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ isset($soal) ? 'Simpan Perubahan' : 'Simpan Soal' }}
                </button>
                <a href="{{ route('pembuat-soal.soal.index') }}"
                   class="btn-secondary w-full text-center">
                    Batal
                </a>
            </div>

        </div>
    </div>
</form>
</div>

@php
    $opsiListData = isset($soal) && $soal->opsiJawaban->count()
        ? $soal->opsiJawaban->map(fn($o) => ['teks' => $o->teks, 'benar' => (bool)$o->is_benar])->toArray()
        : [['teks' => '', 'benar' => false], ['teks' => '', 'benar' => false], ['teks' => '', 'benar' => false], ['teks' => '', 'benar' => false]];
    $pasanganListData = isset($soal) && $soal->pasangan->count()
        ? $soal->pasangan->map(fn($p) => ['kiri' => $p->kiri_teks, 'kanan' => $p->kanan_teks, 'kiri_gambar' => $p->kiri_gambar, 'kanan_gambar' => $p->kanan_gambar, 'kiri_preview' => null, 'kanan_preview' => null])->toArray()
        : [['kiri' => '', 'kanan' => '', 'kiri_gambar' => null, 'kanan_gambar' => null, 'kiri_preview' => null, 'kanan_preview' => null], ['kiri' => '', 'kanan' => '', 'kiri_gambar' => null, 'kanan_gambar' => null, 'kiri_preview' => null, 'kanan_preview' => null]];
    $pernyataanBsData = isset($soal) && $soal->tipe_soal === 'benar_salah' && $soal->opsiJawaban->count()
        ? $soal->opsiJawaban->map(fn($o) => ['teks' => $o->teks, 'benar' => (bool)$o->is_benar])->toArray()
        : [['teks' => '', 'benar' => true], ['teks' => '', 'benar' => true], ['teks' => '', 'benar' => true]];
    $jenisMap = ['pg'=>'pilihan_ganda','pg_kompleks'=>'pilihan_ganda_kompleks','benar_salah'=>'benar_salah','menjodohkan'=>'menjodohkan','isian'=>'isian','essay'=>'essay'];
    $currentJenis = old('jenis_soal', isset($soal) ? ($jenisMap[$soal->tipe_soal] ?? 'pilihan_ganda') : 'pilihan_ganda');
@endphp

<script>
function soalForm() {
    return {
        jenis: '{{ $currentJenis }}',
        opsiList: @json($opsiListData),
        pasanganList: @json($pasanganListData),
        pernyataanBsList: @json($pernyataanBsData),

        selectedKategoriId: '{{ old('kategori_soal_id', $soal->kategori_id ?? '') }}',
        selectedNarasiId: '{{ old('narasi_id', $soal->narasi_id ?? '') }}',
        narasiList: @json($narasis ?? []),

        init() {
            if (this.selectedKategoriId) this.fetchNarasi();
        },

        async fetchNarasi() {
            if (!this.selectedKategoriId) { this.narasiList = []; this.selectedNarasiId = ''; return; }
            try {
                const res = await fetch(`{{ route('pembuat-soal.narasi.api.by-kategori') }}?kategori_id=${this.selectedKategoriId}`);
                this.narasiList = await res.json();
                if (!this.narasiList.find(n => n.id === this.selectedNarasiId)) this.selectedNarasiId = '';
            } catch (e) { this.narasiList = []; }
        },

        addOpsi() {
            if (this.opsiList.length < 6) this.opsiList.push({ teks: '', benar: false });
        },
        removeOpsi(idx) {
            this.opsiList.splice(idx, 1);
        },
        setBenarPG(idx) {
            this.opsiList.forEach((o, i) => o.benar = i === idx);
        },

        addPasangan() {
            this.pasanganList.push({ kiri: '', kanan: '', kiri_gambar: null, kanan_gambar: null, kiri_preview: null, kanan_preview: null });
        },
        removePasangan(idx) {
            this.pasanganList.splice(idx, 1);
        },
        handlePasanganImage(event, idx, side) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.pasanganList[idx][side + '_preview'] = e.target.result;
                    this.pasanganList[idx][side + '_gambar'] = null;
                };
                reader.readAsDataURL(file);
            }
        },
        removePasanganImage(idx, side) {
            this.pasanganList[idx][side + '_preview'] = null;
            this.pasanganList[idx][side + '_gambar'] = null;
        },
        addPernyataanBs() {
            this.pernyataanBsList.push({ teks: '', benar: true });
        },
        removePernyataanBs(idx) {
            this.pernyataanBsList.splice(idx, 1);
        }
    };
}
</script>
@endsection
