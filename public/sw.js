// Service Worker — Ujian Terpadu
// Strategy: CacheFirst untuk assets, NetworkFirst untuk halaman, BackgroundSync untuk jawaban

const CACHE_VERSION    = 'v1';
const STATIC_CACHE     = `ujian-static-${CACHE_VERSION}`;
const IMAGE_CACHE      = `ujian-images-${CACHE_VERSION}`;
const PAGE_CACHE       = `ujian-pages-${CACHE_VERSION}`;
const SYNC_QUEUE_TAG   = 'jawaban-sync';

// Assets yang di-precache saat SW install
const PRECACHE_URLS = [
    '/offline',
    '/images/icon-192.png',
];

// ===== INSTALL =====
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// ===== ACTIVATE =====
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k.startsWith('ujian-') && ![STATIC_CACHE, IMAGE_CACHE, PAGE_CACHE].includes(k))
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ===== FETCH =====
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests (kecuali sync)
    if (request.method !== 'GET') return;

    // Skip chrome-extension dan non-http
    if (!url.protocol.startsWith('http')) return;

    // ---- Gambar soal: CacheFirst ----
    if (url.pathname.startsWith('/storage/soal/') ||
        url.pathname.startsWith('/storage/peserta/')) {
        event.respondWith(cacheFirst(request, IMAGE_CACHE, 7 * 24 * 3600));
        return;
    }

    // ---- Static assets: CacheFirst dengan expiry panjang ----
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE, 30 * 24 * 3600));
        return;
    }

    // ---- Halaman ujian: NetworkFirst dengan fallback cache ----
    if (url.pathname.startsWith('/ujian/')) {
        event.respondWith(networkFirst(request, PAGE_CACHE));
        return;
    }

    // ---- API ujian status: NetworkOnly (jangan cache) ----
    if (url.pathname.startsWith('/api/ujian/status')) {
        event.respondWith(fetch(request).catch(() =>
            new Response(JSON.stringify({ error: 'offline' }), {
                headers: { 'Content-Type': 'application/json' }
            })
        ));
        return;
    }

    // Default: NetworkFirst
    event.respondWith(networkFirst(request, PAGE_CACHE));
});

// ===== BACKGROUND SYNC =====
self.addEventListener('sync', event => {
    if (event.tag === SYNC_QUEUE_TAG) {
        event.waitUntil(syncPendingAnswers());
    }
});

// ===== STRATEGIES =====

async function cacheFirst(request, cacheName, maxAgeSeconds) {
    const cache    = await caches.open(cacheName);
    const cached   = await cache.match(request);

    if (cached) {
        // Check if cache is fresh enough
        const dateHeader = cached.headers.get('date');
        if (dateHeader) {
            const age = (Date.now() - new Date(dateHeader).getTime()) / 1000;
            if (age < maxAgeSeconds) return cached;
        } else {
            return cached;
        }
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return cached || new Response('Gambar tidak tersedia offline', { status: 503 });
    }
}

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);
        if (response.ok && response.status < 400) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await cache.match(request);
        if (cached) return cached;

        // Offline fallback
        if (request.headers.get('accept')?.includes('text/html')) {
            const offlineCache = await caches.open(STATIC_CACHE);
            return await offlineCache.match('/offline') ||
                   new Response('<h1>Offline</h1><p>Tidak ada koneksi.</p>', {
                       headers: { 'Content-Type': 'text/html' }
                   });
        }

        return new Response('Offline', { status: 503 });
    }
}

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|otf|png|jpg|jpeg|webp|svg|gif|ico)$/.test(pathname) ||
           pathname.startsWith('/build/');
}

// ===== SYNC PENDING ANSWERS =====
async function syncPendingAnswers() {
    // Try to notify active client first
    const clients = await self.clients.matchAll({ type: 'window' });
    if (clients.length > 0) {
        clients[0].postMessage({ type: 'TRIGGER_SYNC' });
        return;
    }

    // No active client — sync directly from IndexedDB
    try {
        const dbReq = indexedDB.open('UjianTerpaduDB', 1);

        const idb = await new Promise((resolve, reject) => {
            dbReq.onsuccess = () => resolve(dbReq.result);
            dbReq.onerror   = () => reject(dbReq.error);
        });

        // Read pending answers
        const tx = idb.transaction(['exam_answers', 'exam_state'], 'readonly');
        const answerStore = tx.objectStore('exam_answers');
        const stateStore  = tx.objectStore('exam_state');

        const allAnswers = await new Promise((resolve, reject) => {
            const req = answerStore.getAll();
            req.onsuccess = () => resolve(req.result);
            req.onerror   = () => reject(req.error);
        });

        const pending = allAnswers.filter(a => !a.synced);
        if (pending.length === 0) { idb.close(); return; }

        // Group by sesiPesertaId
        const bySesi = {};
        pending.forEach(a => {
            if (!bySesi[a.sesiPesertaId]) bySesi[a.sesiPesertaId] = [];
            bySesi[a.sesiPesertaId].push(a);
        });

        for (const [sesiPesertaId, answers] of Object.entries(bySesi)) {
            // Get token from exam_state
            const state = await new Promise((resolve) => {
                const req = stateStore.get(sesiPesertaId);
                req.onsuccess = () => resolve(req.result);
                req.onerror   = () => resolve(null);
            });

            const sesiToken = state?.sesiToken;
            if (!sesiToken) continue;

            const formattedAnswers = answers.map(item => ({
                soal_id:         item.soalId,
                jawaban:         formatJawabanSW(item.jawaban),
                idempotency_key: item.idempotencyKey,
                client_timestamp: item.updatedAt,
            }));

            const res = await fetch('/api/ujian/sync-jawaban', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    sesi_token: sesiToken,
                    answers: formattedAnswers,
                    tandai_list: state?.tandaiList ?? [],
                }),
            });

            if (res.ok) {
                // Mark synced in IndexedDB
                const writeTx = idb.transaction('exam_answers', 'readwrite');
                const writeStore = writeTx.objectStore('exam_answers');
                for (const a of answers) {
                    a.synced = true;
                    writeStore.put(a);
                }
                await new Promise((resolve) => { writeTx.oncomplete = resolve; });

                // Submit if pending
                if (state?.pendingSubmit) {
                    await fetch('/api/ujian/submit/' + sesiToken, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ sesi_token: sesiToken }),
                    });
                }
            }
        }

        idb.close();
    } catch (err) {
        console.warn('[SW] Background sync failed:', err.message);
        throw err; // Throwing makes the browser retry the sync event
    }
}

function formatJawabanSW(jawaban) {
    if (!jawaban) return null;
    if (jawaban.pg?.length > 0) return jawaban.pg;
    if (jawaban.benarSalah && Object.keys(jawaban.benarSalah).length > 0) return jawaban.benarSalah;
    if (jawaban.pasangan && Object.keys(jawaban.pasangan).length > 0) return Object.entries(jawaban.pasangan).map(([k,v]) => [parseInt(k), v]);
    if (jawaban.teks !== undefined && jawaban.teks !== '') return jawaban.teks;
    return null;
}

// ===== MESSAGE HANDLER =====
self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data?.type === 'CACHE_EXAM_IMAGES') {
        const { urls } = event.data;
        event.waitUntil(
            caches.open(IMAGE_CACHE).then(cache =>
                Promise.all(
                    urls.map(url =>
                        fetch(url).then(r => cache.put(url, r)).catch(() => {})
                    )
                )
            )
        );
    }
});
