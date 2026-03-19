@extends('layouts.admin')

@section('title', 'Pengguna')

@section('breadcrumb')
    <span class="text-gray-800 font-semibold">Pengguna</span>
@endsection

@section('page-content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl font-bold text-gray-900">Manajemen Pengguna</h1>
        <a href="{{ route('dinas.users.create') }}" class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Pengguna
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('dinas.users.index') }}"
          class="card flex flex-col sm:flex-row gap-3 p-4">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari nama atau email..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <select name="role"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Role</option>
            <option value="admin_dinas" {{ request('role') === 'admin_dinas' ? 'selected' : '' }}>Admin Dinas</option>
            <option value="admin_sekolah" {{ request('role') === 'admin_sekolah' ? 'selected' : '' }}>Admin Sekolah</option>
            <option value="pengawas" {{ request('role') === 'pengawas' ? 'selected' : '' }}>Pengawas</option>
            <option value="pembuat_soal" {{ request('role') === 'pembuat_soal' ? 'selected' : '' }}>Pembuat Soal</option>
        </select>
        <select name="status"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Status</option>
            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        <button type="submit"
                class="btn-primary">Cari</button>
        @if(request()->hasAny(['search', 'role', 'status']))
        <a href="{{ route('dinas.users.index') }}"
           class="btn-secondary text-center">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div class="card overflow-hidden p-0">
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Pengguna</th>
                        <th class="px-5 py-3 text-left hidden md:table-cell">Email</th>
                        <th class="px-5 py-3 text-center">Role</th>
                        <th class="px-5 py-3 text-left hidden lg:table-cell">Sekolah</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-blue-700 text-xs font-bold">{{ substr($user->name, 0, 1) }}</span>
                                </div>
                                <span class="font-medium text-gray-900">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-gray-600">{{ $user->email }}</td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $roleColors = ['admin_dinas' => 'blue', 'admin_sekolah' => 'green', 'pengawas' => 'amber', 'super_admin' => 'purple', 'pembuat_soal' => 'teal'];
                                $color = $roleColors[$user->role] ?? 'gray';
                            @endphp
                            <span class="text-xs font-semibold bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 rounded-full">
                                {{ str_replace('_', ' ', ucfirst($user->role)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 hidden lg:table-cell text-gray-600 text-xs">
                            {{ $user->sekolah?->nama ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($user->is_active)
                                <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('dinas.users.edit', $user->id) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('dinas.users.destroy', $user->id) }}" method="POST"
                                      x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Pengguna',message:'Hapus pengguna ini?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            Belum ada pengguna.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @forelse($users as $user)
            @php
                $roleColors = ['admin_dinas' => 'blue', 'admin_sekolah' => 'green', 'pengawas' => 'amber', 'super_admin' => 'purple', 'pembuat_soal' => 'teal'];
                $color = $roleColors[$user->role] ?? 'gray';
            @endphp
            <div class="px-4 py-3">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-700 text-xs font-bold">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    @if($user->is_active)
                        <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full shrink-0">Aktif</span>
                    @else
                        <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">Nonaktif</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-wrap text-xs ml-10.5 mb-2">
                    <span class="font-semibold bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 rounded-full">
                        {{ str_replace('_', ' ', ucfirst($user->role)) }}
                    </span>
                    @if($user->sekolah)
                    <span class="text-gray-500">{{ $user->sekolah->nama }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 ml-10.5">
                    <a href="{{ route('dinas.users.edit', $user->id) }}"
                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
                    @if($user->id !== auth()->id())
                    <form action="{{ route('dinas.users.destroy', $user->id) }}" method="POST"
                          x-data @submit.prevent="if(await $store.confirmModal.open({title:'Hapus Pengguna',message:'Hapus pengguna ini?',confirmText:'Ya, Hapus',danger:true})) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div class="py-12 text-center text-gray-400 text-sm">Belum ada pengguna.</div>
            @endforelse
        </div>
        @if($users->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $users->withQueryString()->links('components.pagination') }}
        </div>
        @endif
    </div>

</div>
@endsection
