@extends('layouts.admin')

@section('title', 'Kelola Soal Paket: ' . $paket->nama)

@section('breadcrumb')
    <a href="{{ route('dinas.paket.index') }}" class="text-gray-500 hover:text-blue-600">Paket Ujian</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold truncate">{{ $paket->nama }}</span>
@endsection

@section('page-content')
<div class="space-y-5" x-data="paketSoalApp()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $paket->nama }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $paket->jenjang }} · {{ $paket->durasi_menit }} menit ·
                <span class="font-medium text-gray-700">{{ $paket->paketSoal->count() }}</span> soal dipilih
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($paket->status === 'draft')
            <form action="{{ route('dinas.paket.publish', $paket->id) }}" method="POST"
                  onsubmit="return confirm('Publikasikan paket ujian ini?')">
                @csrf
                <button type="submit"
                        class="flex-shrink-0 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Publish Paket
                </button>
            </form>
            @endif
            <a href="{{ route('dinas.paket.edit', $paket->id) }}"
               class="flex-shrink-0 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
                Edit Info Paket
            </a>
        </div>
    </div>

    {{-- Sesi Ujian --}}
    <div class="card" x-data="{ showForm: {{ $errors->has('nama_sesi') || session('_old_input') ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-gray-900">Sesi Ujian ({{ $paket->sesi->count() }})</h2>
            <button type="button" @click="showForm = !showForm"
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Sesi
            </button>
        </div>

        {{-- Add Sesi Form --}}
        <div x-show="showForm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="mb-4">
            <form action="{{ route('dinas.paket.sesi.store', $paket->id) }}" method="POST"
                  class="bg-gray-50 rounded-xl p-4 border border-gray-200 space-y-3">
                @csrf
                <p class="text-sm font-medium text-gray-700 mb-1">Tambah Sesi Baru</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Nama Sesi <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" required
                               placeholder="Sesi 1 Pagi / Ruang A"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('nama_sesi') border-red-400 @enderror">
                        @error('nama_sesi') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Ruangan</label>
                        <input type="text" name="ruangan" value="{{ old('ruangan') }}"
                               placeholder="Ruang Komputer 1"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Waktu Mulai</label>
                        <input type="datetime-local" name="waktu_mulai" value="{{ old('waktu_mulai') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Waktu Selesai</label>
                        <input type="datetime-local" name="waktu_selesai" value="{{ old('waktu_selesai') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Pengawas</label>
                        <select name="pengawas_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Pilih Pengawas --</option>
                            @foreach($pengawas as $p)
                            <option value="{{ $p->id }}" @selected(old('pengawas_id') === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Kapasitas Peserta</label>
                        <input type="number" name="kapasitas" value="{{ old('kapasitas') }}" min="1" max="999"
                               placeholder="Kosongkan = tidak terbatas"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Simpan Sesi
                    </button>
                    <button type="button" @click="showForm = false"
                            class="border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        {{-- Sesi List --}}
        @if($paket->sesi->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">Belum ada sesi untuk paket ini.</p>
        @else
        <div class="space-y-2">
            @foreach($paket->sesi->sortBy('waktu_mulai') as $sesi)
            @php
                $statusColor = match($sesi->status) {
                    'berlangsung' => ['bg' => 'green', 'label' => 'Berlangsung'],
                    'selesai'     => ['bg' => 'gray',  'label' => 'Selesai'],
                    default       => ['bg' => 'blue',  'label' => 'Persiapan'],
                };
            @endphp
            <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $sesi->nama_sesi }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-2">
                        @if($sesi->ruangan)
                        <span>📍 {{ $sesi->ruangan }}</span>
                        @endif
                        @if($sesi->waktu_mulai)
                        <span>🕐 {{ $sesi->waktu_mulai->isoFormat('D MMM YYYY, HH:mm') }}</span>
                        @endif
                        @if($sesi->pengawas)
                        <span>👤 {{ $sesi->pengawas->name }}</span>
                        @endif
                        <span>👥 {{ $sesi->sesiPeserta->count() }}{{ $sesi->kapasitas ? '/'.$sesi->kapasitas : '' }} peserta</span>
                    </p>
                </div>
                <span class="flex-shrink-0 text-xs font-semibold bg-{{ $statusColor['bg'] }}-100 text-{{ $statusColor['bg'] }}-700 px-2 py-0.5 rounded-full">
                    {{ $statusColor['label'] }}
                </span>
                <a href="{{ route('dinas.paket.sesi.peserta', [$paket->id, $sesi->id]) }}"
                   class="flex-shrink-0 text-gray-500 hover:text-green-600 hover:bg-green-50 p-1.5 rounded-lg transition-colors"
                   title="Kelola Peserta">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </a>
                <a href="{{ route('dinas.paket.sesi.edit', [$paket->id, $sesi->id]) }}"
                   class="flex-shrink-0 text-gray-500 hover:text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition-colors"
                   title="Edit sesi">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
                @if($sesi->status !== 'berlangsung')
                <form action="{{ route('dinas.paket.sesi.destroy', [$paket->id, $sesi->id]) }}" method="POST"
                      onsubmit="return confirm('Hapus sesi \'{{ addslashes($sesi->nama_sesi) }}\'? Peserta yang terdaftar juga akan dihapus.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="flex-shrink-0 text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-lg transition-colors"
                            title="Hapus sesi">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Soal Terpilih --}}
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-3">Soal Terpilih ({{ $paket->paketSoal->count() }})</h2>
        @if($paket->paketSoal->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center">Belum ada soal dalam paket ini. Tambahkan dari bank soal di bawah.</p>
        @else
        <div class="space-y-2" id="soal-terpilih">
            @foreach($paket->paketSoal->sortBy('nomor_urut') as $ps)
            <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full text-xs font-bold text-blue-700 flex items-center justify-center">
                    {{ $ps->nomor_urut }}
                </span>
                <p class="flex-1 text-sm text-gray-800 line-clamp-1">{{ strip_tags($ps->soal->pertanyaan) }}</p>
                @php
                    $jenisMeta = [
                        'pg' => ['PG', 'blue'], 'pilihan_ganda' => ['PG', 'blue'],
                        'pg_kompleks' => ['PGK', 'purple'], 'pilihan_ganda_kompleks' => ['PGK', 'purple'],
                        'isian' => ['Isian', 'green'], 'essay' => ['Essay', 'amber'], 'menjodohkan' => ['Jodoh', 'pink'],
                    ];
                    [$jLabel, $jColor] = $jenisMeta[$ps->soal->tipe_soal] ?? [ucfirst($ps->soal->tipe_soal), 'gray'];
                @endphp
                <span class="flex-shrink-0 text-xs font-semibold bg-{{ $jColor }}-100 text-{{ $jColor }}-700 px-2 py-0.5 rounded-full">
                    {{ $jLabel }}
                </span>
                <span class="flex-shrink-0 text-xs text-gray-500">Bobot: {{ $ps->soal->bobot }}</span>
                <form action="{{ route('dinas.paket.soal.remove', [$paket->id, $ps->soal_id]) }}" method="POST"
                      onsubmit="return confirm('Hapus soal ini dari paket?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="flex-shrink-0 text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-lg transition-colors"
                            title="Hapus dari paket">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Bank Soal - Tambah Soal --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
            <h2 class="font-semibold text-gray-900">Tambah dari Bank Soal</h2>
            <div class="flex items-center gap-2">
                <input type="text" x-model="search" placeholder="Cari soal..."
                       class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select x-model="filterJenis"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Jenis</option>
                    <option value="pg">Pilihan Ganda</option>
                    <option value="pg_kompleks">PG Kompleks</option>
                    <option value="isian">Isian</option>
                    <option value="essay">Essay</option>
                    <option value="menjodohkan">Menjodohkan</option>
                </select>
            </div>
        </div>

        <div class="space-y-2">
            @php $terpilihIds = $paket->paketSoal->pluck('soal_id')->toArray(); @endphp
            @forelse($bankSoal as $soal)
            <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0"
                 x-show="(!search || '{{ strtolower(strip_tags($soal->pertanyaan)) }}'.includes(search.toLowerCase()))
                         && (!filterJenis || filterJenis === '{{ $soal->tipe_soal }}')">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 line-clamp-1">{{ strip_tags($soal->pertanyaan) }}</p>
                    <p class="text-xs text-gray-500">{{ $soal->kategori->nama ?? '' }} · Bobot {{ $soal->bobot }}</p>
                </div>
                @if(in_array($soal->id, $terpilihIds))
                    <span class="flex-shrink-0 text-xs text-green-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Ditambahkan
                    </span>
                @else
                <form action="{{ route('dinas.paket.soal.add', $paket->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="soal_id" value="{{ $soal->id }}">
                    <button type="submit"
                            class="flex-shrink-0 text-blue-600 hover:text-blue-800 text-xs font-medium border border-blue-200 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                        + Tambah
                    </button>
                </form>
                @endif
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-6">Bank soal kosong.</p>
            @endforelse
        </div>

        @if($bankSoal->hasPages())
        <div class="mt-4">{{ $bankSoal->links() }}</div>
        @endif
    </div>

</div>

<script>
function paketSoalApp() {
    return { search: '', filterJenis: '' };
}
</script>
@endsection
