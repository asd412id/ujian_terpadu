@extends('errors.layout')

@section('title', 'Terlalu Banyak Percobaan')

@section('content')
<div class="error-icon" style="background: #fef3c7;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
</div>
<div class="error-code" style="color: #d97706;">Error 429</div>
<h1 class="error-title">Terlalu Banyak Percobaan</h1>
<p class="error-message">
    Anda telah melakukan terlalu banyak permintaan dalam waktu singkat.
    Silakan tunggu beberapa saat sebelum mencoba kembali.
</p>
<div class="error-actions">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn btn-primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Kembali
    </a>
</div>
@endsection
