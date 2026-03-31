@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')

@section('content')
<div class="error-icon" style="background: #e0e7ff;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#4f46e5" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
    </svg>
</div>
<div class="error-code" style="color: #4f46e5;">Error 404</div>
<h1 class="error-title">Halaman Tidak Ditemukan</h1>
<p class="error-message">
    Halaman yang Anda cari tidak ada atau telah dipindahkan.
    Periksa kembali alamat URL atau kembali ke beranda.
</p>
<div class="error-actions">
    <a href="/" class="btn btn-primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
        Ke Beranda
    </a>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn btn-secondary">Kembali</a>
</div>
@endsection
