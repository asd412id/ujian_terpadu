<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>

    {{-- Preconnect for performance --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet">

    {{-- MathJax for math formulas --}}
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']] },
            chtml: { scale: 1.15 },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea'],
                ignoreHtmlClass: 'tiptap-content|tiptap-content-mini|tiptap-toolbar|katex',
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" async defer></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="h-full bg-[#f0f4f8] font-['Inter']">

    {{-- Flash Notification — floating kanan atas, di luar semua container --}}
    <x-flash-notification />

    {{-- Global Confirm Modal --}}
    <div x-data x-cloak>
        <template x-teleport="body">
            <div x-show="$store.confirmModal.show"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-[99] flex items-center justify-center p-4 bg-black/50"
                 @click.self="$store.confirmModal.cancel()">
                <div x-show="$store.confirmModal.show"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden" @click.stop>
                    <div class="px-6 py-5 text-center">
                        <div class="mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-4"
                             :class="$store.confirmModal.danger ? 'bg-red-100' : 'bg-blue-100'">
                            <svg x-show="$store.confirmModal.danger" class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.27 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <svg x-show="!$store.confirmModal.danger" class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-2" x-text="$store.confirmModal.title"></h3>
                        <p class="text-sm text-gray-500 whitespace-pre-line" x-text="$store.confirmModal.message"></p>
                    </div>
                    <div class="px-6 pb-5 flex gap-3">
                        <button @click="$store.confirmModal.cancel()"
                                class="flex-1 btn-secondary">Batal</button>
                        <button @click="$store.confirmModal.confirm()"
                                class="flex-1"
                                :class="$store.confirmModal.danger ? 'btn-danger' : 'btn-primary'"
                                x-text="$store.confirmModal.confirmText"></button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @yield('content')

    {{-- PWA Install Prompt --}}
    <div id="pwa-install-banner"
         class="hidden fixed bottom-0 left-0 right-0 bg-blue-800 text-white px-4 py-3 z-50
                flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <img src="/images/icon-192.png" class="w-10 h-10 rounded-xl" alt="App Icon">
            <div>
                <p class="font-semibold text-sm">Pasang {{ config('app.name') }}</p>
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

    {{-- Global Confirm Modal Store --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('confirmModal', {
                show: false,
                title: '',
                message: '',
                confirmText: 'Ya',
                danger: false,
                _resolve: null,

                open(opts = {}) {
                    this.title = opts.title || 'Konfirmasi';
                    this.message = opts.message || 'Apakah Anda yakin?';
                    this.confirmText = opts.confirmText || 'Ya';
                    this.danger = opts.danger || false;
                    this.show = true;

                    return new Promise(resolve => { this._resolve = resolve; });
                },

                confirm() {
                    this.show = false;
                    if (this._resolve) this._resolve(true);
                    this._resolve = null;
                },

                cancel() {
                    this.show = false;
                    if (this._resolve) this._resolve(false);
                    this._resolve = null;
                }
            });
        });

        /**
         * Helper: attach confirm modal to a form or link.
         * Usage on form:  <form x-data x-on:submit.prevent="if(await $store.confirmModal.open({...})) $el.submit()">
         * Or use the global helper for non-Alpine contexts.
         */
        function confirmSubmit(form, opts) {
            Alpine.store('confirmModal').open(opts).then(ok => {
                if (ok) form.submit();
            });
        }
        function confirmNavigate(url, opts) {
            Alpine.store('confirmModal').open(opts).then(ok => {
                if (ok) window.location.href = url;
            });
        }
    </script>

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
