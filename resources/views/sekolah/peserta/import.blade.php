@extends('layouts.admin')

@section('title', 'Import Peserta')

@section('breadcrumb')
    <a href="{{ route('sekolah.peserta.index') }}" class="text-gray-500 hover:text-blue-600">Peserta</a>
    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-semibold">Import Excel</span>
@endsection

@section('page-content')
<div class="space-y-5 max-w-2xl">

    <h1 class="text-xl font-bold text-gray-900">Import Data Peserta</h1>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('errors') && count(session('errors')) > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2">Import selesai dengan beberapa error:</p>
        <ul class="list-disc list-inside space-y-1 text-xs text-amber-700 max-h-40 overflow-y-auto">
            @foreach(session('errors') as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Template Download --}}
    <div class="card bg-blue-50 border-blue-200">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-blue-900 text-sm">Gunakan Template Excel</p>
                <p class="text-xs text-blue-700 mt-0.5">Unduh template dan isi sesuai format, lalu upload di bawah.</p>
                <a href="{{ asset('templates/template-import-peserta.xlsx') }}"
                   download
                   class="mt-2 inline-flex items-center gap-1.5 text-blue-700 hover:text-blue-900 text-xs font-medium">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Unduh Template Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Format Kolom --}}
    <div class="card">
        <h2 class="font-semibold text-gray-900 mb-3">Format Kolom Excel</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Kolom</th>
                        <th class="px-3 py-2 text-left">Header</th>
                        <th class="px-3 py-2 text-left">Keterangan</th>
                        <th class="px-3 py-2 text-left">Wajib</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach([
                        ['A', 'nama_lengkap', 'Nama lengkap peserta', true],
                        ['B', 'nis', 'Nomor Induk Siswa', false],
                        ['C', 'nisn', 'Nomor Induk Siswa Nasional (10 digit)', false],
                        ['D', 'kelas', 'Kelas (Misal: XII IPA 1)', false],
                        ['E', 'jenis_kelamin', 'L atau P', false],
                        ['F', 'password_ujian', 'Kosong = generate otomatis', false],
                    ] as [$kolom, $header, $ket, $wajib])
                    <tr>
                        <td class="px-3 py-2 font-mono font-bold text-blue-600">{{ $kolom }}</td>
                        <td class="px-3 py-2 font-mono text-gray-700">{{ $header }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $ket }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($wajib)
                                <span class="text-red-500 font-bold">✓</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">Minimal salah satu dari NIS atau NISN harus diisi agar peserta bisa login.</p>
    </div>

    {{-- Form Upload --}}
    <form action="{{ route('sekolah.peserta.import.post') }}" method="POST" enctype="multipart/form-data"
          class="card space-y-4">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">File Excel <span class="text-red-500">*</span></label>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-gray-300 rounded-xl">
            <p class="text-xs text-gray-400 mt-1">Format: .xlsx, .xls, atau .csv. Maks 5MB.</p>
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="update_existing" value="1"
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Update data jika NIS/NISN sudah ada</span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload & Import
            </button>
            <a href="{{ route('sekolah.peserta.index') }}"
               class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
                Batal
            </a>
        </div>
    </form>

</div>
@endsection
