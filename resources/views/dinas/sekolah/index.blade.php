@extends('layouts.admin')

@section('title', 'Data Sekolah')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Data Sekolah</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Data Sekolah</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('dinas.sekolah.import') }}" class="btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Excel
            </a>
            <a href="{{ route('dinas.sekolah.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Sekolah
            </a>
            <form action="{{ route('dinas.sekolah.destroy-all') }}" method="POST"
                  onsubmit="return confirm('PERHATIAN: Tindakan ini akan menghapus SEMUA data sekolah secara permanen dan tidak dapat dibatalkan. Yakin ingin melanjutkan?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Hapus Semua
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.sekolah.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, NPSN, kota..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="jenjang"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Jenjang</option>
            @foreach(['SD','MI','SMP','MTs','SMA','MA','SMK'] as $j)
            <option value="{{ $j }}" {{ request('jenjang') === $j ? 'selected' : '' }}>{{ $j }}</option>
            @endforeach
        </select>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Cari</button>
        @if(request()->hasAny(['q', 'jenjang']))
        <a href="{{ route('dinas.sekolah.index') }}"
           class="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg text-center">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Nama Sekolah</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">NPSN</th>
                        <th class="px-5 py-3 text-center hidden sm:table-cell">Tingkat</th>
                        <th class="px-5 py-3 text-center hidden lg:table-cell">Peserta</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sekolahList as $sekolah)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $sekolah->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $sekolah->alamat }}</p>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600 font-mono text-xs">{{ $sekolah->npsn ?? '—' }}</td>
                        <td class="px-5 py-3 text-center hidden sm:table-cell">
                            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $sekolah->jenjang }}</span>
                        </td>
                        <td class="px-5 py-3 text-center hidden lg:table-cell font-medium text-gray-700">{{ $sekolah->peserta_count ?? 0 }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($sekolah->is_active)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('dinas.sekolah.edit', $sekolah->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                <form action="{{ route('dinas.sekolah.destroy', $sekolah->id) }}" method="POST"
                                      onsubmit="return confirm('Hapus sekolah ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            Belum ada data sekolah.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sekolahList->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $sekolahList->withQueryString()->links('components.pagination') }}
        </div>
        @endif
    </div>

</div>

@if(session('import_job_id'))
@push('scripts')
<script>
(function () {
    const jobId     = @json(session('import_job_id'));
    const statusUrl = '{{ route('dinas.sekolah.import.status', ['job' => '__ID__']) }}'.replace('__ID__', jobId);
    let   attempts  = 0;
    const maxAttempts = 60;

    const interval = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) { clearInterval(interval); return; }

        try {
            const res  = await fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();

            if (data.status === 'selesai') {
                clearInterval(interval);
                window.location.reload();
            } else if (data.status === 'gagal') {
                clearInterval(interval);
            }
        } catch (e) { /* ignore, retry */ }
    }, 3000);
})();
</script>
@endpush
@endif
@endsection
