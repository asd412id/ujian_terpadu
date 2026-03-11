<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Login — {{ $peserta->nama }}</title>
@php
    $logoFile = public_path('images/logo.svg');
    $logoSrc  = file_exists($logoFile)
        ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoFile))
        : null;
@endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .kartu {
            width: 9cm;
            border: 2px solid #1e3a5f;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .kartu-header {
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #dee2e6; padding-bottom: 10px; margin-bottom: 12px;
        }
        .kartu-logo { display: flex; align-items: center; gap: 8px; }
        .kartu-logo-icon {
            width: 32px; height: 32px; background: #1e3a5f;
            border-radius: 7px; display: flex; align-items: center; justify-content: center;
        }
        .kartu-logo-text { font-size: 10pt; font-weight: bold; color: #1e3a5f; line-height: 1.3; }
        .kartu-sekolah { font-size: 8pt; color: #666; text-align: right; max-width: 130px; }
        .kartu-nama { font-size: 13pt; font-weight: bold; color: #111; margin-bottom: 2px; }
        .kartu-kelas { font-size: 9pt; color: #666; margin-bottom: 12px; }
        .kartu-row { display: flex; align-items: center; margin-bottom: 6px; gap: 8px; }
        .kartu-label { font-size: 9pt; color: #888; min-width: 90px; }
        .kartu-value {
            font-size: 11pt; font-weight: bold; color: #1e3a5f;
            font-family: 'Courier New', Courier, monospace;
            background: #f0f4ff; padding: 3px 10px; border-radius: 5px; letter-spacing: 0.5px;
        }
        .kartu-url { font-size: 8pt; color: #555; }
        .kartu-footer {
            margin-top: 12px; padding-top: 8px; border-top: 1px dashed #ccc;
            font-size: 8pt; color: #aaa; text-align: center;
        }
    </style>
</head>
<body>
    <div class="kartu">
        <div class="kartu-header">
            <div class="kartu-logo">
                <div class="kartu-logo-icon">
                    @if($logoSrc)
                    <img src="{{ $logoSrc }}" width="18" height="18" alt="Logo" style="display:block;">
                    @endif
                </div>
                <div class="kartu-logo-text">{{ strtoupper(config('app.name')) }}</div>
            </div>
            <div class="kartu-sekolah">{{ $peserta->sekolah?->nama }}</div>
        </div>

        <div class="kartu-nama">{{ $peserta->nama }}</div>
        <div class="kartu-kelas">
            {{ $peserta->kelas ?? 'Kelas —' }}
            @if($peserta->nis) · NIS: {{ $peserta->nis }}@elseif($peserta->nisn) · NISN: {{ $peserta->nisn }}@endif
        </div>

        <div class="kartu-row">
            <span class="kartu-label">Username</span>
            <span class="kartu-value">{{ $peserta->username_ujian }}</span>
        </div>
        <div class="kartu-row">
            <span class="kartu-label">Password</span>
            <span class="kartu-value">{{ $passwordKartu ?? '(lihat admin)' }}</span>
        </div>
        <div class="kartu-row">
            <span class="kartu-label">URL Login</span>
            <span class="kartu-url">{{ url('/ujian/login') }}</span>
        </div>

        <div class="kartu-footer">
            Kartu ini bersifat rahasia. Jangan dibagikan kepada orang lain.
        </div>
    </div>
</body>
</html>
