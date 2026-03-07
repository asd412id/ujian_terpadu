@extends('layouts.admin')

@section('title', isset($soal) ? 'Edit Soal' : 'Tambah Soal')

@section('breadcrumb')
    <a href="{{ route('dinas.dinas.soal.index') }}" class="text-gray-500 hover:text-blue-600">Bank Soal</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">{{ isset($soal) ? 'Edit Soal' : 'Tambah Soal' }}</span>
@endsection

@section('page-content')
<div x-data="soalForm()" x-init="init()">
<form action="{{ isset($soal) ? route('dinas.dinas.soal.update', $soal->id) : route('dinas.dinas.soal.store') }}"
      method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if(isset($soal)) @method('PUT') @endif

    {{-- Error bag --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
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
                    <textarea name="pertanyaan" rows="5"
                              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                              placeholder="Tuliskan pertanyaan di sini...">{{ old('pertanyaan', $soal->pertanyaan ?? '') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Mendukung format HTML dasar dan LaTeX (gunakan \(...\) untuk inline math).</p>
                </div>

                {{-- Gambar Pertanyaan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Gambar Pertanyaan (opsional)</label>
                    @if(isset($soal) && $soal->gambar_pertanyaan)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ Storage::url($soal->gambar_pertanyaan) }}" alt="Gambar soal"
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
                                      :placeholder="`Opsi ${String.fromCharCode(65 + idx)}`"
                                      x-model="opsi.teks"></textarea>
                            {{-- Upload gambar opsi --}}
                            <input type="file" :name="`opsi[${idx}][gambar]`" accept="image/*"
                                   class="block w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 cursor-pointer">
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
                <input type="text" name="kunci_jawaban"
                       value="{{ old('kunci_jawaban', $soal->kunci_jawaban ?? '') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Jawaban yang benar (tepat sama, case-insensitive)">
            </div>

            {{-- Menjodohkan --}}
            <div class="card space-y-4" x-show="jenis === 'menjodohkan'" x-transition>
                <h2 class="font-semibold text-gray-900">Pasangan Soal</h2>
                <template x-for="(pair, idx) in pasanganList" :key="idx">
                    <div class="flex items-center gap-3">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full text-xs font-bold text-blue-700 flex items-center justify-center"
                              x-text="idx + 1"></span>
                        <input type="text" :name="`pasangan[${idx}][kiri]`" x-model="pair.kiri"
                               placeholder="Kolom kiri"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                        <input type="text" :name="`pasangan[${idx}][kanan]`" x-model="pair.kanan"
                               placeholder="Kolom kanan"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" @click="removePasangan(idx)"
                                x-show="pasanganList.length > 2"
                                class="flex-shrink-0 text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
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

            {{-- Pembahasan (Essay) --}}
            <div class="card" x-show="jenis === 'essay'" x-transition>
                <h2 class="font-semibold text-gray-900 mb-3">Panduan Penilaian (Opsional)</h2>
                <textarea name="pembahasan" rows="4"
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                          placeholder="Tuliskan panduan atau kunci jawaban untuk membantu penilai...">{{ old('pembahasan', $soal->pembahasan ?? '') }}</textarea>
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
                <a href="{{ route('dinas.dinas.soal.index') }}"
                   class="w-full text-center border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium py-2.5 rounded-xl transition-colors">
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
        ? $soal->pasangan->map(fn($p) => ['kiri' => $p->kiri_teks, 'kanan' => $p->kanan_teks])->toArray()
        : [['kiri' => '', 'kanan' => ''], ['kiri' => '', 'kanan' => '']];
    $jenisMap = ['pg'=>'pilihan_ganda','pg_kompleks'=>'pilihan_ganda_kompleks','menjodohkan'=>'menjodohkan','isian'=>'isian','essay'=>'essay'];
    $currentJenis = old('jenis_soal', isset($soal) ? ($jenisMap[$soal->tipe_soal] ?? 'pilihan_ganda') : 'pilihan_ganda');
@endphp

<script>
function soalForm() {
    return {
        jenis: '{{ $currentJenis }}',
        opsiList: @json($opsiListData),
        pasanganList: @json($pasanganListData),

        init() {},

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
            this.pasanganList.push({ kiri: '', kanan: '' });
        },
        removePasangan(idx) {
            this.pasanganList.splice(idx, 1);
        }
    };
}
</script>
@endsection
