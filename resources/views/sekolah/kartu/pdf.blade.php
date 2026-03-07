<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Login Ujian — {{ auth()->user()->sekolah->nama_sekolah }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            background: #fff;
        }

        .page-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .page-subtitle {
            text-align: center;
            font-size: 10pt;
            color: #555;
            margin-bottom: 20px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .kartu {
            border: 2px solid #1e3a5f;
            border-radius: 10px;
            padding: 12px 14px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .kartu-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .kartu-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kartu-logo-icon {
            width: 28px;
            height: 28px;
            background: #1e3a5f;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .kartu-logo-text {
            font-size: 9pt;
            font-weight: bold;
            color: #1e3a5f;
            line-height: 1.3;
        }

        .kartu-logo-sekolah {
            font-size: 8pt;
            color: #666;
            text-align: right;
            max-width: 120px;
        }

        .kartu-nama {
            font-size: 12pt;
            font-weight: bold;
            color: #111;
            margin-bottom: 2px;
        }

        .kartu-kelas {
            font-size: 9pt;
            color: #555;
            margin-bottom: 10px;
        }

        .kartu-row {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            gap: 6px;
        }

        .kartu-label {
            font-size: 9pt;
            color: #777;
            min-width: 90px;
        }

        .kartu-value {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a5f;
            font-family: 'Courier New', Courier, monospace;
            background: #f0f4ff;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .kartu-footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #ccc;
            font-size: 8pt;
            color: #999;
            text-align: center;
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
    {{-- Print Button --}}
    <div class="no-print" style="text-align:center; padding: 16px 0 8px; margin-bottom: 8px;">
        <button onclick="window.print()" style="background: #1e3a5f; color: white; border: none; padding: 10px 28px; border-radius: 8px; font-size: 13pt; cursor: pointer; font-weight: bold;">
            🖨️ Cetak Kartu
        </button>
        <a href="{{ route('sekolah.peserta.index') }}" style="margin-left: 12px; color: #555; text-decoration: none; font-size: 11pt;">← Kembali</a>
    </div>

    <div style="padding: 0 24px 24px;">
        <p class="page-title">KARTU LOGIN UJIAN TERPADU</p>
        <p class="page-subtitle">{{ auth()->user()->sekolah->nama_sekolah }} · Tahun Pelajaran {{ date('Y') }}/{{ date('Y') + 1 }}</p>

        <div class="card-grid">
            @foreach($pesertaList as $p)
            <div class="kartu">
                <div class="kartu-header">
                    <div class="kartu-logo">
                        <div class="kartu-logo-icon">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div class="kartu-logo-text">UJIAN<br>TERPADU</div>
                    </div>
                    <div class="kartu-logo-sekolah">{{ auth()->user()->sekolah->nama_sekolah }}</div>
                </div>

                <div class="kartu-nama">{{ $p->nama_lengkap }}</div>
                <div class="kartu-kelas">{{ $p->kelas ?? 'Kelas —' }} · {{ $p->nis ? 'NIS: ' . $p->nis : ($p->nisn ? 'NISN: ' . $p->nisn : '') }}</div>

                <div class="kartu-row">
                    <span class="kartu-label">Username</span>
                    <span class="kartu-value">{{ $p->username_ujian }}</span>
                </div>
                <div class="kartu-row">
                    <span class="kartu-label">Password</span>
                    <span class="kartu-value">{{ $p->password_plain ?? '(lihat admin)' }}</span>
                </div>
                <div class="kartu-row">
                    <span class="kartu-label">URL Login</span>
                    <span style="font-size: 8pt; color: #333;">{{ url('/ujian/login') }}</span>
                </div>

                <div class="kartu-footer">Simpan kartu ini. Jangan dibagikan kepada orang lain.</div>
            </div>
            @endforeach
        </div>
    </div>
</body>
</html>
