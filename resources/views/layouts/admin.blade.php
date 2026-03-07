@extends('layouts.base')

@section('content')
<div class="min-h-screen flex" x-data>

    {{-- SIDEBAR --}}
    <aside id="sidebar"
           class="w-60 bg-[#1e3a5f] flex flex-col fixed inset-y-0 left-0 z-30
                  transition-transform duration-300 lg:translate-x-0 -translate-x-full"
           :class="$store.sidebar.open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        {{-- Logo --}}
        <div class="px-5 py-5 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-bold text-sm leading-tight">UJIAN TERPADU</p>
                    <p class="text-blue-300 text-xs">Dinas Pendidikan</p>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            @php
                $userRole = auth()->user()->role ?? '';
                if ($userRole === 'admin_sekolah') {
                    $navItems = [
                        ['route' => 'sekolah.dashboard',    'icon' => 'grid',     'label' => 'Dashboard'],
                        ['route' => 'sekolah.paket',        'icon' => 'document', 'label' => 'Paket Ujian'],
                        ['route' => 'sekolah.peserta.index','icon' => 'users',    'label' => 'Data Peserta'],
                        ['route' => 'sekolah.soal.index',   'icon' => 'pencil',   'label' => 'Bank Soal'],
                        ['route' => 'sekolah.kartu.index',  'icon' => 'tag',      'label' => 'Kartu Login'],
                    ];
                } elseif ($userRole === 'pengawas') {
                    $navItems = [
                        ['route' => 'dinas.monitoring',     'icon' => 'eye',      'label' => 'Monitoring Ujian', 'badge' => 'LIVE'],
                    ];
                } else {
                    $navItems = [
                        ['route' => 'dinas.dashboard',         'icon' => 'grid',     'label' => 'Dashboard'],
                        ['route' => 'dinas.monitoring',        'icon' => 'eye',      'label' => 'Monitoring Ujian', 'badge' => 'LIVE'],
                        ['route' => 'dinas.paket.index',       'icon' => 'document', 'label' => 'Paket Ujian'],
                        ['route' => 'dinas.dinas.soal.index',  'icon' => 'pencil',   'label' => 'Bank Soal'],
                        ['route' => 'dinas.kategori.index',    'icon' => 'tag',      'label' => 'Kategori Soal'],
                        ['route' => 'dinas.sekolah.index',     'icon' => 'office',   'label' => 'Sekolah'],
                        ['route' => 'dinas.grading',           'icon' => 'star',     'label' => 'Penilaian Essay'],
                        ['route' => 'dinas.laporan',           'icon' => 'chart',    'label' => 'Laporan'],
                        ['route' => 'dinas.users.index',       'icon' => 'users',    'label' => 'Pengguna'],
                    ];
                }
            @endphp

            @foreach($navItems as $item)
                @php $active = request()->routeIs($item['route'] . '*') @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $active
                             ? 'bg-blue-600 text-white'
                             : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                    <x-nav-icon :name="$item['icon']" class="w-5 h-5 flex-shrink-0"/>
                    <span>{{ $item['label'] }}</span>
                    @if(isset($item['badge']))
                        <span class="ml-auto flex items-center gap-1 text-[10px] font-bold
                                     bg-red-500 text-white px-1.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                            {{ $item['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- User info --}}
        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-sm font-bold">{{ substr(auth()->user()->name, 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-xs font-semibold truncate">{{ auth()->user()->name }}</p>
                    <p class="text-blue-300 text-xs truncate">{{ auth()->user()->sekolah?->nama ?? 'Dinas Pendidikan' }}</p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-blue-300 hover:text-white" title="Logout">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Overlay mobile --}}
    <div class="lg:hidden fixed inset-0 bg-black/50 z-20"
         x-show="$store.sidebar.open"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="$store.sidebar.open = false">
    </div>

    {{-- MAIN CONTENT --}}
    <div class="flex-1 flex flex-col lg:ml-60 min-h-screen">

        {{-- Top Bar --}}
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="flex items-center justify-between px-4 py-3 lg:px-6">
                <div class="flex items-center gap-3">
                    {{-- Mobile toggle --}}
                    <button @click="$store.sidebar.open = !$store.sidebar.open"
                            class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    {{-- Breadcrumb --}}
                    <nav class="hidden sm:flex items-center gap-2 text-sm text-gray-500">
                        @yield('breadcrumb')
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Auto-refresh indicator --}}
                    @hasSection('polling')
                    <div class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                        Auto-refresh aktif
                    </div>
                    @endif

                    {{-- Notifications --}}
                    <button class="relative p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </button>

                    {{-- Avatar --}}
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold">{{ substr(auth()->user()->name, 0, 1) }}</span>
                        </div>
                        <span class="hidden md:block text-sm text-gray-700 font-medium">{{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="mx-4 mt-4 lg:mx-6" x-data="{show: true}" x-show="show" x-transition>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl
                        flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm">{{ session('success') }}</span>
                </div>
                <button @click="show = false" class="text-green-400 hover:text-green-600">✕</button>
            </div>
        </div>
        @endif

        @if(session('error') || $errors->any())
        <div class="mx-4 mt-4 lg:mx-6">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm">{{ session('error') ?? $errors->first() }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Page Content --}}
        <main class="flex-1 px-4 py-5 lg:px-6 lg:py-6">
            @yield('page-content')
        </main>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('sidebar', { open: false });
    });
</script>
@endpush
