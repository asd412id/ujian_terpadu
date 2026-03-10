<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <title>@yield('title', 'Ujian Terpadu TKA') — {{ config('app.name') }}</title>

    {{-- Preconnect for performance --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet">

    {{-- MathJax for math formulas --}}
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']] },
            options: { skipHtmlTags: ['script', 'noscript', 'style', 'textarea'] }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" async defer></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="h-full bg-[#f0f4f8] font-['Inter']">

    {{-- Flash Notification — floating kanan atas, di luar semua container --}}
    <x-flash-notification />

    @yield('content')

    {{-- PWA Install Prompt --}}
    <div id="pwa-install-banner"
         class="hidden fixed bottom-0 left-0 right-0 bg-blue-800 text-white px-4 py-3 z-50
                flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <img src="/images/icon-192.png" class="w-10 h-10 rounded-xl" alt="App Icon">
            <div>
                <p class="font-semibold text-sm">Pasang Ujian Terpadu</p>
                <p class="text-xs text-blue-200">Akses lebih cepat, bisa offline</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button id="pwa-install-btn"
                    class="bg-white text-blue-800 font-semibold text-sm px-4 py-2 rounded-lg">
                Pasang
            </button>
            <button id="pwa-dismiss-btn" class="text-blue-200 hover:text-white text-sm px-2">✕</button>
        </div>
    </div>

    @stack('scripts')

    {{-- Service Worker Registration --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    .then(reg => console.log('[SW] Registered:', reg.scope))
                    .catch(err => console.warn('[SW] Registration failed:', err));
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const banner = document.getElementById('pwa-install-banner');
            if (banner && ! localStorage.getItem('pwa-dismissed')) {
                setTimeout(() => banner.classList.remove('hidden'), 5000);
                banner.classList.add('flex');
            }
        });

        document.getElementById('pwa-install-btn')?.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                document.getElementById('pwa-install-banner')?.classList.add('hidden');
            }
        });

        document.getElementById('pwa-dismiss-btn')?.addEventListener('click', () => {
            localStorage.setItem('pwa-dismissed', '1');
            document.getElementById('pwa-install-banner')?.classList.add('hidden');
        });
    </script>
</body>
</html>
