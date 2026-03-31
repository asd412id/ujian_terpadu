@extends('errors.layout')

@section('title', 'Terjadi Kesalahan')

@section('content')
<div class="error-icon" style="background: #fee2e2;">
    <svg fill="none" viewBox="0 0 24 24" stroke="#dc2626" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
    </svg>
</div>
<div class="error-code" style="color: #dc2626;">Error 500</div>
<h1 class="error-title">Terjadi Kesalahan</h1>
<p class="error-message">
    Maaf, terjadi kesalahan pada server kami.
    Tim teknis telah diberitahu dan sedang menanganinya.
</p>
<div class="error-actions">
    <a href="{{ url()->current() }}" class="btn btn-primary"
       onclick="event.preventDefault(); window.location.reload();">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
        Coba Lagi
    </a>
    <a href="/" class="btn btn-secondary">Ke Beranda</a>
</div>
@endsection
