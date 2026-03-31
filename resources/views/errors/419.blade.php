@extends('errors.layout')

@section('title', 'Sesi Kedaluwarsa')

@section('content')
<div class="error-icon" style="background: #fef3c7;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
    </svg>
</div>
<div class="error-code" style="color: #d97706;">Error 419</div>
<h1 class="error-title">Sesi Kedaluwarsa</h1>
<p class="error-message">
    Halaman ini sudah tidak aktif terlalu lama sehingga sesi keamanan telah berakhir.
    Silakan muat ulang halaman dan coba lagi.
</p>
<div class="error-actions">
    <a href="{{ url()->current() }}" class="btn btn-primary"
       onclick="event.preventDefault(); window.location.reload();">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
        Muat Ulang
    </a>
    <a href="/" class="btn btn-secondary">Ke Beranda</a>
</div>
@endsection
