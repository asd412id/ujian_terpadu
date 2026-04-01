@extends('layouts.admin')

@section('title', 'Data Peserta')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Data Peserta</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Data Peserta</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola peserta sekolah Anda, tambahkan manual, import Excel, atau cetak kartu login.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sekolah.peserta.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Peserta
            </a>
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
        <button type="submit" class="btn-primary">Cari</button>
        @if(request()->hasAny(['q', 'kelas']))
        <a href="{{ route('sekolah.peserta.index') }}" class="btn-secondary text-center">Reset</a>
        @endif
    </form>

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
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Nama Lengkap</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">NIS / NISN</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Username Ujian</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
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
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">
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
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                <a href="{{ route('sekolah.kartu.show', $p->id) }}"
                                   class="text-amber-600 hover:text-amber-800 text-xs font-medium" target="_blank">Cetak Kartu</a>
                                <a href="{{ route('sekolah.peserta.edit', $p->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('sekolah.peserta.destroy', $p->id) }}" method="POST"
                                      x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Peserta',message:'Hapus peserta {{ addslashes($p->nama) }}?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-0">
                            @if(request()->hasAny(['q', 'kelas']))
                                <x-empty-state icon="search" title="Tidak ada peserta yang cocok" subtitle="Coba ubah filter atau kata kunci pencarian Anda." />
                            @else
                                <x-empty-state icon="users" title="Belum ada data peserta" subtitle="Tambahkan peserta baru atau import dari file Excel." />
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($peserta as $p)
            <div class="px-4 py-3">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-700 text-xs font-bold">{{ substr($p->nama, 0, 1) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $p->nis ?: $p->nisn ?: '—' }} · {{ $p->kelas ?? '—' }}</p>
                        </div>
                    </div>
                    @if($p->is_active)
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full shrink-0">Aktif</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">Nonaktif</span>
                    @endif
                </div>
                <div class="ml-10.5 text-xs text-gray-500 mb-2">
                    <code class="bg-gray-100 px-2 py-0.5 rounded font-mono text-gray-600">{{ $p->username_ujian }}</code>
                </div>
                <div class="flex items-center gap-3 ml-10.5 flex-wrap">
                    <a href="{{ route('sekolah.kartu.show', $p->id) }}"
                       class="text-amber-600 hover:text-amber-800 text-xs font-medium" target="_blank">Cetak Kartu</a>
                    <a href="{{ route('sekolah.peserta.edit', $p->id) }}"
                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                    <form action="{{ route('sekolah.peserta.destroy', $p->id) }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Peserta',message:'Hapus peserta {{ addslashes($p->nama) }}?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                    </form>
                </div>
            </div>
            @empty
            @if(request()->hasAny(['q', 'kelas']))
                <x-empty-state icon="search" title="Tidak ada peserta yang cocok" subtitle="Coba ubah filter atau kata kunci pencarian." compact />
            @else
                <x-empty-state icon="users" title="Belum ada data peserta" subtitle="Tambahkan peserta baru atau import dari file Excel." compact />
            @endif
            @endforelse
        </div>

        @if($peserta->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">{{ $peserta->withQueryString()->links('components.pagination') }}</div>
        @endif
    </div>

</div>
@endsection