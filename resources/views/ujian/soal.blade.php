<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <title>{{ $paket->nama }} — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet">

    {{-- MathJax --}}
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$','$'],['\\(','\\)']], displayMath: [['$$','$$'],['\\[','\\]']] },
            chtml: { scale: 1.15 },
            options: { skipHtmlTags: ['script','noscript','style','textarea'] }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" async defer></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Exam config for JS --}}
    <script>
        window.UJIAN_CONFIG = {
            sesiToken:      "{{ $sesiPeserta->token_ujian }}",
            sesiPesertaId:  "{{ $sesiPeserta->id }}",
            paketId:        "{{ $paket->id }}",
            sisaWaktuDetik: {{ $sisaWaktu }},
            mulaiAt:        {{ $sesiPeserta->mulai_at?->timestamp ?? 'null' }},
            durasiMenit:    {{ $paket->durasi_menit }},
            autoSaveInterval: {{ config('ujian.auto_save_interval', 30) }},
            syncUrl:        "{{ route('api.ujian.sync') }}",
            statusUrl:      "{{ route('api.ujian.status', $sesiPeserta->token_ujian) }}",
            submitUrl:      "{{ route('api.ujian.submit', $sesiPeserta->token_ujian) }}",
            logCheatingUrl: "{{ route('api.ujian.log-cheating') }}",
            soalList:       @json($soalListJs),
            jawabanExisting: @json($jawabanExistingJs),
        };
    </script>
    <style>
        .prose p { margin-top: 0.25em; margin-bottom: 0.25em; }
        .prose table { border-collapse: collapse; width: 100%; }
        .prose table th, .prose table td { border: 1px solid #999; padding: 4px 8px; }
        .prose table th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>

<body class="h-full bg-gray-50 font-['Inter'] overflow-hidden select-none"
      x-data="ujianApp()"
      x-init="init()"
      @visibilitychange.window="onVisibilityChange()"
      @online.window="onOnline()"
      @offline.window="onOffline()">

    {{-- ===== OFFLINE BANNER ===== --}}
    <div x-show="isOffline"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="offline-banner flex items-center justify-center gap-2 z-50">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 13h.01M3 3l18 18"/>
        </svg>
        <span>Mode Offline — Jawaban tersimpan lokal, akan dikirim saat koneksi pulih</span>
        <span x-show="pendingSync > 0" class="bg-amber-700 text-white text-xs px-2 py-0.5 rounded-full">
            <span x-text="pendingSync"></span> pending
        </span>
    </div>

    {{-- ===== SYNC SUCCESS TOAST ===== --}}
    <div x-show="showSyncToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 right-4 z-50 bg-green-600 text-white px-4 py-3 rounded-xl
                shadow-lg flex items-center gap-2 text-sm font-medium">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Jawaban tersinkron ✓
    </div>

    {{-- ===== DURATION CHANGE TOAST ===== --}}
    <div x-show="showDurasiToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-amber-600 text-white px-5 py-3 rounded-xl
                shadow-lg flex items-center gap-3 text-sm font-medium max-w-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span x-text="durasiToastMsg"></span>
    </div>

    {{-- ===== MAIN LAYOUT ===== --}}
    <div class="h-screen flex flex-col" :class="isOffline ? 'pt-10' : ''">

        {{-- HEADER --}}
        <header class="bg-white border-b border-gray-200 shadow-sm flex-shrink-0 safe-area-top">
            <div class="flex items-center justify-between px-3 py-2.5 sm:px-4 sm:py-3">

                {{-- Left: Navigation menu (mobile) --}}
                <button @click="showNavigator = !showNavigator"
                        class="lg:hidden flex items-center gap-2 text-gray-600 hover:text-blue-700
                               bg-gray-100 hover:bg-blue-50 px-3 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <span class="text-xs font-medium">Soal</span>
                    <span class="text-xs text-blue-700 font-bold" x-text="(currentIndex + 1) + '/' + totalSoal"></span>
                </button>

                {{-- Center: Info --}}
                <div class="flex-1 text-center px-2">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $paket->nama }}</p>
                    <p class="text-xs text-gray-500 hidden sm:block">{{ $peserta->nama }}</p>
                </div>

                {{-- Right: Timer --}}
                <div class="flex items-center gap-2">
                    {{-- Auto-save indicator --}}
                    <div x-show="isSaving" class="hidden sm:block">
                        <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>
                    <div x-show="!isSaving && lastSaved" class="hidden sm:block">
                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    {{-- Timer --}}
                    <div class="flex items-center gap-1.5 bg-gray-100 px-3 py-1.5 rounded-lg"
                         :class="sisaWaktu <= 300 ? 'bg-red-50 timer-urgent' : ''">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-mono font-bold text-sm sm:text-base" x-text="formatTime(sisaWaktu)"></span>
                    </div>

                    {{-- Submit button (desktop) --}}
                    <button @click="confirmSubmit()"
                            class="hidden sm:flex items-center gap-2 bg-blue-700 hover:bg-blue-800
                                   text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Selesai
                    </button>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="h-1 bg-gray-200">
                <div class="h-1 bg-blue-600 transition-all duration-500"
                     :style="`width: ${Math.round((soalTerjawab / totalSoal) * 100)}%`"></div>
            </div>

            {{-- Mobile: soal counter row --}}
            <div class="sm:hidden px-3 py-1.5 bg-gray-50 border-t border-gray-100 text-xs text-gray-500
                        flex items-center justify-between">
                <span>Dijawab: <strong class="text-gray-800" x-text="soalTerjawab"></strong> / <span x-text="totalSoal"></span></span>
                <span x-show="ditandai > 0" class="text-amber-600">
                    Ditandai: <strong x-text="ditandai"></strong>
                </span>
            </div>
        </header>

        {{-- MAIN CONTENT --}}
        <div class="flex-1 flex overflow-hidden">

            {{-- ========== QUESTION PANEL ========== --}}
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-3xl mx-auto px-3 py-4 sm:px-5 sm:py-6 pb-32 lg:pb-6">

                    @foreach($soalList as $index => $soal)
                    <div x-show="currentIndex === {{ $index }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0">

                        {{-- Question header --}}
                        <div class="flex items-start justify-between mb-4 gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex-shrink-0 w-9 h-9 bg-blue-700 text-white rounded-xl
                                             flex items-center justify-center font-bold text-sm">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <span class="badge badge-{{ $soal['tingkat_kesulitan'] === 'mudah' ? 'green' : ($soal['tingkat_kesulitan'] === 'sulit' ? 'red' : 'yellow') }}">
                                        {{ ucfirst($soal['tingkat_kesulitan']) }}
                                    </span>
                                    <span class="badge badge-blue ml-1">{{ $soal['kategori']['nama'] ?? '' }}</span>
                                </div>
                            </div>
                            {{-- Tandai --}}
                            <button @click="toggleTandai('{{ $soal['id'] }}')"
                                    :class="isTandai('{{ $soal['id'] }}') ? 'text-amber-500 bg-amber-50' : 'text-gray-400 hover:text-amber-400'"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs
                                           transition-colors flex-shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                </svg>
                                <span x-text="isTandai('{{ $soal['id'] }}') ? 'Ditandai' : 'Tandai'"></span>
                            </button>
                        </div>

                        {{-- Question body --}}
                        @php $hasInlineImg = str_contains($soal['pertanyaan'] ?? '', '<img '); @endphp
                        <div class="card p-5 sm:p-6 mb-4">
                            @if(!$hasInlineImg && $soal['posisi_gambar'] === 'atas' && $soal['gambar_soal'])
                            <div class="mb-4 rounded-xl overflow-hidden border border-gray-200">
                                <img src="{{ asset('storage/'.$soal['gambar_soal']) }}"
                                     alt="Gambar soal {{ $index+1 }}"
                                     class="w-full max-h-64 sm:max-h-80 object-contain bg-gray-50"
                                     loading="lazy">
                            </div>
                            @endif

                            <div class="prose prose-sm max-w-none text-gray-800 leading-relaxed">
                                {!! $soal['pertanyaan'] !!}
                            </div>

                            @if(!$hasInlineImg && $soal['gambar_soal'] && $soal['posisi_gambar'] !== 'atas')
                            <div class="mt-4 rounded-xl overflow-hidden border border-gray-200">
                                <img src="{{ asset('storage/'.$soal['gambar_soal']) }}"
                                     alt="Gambar soal {{ $index+1 }}"
                                     class="w-full max-h-64 sm:max-h-80 object-contain bg-gray-50"
                                     loading="lazy">
                            </div>
                            @endif
                        </div>

                        {{-- ===== OPTIONS ===== --}}
                        <div class="space-y-3">

                            {{-- PG / PG Kompleks --}}
                            @if(in_array($soal['tipe_soal'], ['pg', 'pg_kompleks']))
                            @foreach($soal['opsi_jawaban'] as $opsi)
                            <label class="soal-option"
                                   :class="isSelected('{{ $soal['id'] }}', '{{ $opsi['label'] }}') ? 'selected' : ''"
                                   @click="selectOpsi('{{ $soal['id'] }}', '{{ $opsi['label'] }}', '{{ $soal['tipe_soal'] }}')">
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg border-2 flex items-center justify-center
                                            font-bold text-sm transition-colors"
                                     :class="isSelected('{{ $soal['id'] }}', '{{ $opsi['label'] }}')
                                             ? 'border-blue-600 bg-blue-600 text-white'
                                             : 'border-gray-300 text-gray-500'">
                                    {{ $opsi['label'] }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    @if($opsi['teks'])
                                    <span class="text-sm text-gray-800">{!! $opsi['teks'] === strip_tags($opsi['teks']) ? e($opsi['teks']) : $opsi['teks'] !!}</span>
                                    @endif
                                    @if($opsi['gambar'])
                                    <img src="{{ asset('storage/'.$opsi['gambar']) }}"
                                         alt="Opsi {{ $opsi['label'] }}"
                                         class="mt-1.5 max-h-28 object-contain rounded-lg border border-gray-200"
                                         loading="lazy">
                                    @endif
                                </div>
                            </label>
                            @endforeach

                            @if($soal['tipe_soal'] === 'pg_kompleks')
                            <p class="text-xs text-blue-600 font-medium px-1">
                                ℹ️ Pilihan ganda kompleks — bisa pilih lebih dari satu jawaban
                            </p>
                            @endif

                            {{-- Isian Singkat --}}
                            @elseif($soal['tipe_soal'] === 'isian')
                            <div class="card p-5">
                                <label class="form-label">Jawaban:</label>
                                <input type="text"
                                       :value="getJawabanTeks('{{ $soal['id'] }}')"
                                       @input.debounce.500ms="saveIsian('{{ $soal['id'] }}', $event.target.value)"
                                       class="form-input text-base"
                                       placeholder="Ketikkan jawaban Anda di sini...">
                            </div>

                            {{-- Essay --}}
                            @elseif($soal['tipe_soal'] === 'essay')
                            <div class="card p-5">
                                <label class="form-label">Jawaban Essay:</label>
                                <textarea rows="6"
                                          :value="getJawabanTeks('{{ $soal['id'] }}')"
                                          @input.debounce.500ms="saveEssay('{{ $soal['id'] }}', $event.target.value)"
                                          class="form-input resize-none"
                                          placeholder="Tuliskan jawaban Anda secara lengkap..."></textarea>
                                <p class="text-xs text-gray-400 mt-2">Essay akan dinilai oleh guru/pengawas</p>
                            </div>

                            {{-- Menjodohkan --}}
                            @elseif($soal['tipe_soal'] === 'menjodohkan')
                            <div class="card p-5">
                                <p class="text-sm font-medium text-gray-700 mb-4">
                                    Pasangkan setiap item di kolom kiri dengan kolom kanan yang sesuai:
                                </p>
                                <div class="space-y-3">
                                    @foreach($soal['pasangan'] as $i => $pas)
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                                            @if($pas['kiri_gambar'])
                                            <img src="{{ asset('storage/'.$pas['kiri_gambar']) }}"
                                                 class="max-h-20 object-contain mb-1" alt="">
                                            @endif
                                            {{ $pas['kiri_teks'] }}
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                        </svg>
                                        <div class="flex-1">
                                            <select @change="savePasangan('{{ $soal['id'] }}', {{ $i }}, $event.target.value)"
                                                    class="form-input text-sm">
                                                <option value="">— Pilih —</option>
                                                @foreach($soal['pasangan'] as $j => $opt)
                                                <option value="{{ $j }}"
                                                        :selected="getPasanganJawaban('{{ $soal['id'] }}', {{ $i }}) === {{ $j }}">
                                                    {{ $opt['kanan_teks'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @php $selectedKanan = null; @endphp
                                            @foreach($soal['pasangan'] as $j => $opt)
                                                @if(!empty($opt['kanan_gambar']))
                                                <template x-if="getPasanganJawaban('{{ $soal['id'] }}', {{ $i }}) === {{ $j }}">
                                                    <img src="{{ asset('storage/'.$opt['kanan_gambar']) }}" class="mt-1 max-h-16 object-contain rounded border" alt="">
                                                </template>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Benar / Salah --}}
                            @elseif($soal['tipe_soal'] === 'benar_salah')
                            <div class="card p-5">
                                <p class="text-sm font-medium text-gray-700 mb-4">
                                    Tentukan <strong>Benar</strong> atau <strong>Salah</strong> untuk setiap pernyataan berikut:
                                </p>
                                <div class="space-y-3">
                                    @foreach($soal['opsi_jawaban'] as $opsi)
                                    <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-white"
                                         :class="{
                                             'border-blue-200 bg-blue-50/40': getBenarSalah('{{ $soal['id'] }}', '{{ $opsi['label'] }}') !== null
                                         }">
                                        <span class="flex-shrink-0 mt-0.5 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700
                                                     flex items-center justify-center text-xs font-bold">{{ $opsi['label'] }}</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-800 leading-relaxed">{!! $opsi['teks'] === strip_tags($opsi['teks']) ? e($opsi['teks']) : $opsi['teks'] !!}</p>
                                            @if(!empty($opsi['gambar']))
                                            <img src="{{ asset('storage/'.$opsi['gambar']) }}" class="mt-1 max-h-20 object-contain rounded border" alt="">
                                            @endif
                                        </div>
                                        <div class="flex-shrink-0 flex gap-2 mt-0.5">
                                            <button type="button"
                                                    @click="selectBenarSalah('{{ $soal['id'] }}', '{{ $opsi['label'] }}', 'benar')"
                                                    :class="getBenarSalah('{{ $soal['id'] }}', '{{ $opsi['label'] }}') === 'benar'
                                                            ? 'bg-green-600 text-white border-green-600 shadow-sm'
                                                            : 'bg-white text-gray-500 border-gray-300 hover:border-green-400 hover:text-green-600'"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all duration-150">
                                                BENAR
                                            </button>
                                            <button type="button"
                                                    @click="selectBenarSalah('{{ $soal['id'] }}', '{{ $opsi['label'] }}', 'salah')"
                                                    :class="getBenarSalah('{{ $soal['id'] }}', '{{ $opsi['label'] }}') === 'salah'
                                                            ? 'bg-red-600 text-white border-red-600 shadow-sm'
                                                            : 'bg-white text-gray-500 border-gray-300 hover:border-red-400 hover:text-red-600'"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition-all duration-150">
                                                SALAH
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <p class="text-xs text-indigo-600 font-medium px-1 mt-3">
                                    ℹ️ Pilih Benar atau Salah untuk setiap pernyataan
                                </p>
                            </div>
                            @endif

                        </div>{{-- end options --}}

                    </div>{{-- end soal block --}}
                    @endforeach

                </div>
            </main>

            {{-- ========== NAVIGATOR PANEL (Desktop) ========== --}}
            <aside class="hidden lg:flex flex-col w-64 xl:w-72 bg-white border-l border-gray-200 flex-shrink-0">
                <div class="px-4 py-4 border-b border-gray-100">
                    <p class="font-semibold text-gray-800 text-sm">Navigasi Soal</p>
                    <div class="flex gap-4 mt-2 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-blue-600 rounded-sm inline-block"></span> Dijawab
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-amber-400 rounded-sm inline-block"></span> Ditandai
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 bg-gray-200 rounded-sm inline-block"></span> Belum
                        </span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div class="grid grid-cols-5 gap-1.5">
                        @foreach($soalList as $i => $s)
                        <button @click="goToSoal({{ $i }})"
                                :class="{
                                    'bg-blue-600 text-white border-blue-600': isAnswered('{{ $s['id'] }}') && !isTandai('{{ $s['id'] }}'),
                                    'bg-amber-400 text-white border-amber-400': isTandai('{{ $s['id'] }}'),
                                    'bg-gray-100 text-gray-600 border-gray-200': !isAnswered('{{ $s['id'] }}') && !isTandai('{{ $s['id'] }}'),
                                    'ring-2 ring-offset-1 ring-blue-400': currentIndex === {{ $i }}
                                }"
                                class="w-full aspect-square rounded-lg border text-xs font-semibold
                                       hover:opacity-80 transition-all duration-100 flex items-center justify-center">
                            {{ $i + 1 }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop stats & submit --}}
                <div class="p-4 border-t border-gray-100 space-y-3">
                    <div class="text-sm text-gray-600 space-y-1">
                        <div class="flex justify-between">
                            <span>Terjawab</span>
                            <span class="font-semibold text-gray-900" x-text="soalTerjawab + ' / ' + totalSoal"></span>
                        </div>
                        <div class="flex justify-between" x-show="ditandai > 0">
                            <span>Ditandai</span>
                            <span class="font-semibold text-amber-600" x-text="ditandai"></span>
                        </div>
                    </div>
                    <button @click="confirmSubmit()"
                            class="w-full btn-primary py-3 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Kumpulkan Ujian
                    </button>
                </div>
            </aside>
        </div>

        {{-- ========== MOBILE BOTTOM NAV ========== --}}
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200
                    shadow-lg flex items-center justify-between px-4 py-2.5 safe-area-bottom z-20">
            <button @click="prevSoal()"
                    :disabled="currentIndex === 0"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium
                           transition-colors disabled:opacity-40
                           bg-gray-100 text-gray-700 hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Prev
            </button>

            <div class="text-center">
                <p class="text-xs font-bold text-gray-800" x-text="(currentIndex + 1) + ' / ' + totalSoal"></p>
                <p class="text-xs text-green-600" x-show="lastSaved">Tersimpan ✓</p>
            </div>

            <button @click="nextSoal()"
                    x-show="currentIndex < totalSoal - 1"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-blue-700 text-white hover:bg-blue-800 transition-colors">
                Next
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <button @click="confirmSubmit()"
                    x-show="currentIndex === totalSoal - 1"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-green-600 text-white hover:bg-green-700 transition-colors">
                Selesai
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </button>
        </nav>
    </div>

    {{-- ========== MOBILE NAVIGATOR DRAWER ========== --}}
    <div x-show="showNavigator"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         @click.self="showNavigator = false"
         class="lg:hidden fixed inset-0 bg-black/50 z-40">
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl p-5 max-h-[60vh] overflow-y-auto"
             @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-900">Navigasi Soal</h3>
                <button @click="showNavigator = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-7 sm:grid-cols-10 gap-2 mb-4">
                @foreach($soalList as $i => $s)
                <button @click="goToSoal({{ $i }}); showNavigator = false"
                        :class="{
                            'bg-blue-600 text-white': isAnswered('{{ $s['id'] }}') && !isTandai('{{ $s['id'] }}'),
                            'bg-amber-400 text-white': isTandai('{{ $s['id'] }}'),
                            'bg-gray-100 text-gray-600': !isAnswered('{{ $s['id'] }}') && !isTandai('{{ $s['id'] }}'),
                            'ring-2 ring-blue-400': currentIndex === {{ $i }}
                        }"
                        class="aspect-square rounded-lg text-xs font-bold
                               hover:opacity-80 transition-all flex items-center justify-center">
                    {{ $i + 1 }}
                </button>
                @endforeach
            </div>
            <button @click="confirmSubmit()"
                    class="w-full btn-primary py-3 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Kumpulkan Ujian
            </button>
        </div>
    </div>

    {{-- ========== SUBMIT CONFIRM MODAL ========== --}}
    <div x-show="showSubmitModal" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm" @click.stop>
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Kumpulkan Ujian?</h3>
                <p class="text-gray-500 text-sm mt-1">Pastikan semua soal sudah dijawab dengan benar.</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 mb-5 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Terjawab</span>
                    <span class="font-semibold" x-text="soalTerjawab + ' dari ' + totalSoal + ' soal'"></span>
                </div>
                <div class="flex justify-between" x-show="belumTerjawab > 0">
                    <span class="text-red-600">Belum dijawab</span>
                    <span class="font-semibold text-red-600" x-text="belumTerjawab + ' soal'"></span>
                </div>
                <div class="flex justify-between" x-show="pendingSync > 0">
                    <span class="text-amber-600">Menunggu sinkronisasi</span>
                    <span class="font-semibold text-amber-600" x-text="pendingSync + ' jawaban'"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Sisa waktu</span>
                    <span class="font-mono font-semibold" x-text="formatTime(sisaWaktu)"></span>
                </div>
            </div>

            <div x-show="isOffline" class="mb-4 bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
                ⚠️ Anda sedang offline. Ujian akan dikumpulkan dan dikirim otomatis saat koneksi pulih.
            </div>

            <div x-show="belumTerjawab > 0" class="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-700">
                ⚠️ Masih ada <span class="font-bold" x-text="belumTerjawab"></span> soal yang belum dijawab. Jawab semua soal terlebih dahulu sebelum mengumpulkan ujian.
            </div>

            <div class="flex gap-3">
                <button @click="showSubmitModal = false"
                        class="flex-1 btn-secondary">
                    Kembali
                </button>
                <button @click="doSubmit()"
                        :disabled="isSubmitting || belumTerjawab > 0"
                        class="flex-1 btn-primary flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="isSubmitting ? 'Mengirim...' : (belumTerjawab > 0 ? 'Jawab Semua Soal Dulu' : 'Ya, Kumpulkan')"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ========== VIOLATION OVERLAY (Anti-Cheat) ========== --}}
    <div x-show="showViolationOverlay" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="background: rgba(127, 29, 29, 0.92);">
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md text-center" @click.stop>
            {{-- Warning Icon --}}
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            {{-- Title --}}
            <h3 class="text-xl font-bold text-red-800 mb-2">Peringatan Pelanggaran!</h3>

            {{-- Violation Counter --}}
            <div class="inline-flex items-center gap-2 bg-red-50 border border-red-200 rounded-full px-4 py-1.5 mb-4">
                <span class="text-sm font-semibold text-red-700">Pelanggaran ke-</span>
                <span class="text-lg font-bold text-red-800" x-text="violationCount"></span>
                <span class="text-sm text-red-600">dari</span>
                <span class="text-lg font-bold text-red-800" x-text="maxViolations"></span>
            </div>

            {{-- Message --}}
            <p class="text-gray-700 text-sm leading-relaxed mb-5" x-text="violationMessage"></p>

            {{-- Progress bar showing violations --}}
            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-5">
                <div class="bg-red-600 h-2.5 rounded-full transition-all duration-500"
                     :style="'width: ' + Math.min(100, (violationCount / maxViolations) * 100) + '%'"></div>
            </div>

            {{-- Warning text if close to max --}}
            <template x-if="violationCount >= maxViolations">
                <div class="bg-red-50 border border-red-300 rounded-xl p-3 mb-4">
                    <p class="text-sm font-bold text-red-700">Ujian akan otomatis dikumpulkan...</p>
                    <div class="flex justify-center mt-2">
                        <svg class="w-5 h-5 text-red-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>
                </div>
            </template>

            <template x-if="violationCount < maxViolations">
                <div>
                    <p class="text-xs text-gray-500 mb-4">
                        Jika pelanggaran mencapai <strong x-text="maxViolations"></strong> kali,
                        ujian akan <strong>otomatis dikumpulkan</strong>.
                    </p>
                    <button @click="returnToFullscreen()"
                            class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 px-6
                                   rounded-xl transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                        Kembali ke Fullscreen & Lanjutkan Ujian
                    </button>
                </div>
            </template>
        </div>
    </div>

</body>
</html>
