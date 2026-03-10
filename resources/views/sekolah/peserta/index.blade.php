@extends('layouts.admin')

@section('title', 'Data Peserta')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Data Peserta</span>
@endsection

@section('page-content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Data Peserta</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('sekolah.peserta.import') }}"
               class="btn-secondary inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Excel
            </a>
            <a href="{{ route('sekolah.kartu.cetak-semua') }}" target="_blank"
               class="btn-primary inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Cetak Semua Kartu
            </a>
            <form action="{{ route('sekolah.peserta.destroy-all') }}" method="POST"
                  x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Semua Peserta',message:'PERHATIAN: Tindakan ini akan menghapus SEMUA data peserta sekolah Anda secara permanen dan tidak dapat dibatalkan. Yakin ingin melanjutkan?',confirmText:'Ya, Hapus Semua',danger:true})) $el.submit()">
                @csrf @method('DELETE')
                <button type="submit"
                        class="btn-danger-outline inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Hapus Semua
                </button>
            </form>
        </div>
    </div>

    {{-- Filter + Search --}}
    <form method="GET" action="{{ route('sekolah.peserta.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, NIS, NISN..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="kelas"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Kelas</option>
            @foreach($kelasList as $kls)
            <option value="{{ $kls }}" {{ request('kelas') === $kls ? 'selected' : '' }}>{{ $kls }}</option>
            @endforeach
        </select>
        <button type="submit"
                class="btn-primary">Cari</button>
        @if(request()->hasAny(['q', 'kelas']))
        <a href="{{ route('sekolah.peserta.index') }}"
           class="btn-secondary text-center">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between text-sm text-gray-500">
            <span>{{ $peserta->total() }} peserta</span>
            <a href="{{ route('sekolah.kartu.index') }}" class="text-blue-600 text-xs hover:underline flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Kartu Login per Sesi
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Nama Lengkap</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">NIS / NISN</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Username Ujian</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Kartu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($peserta as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-blue-700 text-xs font-bold">{{ substr($p->nama, 0, 1) }}</span>
                                </div>
                                <span class="font-medium text-gray-900">{{ $p->nama }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden sm:table-cell text-gray-600">
                            <p>{{ $p->nis ?: '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $p->nisn ?: '' }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">{{ $p->kelas ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded font-mono">{{ $p->username_ujian }}</code>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($p->is_active)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('sekolah.kartu.show', $p->id) }}"
                               class="text-amber-600 hover:text-amber-800 text-xs font-medium" target="_blank">
                                Cetak Kartu
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            Belum ada peserta terdaftar. Hubungi Dinas Pendidikan untuk menambah data peserta.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($peserta->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $peserta->withQueryString()->links() }}</div>
        @endif
    </div>

</div>
@endsection
