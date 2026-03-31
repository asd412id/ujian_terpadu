@extends('errors.layout')

@section('title', 'Sedang Maintenance')

@section('content')
<div class="error-icon" style="background: #e0e7ff;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#4f46e5" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.66-5.66a2.12 2.12 0 113-3l5.66 5.66m2.12 2.12l5.66 5.66a2.12 2.12 0 01-3 3l-5.66-5.66m-2.12-2.12l-2.12-2.12m7.96 7.96l-2.12-2.12"/>
    </svg>
</div>
<div class="error-code" style="color: #4f46e5;">Maintenance</div>
<h1 class="error-title">Sedang Dalam Pemeliharaan</h1>
<p class="error-message">
    Sistem sedang dalam pemeliharaan untuk peningkatan layanan.
    Silakan coba beberapa saat lagi.
</p>
<div class="error-actions">
    <a href="{{ url()->current() }}" class="btn btn-primary"
       onclick="event.preventDefault(); window.location.reload();">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
        Coba Lagi
    </a>
</div>
@endsection
