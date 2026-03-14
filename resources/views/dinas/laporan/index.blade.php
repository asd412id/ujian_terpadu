@extends('layouts.admin')

@section('title', 'Laporan Ujian')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Laporan</span>
@endsection

@section('page-content')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-bold text-gray-900">Laporan Ujian</h1>
        <p class="text-sm text-gray-500 mt-0.5">Rekap dan unduh hasil ujian per sekolah atau per paket.</p>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.laporan') }}" class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Filter Laporan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Paket Ujian</label>
                <select name="paket_id"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Paket</option>
                    @foreach($paketList as $p)
                    <option value="{{ $p->id }}" {{ request('paket_id') == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sekolah</label>
                <x-searchable-select
                    name="sekolah_id"
                    :options="$sekolahList->map(fn($s) => ['id' => $s->id, 'text' => $s->nama])"
                    :value="request('sekolah_id')"
                    placeholder="Semua Sekolah" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select name="status"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua</option>
                    <option value="lulus" {{ request('status') === 'lulus' ? 'selected' : '' }}>Lulus</option>
                    <option value="tidak_lulus" {{ request('status') === 'tidak_lulus' ? 'selected' : '' }}>Tidak Lulus</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="btn-primary">
                Tampilkan
            </button>
            @if(request()->hasAny(['paket_id', 'sekolah_id', 'status']))
            <a href="{{ route('dinas.laporan') }}"
               class="btn-secondary">Reset</a>
            @endif
            {{-- Export --}}
            <div class="ml-auto flex items-center gap-2">
                @if(request('paket_id'))
                <a href="{{ route('dinas.laporan.analisis-soal', request('paket_id')) }}"
                   class="btn-secondary inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Analisis Soal
                </a>
                @endif
                <a href="{{ route('dinas.laporan.export', request()->query()) }}"
                   class="btn-success inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export Excel (.xlsx)
                </a>
            </div>
        </div>
    </form>

    {{-- Recalculate Form --}}
    <form method="POST" action="{{ route('dinas.laporan.recalculate') }}"
          id="recalculate-form" class="hidden">
        @csrf
        @if(request('paket_id'))
        <input type="hidden" name="paket_id" value="{{ request('paket_id') }}">
        @endif
        @if(request('sekolah_id'))
        <input type="hidden" name="sekolah_id" value="{{ request('sekolah_id') }}">
        @endif
    </form>

    {{-- Recalculate Progress Banner --}}
    <div x-data="recalcProgress()" x-show="show" x-cloak
         class="rounded-lg border p-4 text-sm"
         :class="{
            'bg-blue-50 border-blue-200 text-blue-800': status === 'processing',
            'bg-green-50 border-green-200 text-green-800': status === 'done',
         }">
        <div class="flex items-center gap-2">
            <svg x-show="status === 'processing'" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span x-text="message"></span>
        </div>
        <template x-if="status === 'done'">
            <button @click="clear()" class="mt-2 text-xs underline">Tutup & refresh</button>
        </template>
    </div>

    <script>
    function recalcProgress() {
        return {
            show: false,
            status: 'idle',
            message: '',
            interval: null,
            init() {
                this.checkProgress();
                this.interval = setInterval(() => this.checkProgress(), 3000);
            },
            async checkProgress() {
                try {
                    const res = await fetch('{{ route("dinas.laporan.recalculate-progress") }}');
                    const data = await res.json();
                    this.status = data.status;
                    if (data.status === 'processing') {
                        this.show = true;
                        this.message = `Recalculate sedang berjalan... ${data.updated}/${data.total} (${data.changed} berubah)`;
                    } else if (data.status === 'done') {
                        this.show = true;
                        this.message = `Recalculate selesai: ${data.changed} dari ${data.updated} nilai diperbarui.`;
                        clearInterval(this.interval);
                    } else {
                        if (this.show && this.status === 'done') return;
                        this.show = false;
                        clearInterval(this.interval);
                    }
                } catch (e) {}
            },
            clear() {
                this.show = false;
                window.location.reload();
            }
        }
    }
    </script>

    {{-- Rekap Statistik --}}
    @if(isset($rekap))
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $rekap['total_peserta'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Total Peserta</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $rekap['sudah_ujian'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Sudah Ujian</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $rekap['lulus'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Lulus</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $rekap['tidak_lulus'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Tidak Lulus</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $rekap['rata_rata'] }}</p>
            <p class="text-sm text-gray-500 mt-1">Rata-rata</p>
        </div>
    </div>
    @endif

    {{-- Tabel Hasil --}}
    @if(isset($data) && is_object($data) && $data->count() > 0)
    <div class="card overflow-hidden p-0">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <p class="text-sm text-gray-500">Menampilkan {{ $data->count() }} dari {{ $data->total() }} data</p>
            <button type="button"
                    x-data
                    @click="
                        $store.confirmModal.open({
                            title: 'Hitung Ulang Nilai',
                            message: 'Hitung ulang semua nilai{{ request('paket_id') || request('sekolah_id') ? ' (sesuai filter aktif)' : '' }}?\nProses ini mungkin membutuhkan waktu beberapa detik.',
                            confirmText: 'Ya, Hitung Ulang'
                        }).then(ok => {
                            if (!ok) return;
                            $el.disabled = true;
                            $el.querySelector('.icon-default').classList.add('hidden');
                            $el.querySelector('.icon-spinner').classList.remove('hidden');
                            $el.querySelector('.btn-text').textContent = 'Menghitung ulang...';
                            document.getElementById('recalculate-form').submit();
                        })
                    "
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-amber-50">
                <svg class="w-3.5 h-3.5 icon-default" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg class="w-3.5 h-3.5 animate-spin hidden icon-spinner" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="btn-text">Hitung Ulang Nilai</span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">Peserta</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center">Benar</th>
                        <th class="px-5 py-3 text-center">Salah</th>
                        <th class="px-5 py-3 text-center">Kosong</th>
                        <th class="px-5 py-3 text-center">Nilai</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($data as $hasil)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $data->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('dinas.laporan.detail-siswa', $hasil->id) }}" class="hover:text-blue-600">
                                <p class="font-medium text-gray-900 hover:text-blue-600">{{ $hasil->peserta->nama }}</p>
                                <p class="text-xs text-gray-400">{{ $hasil->peserta->nis ?? $hasil->peserta->nisn }}</p>
                            </a>
                        </td>
                        <td class="px-5 py-3 hidden lg:table-cell text-gray-600 text-xs">{{ $hasil->peserta->sekolah->nama ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell text-gray-600">{{ $hasil->peserta->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center font-bold text-green-600">{{ $hasil->jumlah_benar ?? 0 }}</td>
                        <td class="px-5 py-3 text-center font-bold text-red-500">{{ $hasil->jumlah_salah ?? 0 }}</td>
                        <td class="px-5 py-3 text-center text-gray-400">{{ $hasil->jumlah_kosong ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @php $nilai = $hasil->nilai_akhir ?? 0; @endphp
                            <span class="font-bold {{ $nilai >= 80 ? 'text-green-600' : ($nilai >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ $nilai }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($hasil->nilai_akhir !== null)
                                @if($hasil->nilai_akhir >= 70)
                                    <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Lulus</span>
                                @else
                                    <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Tidak Lulus</span>
                                @endif
                            @elseif(in_array($hasil->status, ['submit', 'dinilai']))
                                <span class="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Menghitung...</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ ucfirst(str_replace('_', ' ', $hasil->status)) }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($data->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $data->withQueryString()->links() }}
        </div>
        @endif
    </div>
    @elseif(request()->hasAny(['paket_id', 'sekolah_id', 'status']))
    <div class="card text-center py-12 text-gray-400">Tidak ada data yang sesuai filter.</div>
    @else
    <div class="card text-center py-12 text-gray-400">
        Belum ada peserta yang menyelesaikan ujian.
    </div>
    @endif

</div>
@endsection
