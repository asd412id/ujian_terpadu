<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #1e293b;
        }
        .error-container {
            text-align: center;
            max-width: 28rem;
            width: 100%;
        }
        .error-icon {
            width: 5rem;
            height: 5rem;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.75rem;
        }
        .error-icon svg { width: 2.25rem; height: 2.25rem; }
        .error-code {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }
        .error-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        .error-message {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .error-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 0.625rem;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
        }
        .btn svg { width: 1rem; height: 1rem; }
        .btn-primary {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 1px 2px rgba(37,99,235,0.2);
        }
        .btn-primary:hover { background: #1d4ed8; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }
        .app-name {
            margin-top: 3rem;
            font-size: 0.6875rem;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="error-container">
        @yield('content')
        <div class="app-name">{{ config('app.name') }}</div>
    </div>
</body>
</html>
