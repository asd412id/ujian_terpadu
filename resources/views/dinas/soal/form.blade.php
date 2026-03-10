@extends('layouts.admin')

@section('title', isset($soal) ? 'Edit Soal' : 'Tambah Soal')

@section('breadcrumb')
    <a href="{{ route('dinas.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($soal) ? 'Edit Soal' : 'Tambah Soal' }}</span>
@endsection

@section('page-content')
<div x-data="soalForm()" x-init="init()">
<form action="{{ isset($soal) ? route('dinas.soal.update', $soal->id) : route('dinas.soal.store') }}"
      method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if(isset($soal)) @method('PUT') @endif

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
                    <textarea name="pertanyaan" rows="5"
                              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                              placeholder="Tuliskan pertanyaan di sini...">{{ old('pertanyaan', $soal->pertanyaan ?? '') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Mendukung format HTML dasar dan LaTeX (gunakan \(...\) untuk inline math).</p>
                </div>

                {{-- Gambar Pertanyaan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Gambar Pertanyaan (opsional)</label>
                    @if(isset($soal) && $soal->gambar_soal)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ Storage::url($soal->gambar_soal) }}" alt="Gambar soal"
                             class="h-20 w-auto rounded-lg border border-gray-200 object-contain">
                        <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="hapus_gambar_pertanyaan" value="1">
                            Hapus gambar
                        </label>
                    </div>
                    @endif
                    <input type="file" name="gambar_pertanyaan" accept="image/*"
                           class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">Maks 2MB, format JPG/PNG/GIF/WEBP.</p>
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
                        {{-- Input teks --}}
                        <div class="flex-1 space-y-2">
                            <textarea :name="`opsi[${idx}][teks]`" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                      :placeholder="`Opsi ${String.fromCharCode(65 + idx)} (teks opsional jika ada gambar)`"
                                      x-model="opsi.teks"
                                      @paste="handleOpsiPaste($event, idx)"></textarea>
                            {{-- Upload / paste gambar opsi --}}
                            <input type="hidden" :name="`opsi[${idx}][gambar_existing]`" :value="opsi.gambar || ''">
                            {{-- Preview gambar existing (dari server) --}}
                            <template x-if="opsi.gambar && !opsi.pastedPreview">
                                <div class="mt-1 flex items-center gap-2">
                                    <img :src="'/storage/' + opsi.gambar" class="h-10 w-10 rounded object-cover border">
                                    <span class="text-xs text-gray-400">Gambar saat ini</span>
                                    <button type="button" @click="opsi.gambar = null" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                </div>
                            </template>
                            {{-- Preview gambar dari paste clipboard --}}
                            <template x-if="opsi.pastedPreview">
                                <div class="mt-1 flex items-center gap-2">
                                    <img :src="opsi.pastedPreview" class="h-16 max-w-[120px] rounded object-cover border">
                                    <span class="text-xs text-green-600">Gambar dari clipboard</span>
                                    <button type="button" @click="removePastedImage(idx)" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                </div>
                            </template>
                            <div class="flex items-center gap-2">
                                <input type="file" :id="`opsi-gambar-${idx}`" :name="`opsi[${idx}][gambar]`" accept="image/*"
                                       @change="handleOpsiFileChange($event, idx)"
                                       class="block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 cursor-pointer">
                                <span class="text-xs text-gray-400 whitespace-nowrap">atau Ctrl+V di teks</span>
                            </div>
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
                            <textarea :name="`pernyataan_bs[${idx}][teks]`" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                      :placeholder="`Pernyataan ke-${idx + 1}`"
                                      x-model="item.teks"></textarea>
                            {{-- Gambar pernyataan --}}
                            <input type="hidden" :name="`pernyataan_bs[${idx}][gambar_existing]`" :value="item.gambar || ''">
                            <template x-if="item.gambar && !item.preview">
                                <div class="flex items-center gap-2">
                                    <img :src="'/storage/' + item.gambar" class="h-10 w-10 rounded object-cover border">
                                    <span class="text-xs text-gray-400">Gambar saat ini</span>
                                    <button type="button" @click="item.gambar = null" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                </div>
                            </template>
                            <template x-if="item.preview">
                                <div class="flex items-center gap-2">
                                    <img :src="item.preview" class="h-10 w-10 rounded object-cover border">
                                    <span class="text-xs text-green-600">Gambar baru</span>
                                    <button type="button" @click="removeBsImage(idx)" class="text-xs text-red-400 hover:text-red-600">Hapus</button>
                                </div>
                            </template>
                            <input type="file" :id="`bs-gambar-${idx}`" :name="`pernyataan_bs[${idx}][gambar]`" accept="image/*"
                                   @change="handleBsImage($event, idx)"
                                   class="block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 cursor-pointer">
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
                    <textarea :name="jenis === 'essay' ? 'kunci_jawaban' : ''" rows="4"
                              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                              placeholder="Jawaban yang diharapkan...">{{ old('kunci_jawaban', $soal->kunci_jawaban ?? '') }}</textarea>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900 mb-3">Panduan Penilaian (Opsional)</h2>
                    <textarea name="pembahasan" rows="4"
                              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                              placeholder="Tuliskan panduan atau rubrik penilaian untuk membantu penilai...">{{ old('pembahasan', $soal->pembahasan ?? '') }}</textarea>
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
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Tanpa Kategori —</option>
                        @foreach($kategoris as $kat)
                        <option value="{{ $kat->id }}" {{ old('kategori_soal_id', $soal->kategori_id ?? '') == $kat->id ? 'selected' : '' }}>
                            {{ $kat->nama }}
                        </option>
                        @endforeach
                    </select>
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

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Acak Opsi</label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="acak_opsi" value="1"
                               {{ old('acak_opsi', $soal->acak_opsi ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                        <span class="text-sm text-gray-600">Urutan opsi diacak saat ujian</span>
                    </label>
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
                <a href="{{ route('dinas.soal.index') }}"
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
        ? $soal->opsiJawaban->map(fn($o) => ['teks' => $o->teks, 'benar' => (bool)$o->is_benar, 'gambar' => $o->gambar, 'pastedPreview' => null])->toArray()
        : [['teks' => '', 'benar' => false, 'gambar' => null, 'pastedPreview' => null], ['teks' => '', 'benar' => false, 'gambar' => null, 'pastedPreview' => null], ['teks' => '', 'benar' => false, 'gambar' => null, 'pastedPreview' => null], ['teks' => '', 'benar' => false, 'gambar' => null, 'pastedPreview' => null]];
    $pasanganListData = isset($soal) && $soal->pasangan->count()
        ? $soal->pasangan->map(fn($p) => ['kiri' => $p->kiri_teks, 'kanan' => $p->kanan_teks, 'kiri_gambar' => $p->kiri_gambar, 'kanan_gambar' => $p->kanan_gambar, 'kiri_preview' => null, 'kanan_preview' => null])->toArray()
        : [['kiri' => '', 'kanan' => '', 'kiri_gambar' => null, 'kanan_gambar' => null, 'kiri_preview' => null, 'kanan_preview' => null], ['kiri' => '', 'kanan' => '', 'kiri_gambar' => null, 'kanan_gambar' => null, 'kiri_preview' => null, 'kanan_preview' => null]];
    $pernyataanBsData = isset($soal) && $soal->tipe_soal === 'benar_salah' && $soal->opsiJawaban->count()
        ? $soal->opsiJawaban->map(fn($o) => ['teks' => $o->teks, 'benar' => (bool)$o->is_benar, 'gambar' => $o->gambar, 'preview' => null])->toArray()
        : [['teks' => '', 'benar' => true, 'gambar' => null, 'preview' => null], ['teks' => '', 'benar' => true, 'gambar' => null, 'preview' => null], ['teks' => '', 'benar' => true, 'gambar' => null, 'preview' => null]];
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

        init() {},

        addOpsi() {
            if (this.opsiList.length < 6) this.opsiList.push({ teks: '', benar: false, gambar: null, pastedPreview: null });
        },
        removeOpsi(idx) {
            this.opsiList.splice(idx, 1);
        },
        setBenarPG(idx) {
            this.opsiList.forEach((o, i) => o.benar = i === idx);
        },

        handleOpsiPaste(event, idx) {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    event.preventDefault();
                    const file = item.getAsFile();
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.opsiList[idx].pastedPreview = e.target.result;
                        this.opsiList[idx].gambar = null;
                    };
                    reader.readAsDataURL(file);

                    const dt = new DataTransfer();
                    dt.items.add(file);
                    const fileInput = document.getElementById(`opsi-gambar-${idx}`);
                    if (fileInput) {
                        fileInput.files = dt.files;
                    }
                    return;
                }
            }
        },

        handleOpsiFileChange(event, idx) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.opsiList[idx].pastedPreview = e.target.result;
                    this.opsiList[idx].gambar = null;
                };
                reader.readAsDataURL(file);
            } else {
                this.opsiList[idx].pastedPreview = null;
            }
        },

        removePastedImage(idx) {
            this.opsiList[idx].pastedPreview = null;
            this.opsiList[idx].gambar = null;
            const fileInput = document.getElementById(`opsi-gambar-${idx}`);
            if (fileInput) {
                fileInput.value = '';
            }
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
            this.pernyataanBsList.push({ teks: '', benar: true, gambar: null, preview: null });
        },
        removePernyataanBs(idx) {
            this.pernyataanBsList.splice(idx, 1);
        },
        handleBsImage(event, idx) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.pernyataanBsList[idx].preview = e.target.result;
                    this.pernyataanBsList[idx].gambar = null;
                };
                reader.readAsDataURL(file);
            }
        },
        removeBsImage(idx) {
            this.pernyataanBsList[idx].preview = null;
            this.pernyataanBsList[idx].gambar = null;
            const fileInput = document.getElementById(`bs-gambar-${idx}`);
            if (fileInput) fileInput.value = '';
        }
    };
}
</script>
@endsection
