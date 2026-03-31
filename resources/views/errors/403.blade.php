@extends('errors.layout')

@section('title', 'Akses Ditolak')

@section('content')
<div class="error-icon" style="background: #fee2e2;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#dc2626" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
    </svg>
</div>
<div class="error-code" style="color: #dc2626;">Error 403</div>
<h1 class="error-title">Akses Ditolak</h1>
<p class="error-message">
    Anda tidak memiliki izin untuk mengakses halaman ini.
    Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
</p>
<div class="error-actions">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn btn-primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Kembali
    </a>
    <a href="/" class="btn btn-secondary">Ke Beranda</a>
</div>
@endsection
