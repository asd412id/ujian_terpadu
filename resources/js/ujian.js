import Dexie from 'dexie';

// ===== IndexedDB Schema =====
const db = new Dexie('UjianTerpaduDB');
db.version(1).stores({
    exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
    exam_state:   'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
    image_status: 'url, cached, error',
});

// ===== Main Exam Alpine Component =====
function ujianApp() {
    return {
        // State
        currentIndex:    0,
        totalSoal:       0,
        isOffline:       !navigator.onLine,
        isSaving:        false,
        lastSaved:       false,
        isSubmitting:    false,
        showNavigator:   false,
        showSubmitModal: false,
        showSyncToast:   false,

        // Jawaban & status
        answers:         {}, // { soalId: { pg: [], teks: '', pasangan: {}, benarSalah: {}, terjawab: false } }
        tandaiList:      [], // [soalId, ...]
        pendingSync:     0,

        // Timer
        sisaWaktu:       0,
        timerInterval:   null,

        // Cache progress (pre-warm)
        cacheTotal:      0,
        cacheDone:       0,
        cacheReady:      false,

        get soalTerjawab() {
            return Object.values(this.answers).filter(a => a.terjawab).length;
        },
        get belumTerjawab() {
            return this.totalSoal - this.soalTerjawab;
        },
        get ditandai() {
            return this.tandaiList.length;
        },

        // ===== INIT =====
        async init() {
            const cfg = window.UJIAN_CONFIG;
            if (!cfg) return;

            this.totalSoal   = cfg.soalList.length;
            this.sisaWaktu   = cfg.sisaWaktuDetik;

            // Restore state from IndexedDB
            await this.restoreState(cfg.sesiPesertaId);

            // Sync timer dengan server
            this.startTimer(cfg.mulaiAt, cfg.durasiMenit);

            // Auto-save interval
            setInterval(() => this.autoSync(), cfg.autoSaveInterval * 1000);

            // Pre-cache semua gambar soal + opsi
            this.preCacheImages(cfg.soalList);

            // Fullscreen request (desktop)
            if (window.innerWidth >= 1024) {
                this.requestFullscreen();
            }
        },

        // ===== RESTORE STATE =====
        async restoreState(sesiPesertaId) {
            const cfg = window.UJIAN_CONFIG;

            // Muat jawaban dari IndexedDB
            const localAnswers = await db.exam_answers
                .where('sesiPesertaId').equals(sesiPesertaId)
                .toArray();

            // Seed from server data first (jawabanExisting), then overlay IndexedDB
            if (cfg.jawabanExisting?.length > 0) {
                cfg.jawabanExisting.forEach(j => {
                    const soalId = String(j.soal_id);
                    const ans = { pg: [], teks: '', pasangan: {}, benarSalah: {}, terjawab: !!j.is_terjawab };

                    if (j.jawaban_pg) {
                        if (Array.isArray(j.jawaban_pg)) {
                            ans.pg = j.jawaban_pg;
                        } else if (typeof j.jawaban_pg === 'object') {
                            // Benar/Salah: {"A":"benar","B":"salah"}
                            ans.benarSalah = j.jawaban_pg;
                        }
                    }
                    if (j.jawaban_teks) {
                        ans.teks = j.jawaban_teks;
                    }
                    if (j.jawaban_pasangan && Array.isArray(j.jawaban_pasangan)) {
                        const map = {};
                        j.jawaban_pasangan.forEach(pair => {
                            if (Array.isArray(pair) && pair.length === 2) {
                                map[pair[0]] = pair[1];
                            }
                        });
                        ans.pasangan = map;
                    }

                    this.answers[soalId] = ans;

                    // Also seed into IndexedDB if no local record exists
                    if (!localAnswers.find(a => String(a.soalId) === soalId)) {
                        const jawData = {};
                        if (ans.pg.length > 0) { jawData.pg = ans.pg; jawData.terjawab = ans.terjawab; }
                        else if (Object.keys(ans.benarSalah).length > 0) { jawData.benarSalah = ans.benarSalah; jawData.terjawab = ans.terjawab; }
                        else if (Object.keys(ans.pasangan).length > 0) { jawData.pasangan = ans.pasangan; jawData.terjawab = ans.terjawab; }
                        else if (ans.teks) { jawData.teks = ans.teks; jawData.terjawab = ans.terjawab; }
                        else { jawData.terjawab = false; }

                        db.exam_answers.add({
                            sesiPesertaId,
                            soalId,
                            jawaban: jawData,
                            synced: true,
                            idempotencyKey: `server-${sesiPesertaId}-${soalId}`,
                            updatedAt: Date.now(),
                        }).catch(() => {});
                    }
                });

                // Restore tandai from server data
                const serverTandai = cfg.jawabanExisting
                    .filter(j => j.is_ditandai)
                    .map(j => String(j.soal_id));
                if (serverTandai.length > 0) {
                    this.tandaiList = serverTandai;
                }
            }

            // Overlay with IndexedDB data (more recent, takes priority)
            localAnswers.forEach(ans => {
                this.answers[ans.soalId] = {
                    pg:         ans.jawaban?.pg         ?? [],
                    teks:       ans.jawaban?.teks       ?? '',
                    pasangan:   ans.jawaban?.pasangan   ?? {},
                    benarSalah: ans.jawaban?.benarSalah ?? {},
                    terjawab:   ans.jawaban?.terjawab   ?? false,
                };
            });

            this.pendingSync = localAnswers.filter(a => !a.synced).length;

            // Muat state (posisi, tandai) — IndexedDB tandaiList takes priority if exists
            const state = await db.exam_state.get(sesiPesertaId);
            if (state) {
                this.currentIndex = state.currentIndex ?? 0;
                if (state.tandaiList?.length > 0) {
                    this.tandaiList = state.tandaiList;
                }
            }
        },

        // ===== TIMER (SERVER-AUTHORITATIVE) =====
        startTimer(mulaiAtTimestamp, durasiMenit) {
            const durasiDetik = durasiMenit * 60;

            const tick = () => {
                if (!mulaiAtTimestamp) {
                    this.sisaWaktu = Math.max(0, this.sisaWaktu - 1);
                } else {
                    const elapsed  = Math.floor(Date.now() / 1000) - mulaiAtTimestamp;
                    this.sisaWaktu = Math.max(0, durasiDetik - elapsed);
                }

                if (this.sisaWaktu <= 0) {
                    clearInterval(this.timerInterval);
                    this.autoSubmit();
                }
            };

            this.timerInterval = setInterval(tick, 1000);
        },

        formatTime(secs) {
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            if (h > 0) {
                return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            }
            return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        },

        // ===== NAVIGATION =====
        goToSoal(index) {
            this.currentIndex = index;
            this.saveState();
            // Scroll to top
            document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' });
        },
        nextSoal() { if (this.currentIndex < this.totalSoal - 1) this.goToSoal(this.currentIndex + 1); },
        prevSoal() { if (this.currentIndex > 0) this.goToSoal(this.currentIndex - 1); },

        // ===== JAWABAN =====
        getAnswer(soalId) {
            return this.answers[soalId] ?? { pg: [], teks: '', pasangan: {}, benarSalah: {}, terjawab: false };
        },

        isSelected(soalId, label) {
            return (this.answers[soalId]?.pg ?? []).includes(label);
        },

        isAnswered(soalId) {
            return this.answers[soalId]?.terjawab ?? false;
        },

        selectOpsi(soalId, label, tipe) {
            const ans = this.getAnswer(soalId);

            if (tipe === 'pg') {
                ans.pg = [label]; // single select
            } else {
                // pg_kompleks: toggle
                const idx = ans.pg.indexOf(label);
                if (idx >= 0) {
                    ans.pg.splice(idx, 1);
                } else {
                    ans.pg.push(label);
                }
            }

            ans.terjawab = ans.pg.length > 0;
            this.answers[soalId] = ans;
            this.saveJawaban(soalId, { pg: ans.pg, terjawab: ans.terjawab });
        },

        getJawabanTeks(soalId) {
            return this.answers[soalId]?.teks ?? '';
        },

        saveIsian(soalId, value) {
            const ans = this.getAnswer(soalId);
            ans.teks     = value;
            ans.terjawab = value.trim() !== '';
            this.answers[soalId] = ans;
            this.saveJawaban(soalId, { teks: value, terjawab: ans.terjawab });
        },

        saveEssay(soalId, value) {
            this.saveIsian(soalId, value);
        },

        savePasangan(soalId, kiriIndex, kananValue) {
            const ans = this.getAnswer(soalId);
            ans.pasangan[kiriIndex] = parseInt(kananValue);
            ans.terjawab = Object.keys(ans.pasangan).length > 0;
            this.answers[soalId] = ans;
            this.saveJawaban(soalId, { pasangan: ans.pasangan, terjawab: ans.terjawab });
        },

        getPasanganJawaban(soalId, kiriIndex) {
            return this.answers[soalId]?.pasangan?.[kiriIndex] ?? null;
        },

        // ===== BENAR / SALAH =====
        selectBenarSalah(soalId, label, value) {
            const ans = this.getAnswer(soalId);
            if (!ans.benarSalah) ans.benarSalah = {};

            // Toggle: klik lagi pada pilihan yang sama → hapus
            if (ans.benarSalah[label] === value) {
                delete ans.benarSalah[label];
            } else {
                ans.benarSalah[label] = value;
            }

            ans.terjawab = Object.keys(ans.benarSalah).length > 0;
            this.answers[soalId] = ans;
            this.saveJawaban(soalId, { benarSalah: ans.benarSalah, terjawab: ans.terjawab });
        },

        getBenarSalah(soalId, label) {
            return this.answers[soalId]?.benarSalah?.[label] ?? null;
        },

        // ===== TANDAI =====
        toggleTandai(soalId) {
            const idx = this.tandaiList.indexOf(soalId);
            if (idx >= 0) {
                this.tandaiList.splice(idx, 1);
            } else {
                this.tandaiList.push(soalId);
            }
            this.saveState();
        },

        isTandai(soalId) {
            return this.tandaiList.includes(soalId);
        },

        // ===== SAVE (IndexedDB + Server) =====
        async saveJawaban(soalId, jawabanData) {
            const cfg            = window.UJIAN_CONFIG;
            const idempotencyKey = `${cfg.sesiPesertaId}-${soalId}-${Date.now()}`;

            // 1. Simpan ke IndexedDB (immediate, offline-safe)
            this.isSaving = true;

            const existing = await db.exam_answers
                .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                .and(item => item.soalId === soalId)
                .first();

            if (existing) {
                await db.exam_answers.update(existing.id, {
                    jawaban:         jawabanData,
                    synced:          false,
                    idempotencyKey,
                    updatedAt:       Date.now(),
                });
            } else {
                await db.exam_answers.add({
                    sesiPesertaId: cfg.sesiPesertaId,
                    soalId,
                    jawaban:       jawabanData,
                    synced:        false,
                    idempotencyKey,
                    updatedAt:     Date.now(),
                });
            }

            this.pendingSync++;
            this.lastSaved  = true;
            this.isSaving   = false;

            // 2. Coba sync ke server jika online
            if (navigator.onLine) {
                this.syncToServer();
            }
        },

        async saveState() {
            const cfg = window.UJIAN_CONFIG;
            await db.exam_state.put({
                sesiPesertaId: cfg.sesiPesertaId,
                currentIndex:  this.currentIndex,
                tandaiList:    this.tandaiList,
                lastSyncAt:    Date.now(),
            });
        },

        // ===== SYNC TO SERVER =====
        async autoSync() {
            if (navigator.onLine && this.pendingSync > 0) {
                await this.syncToServer();
            }
        },

        async syncToServer() {
            if (this.isSyncing) return;
            this.isSyncing = true;

            const cfg     = window.UJIAN_CONFIG;
            const pending = await db.exam_answers
                .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                .and(item => !item.synced)
                .toArray();

            if (pending.length === 0) {
                this.isSyncing = false;
                return;
            }

            // Format untuk API
            const answers = pending.map(item => ({
                soal_id:           item.soalId,
                jawaban:           this.formatJawabanForApi(item.jawaban),
                idempotency_key:   item.idempotencyKey,
                client_timestamp:  item.updatedAt,
            }));

            try {
                const res = await fetch(cfg.syncUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        sesi_token: cfg.sesiToken,
                        answers,
                        soal_ditandai: this.tandaiList.length,
                        tandai_list: this.tandaiList,
                    }),
                });

                if (res.ok) {
                    const data = await res.json();

                    // Mark synced in IndexedDB
                    await Promise.all(pending.map(item =>
                        db.exam_answers.update(item.id, { synced: true })
                    ));

                    this.pendingSync = Math.max(0, this.pendingSync - data.synced);

                    // Show toast jika ada sync
                    if (data.synced > 0) {
                        this.showSyncToast = true;
                        setTimeout(() => { this.showSyncToast = false; }, 3000);
                    }
                }
            } catch (err) {
                console.warn('[Sync] Failed, will retry:', err.message);
            } finally {
                this.isSyncing = false;
            }
        },

        formatJawabanForApi(jawaban) {
            if (jawaban.pg?.length > 0)                        return jawaban.pg;
            if (jawaban.benarSalah && Object.keys(jawaban.benarSalah).length > 0) return jawaban.benarSalah;
            if (jawaban.pasangan && Object.keys(jawaban.pasangan).length > 0)     return Object.entries(jawaban.pasangan).map(([k,v]) => [parseInt(k), v]);
            if (jawaban.teks !== undefined && jawaban.teks !== '')                 return jawaban.teks;
            return null;
        },

        // ===== SUBMIT =====
        confirmSubmit() {
            this.showNavigator   = false;
            this.showSubmitModal = true;
        },

        async doSubmit() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;

            const cfg = window.UJIAN_CONFIG;

            // Gather ALL answers from IndexedDB as safety net
            let allAnswers = [];
            try {
                const allRecords = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .toArray();

                allAnswers = allRecords.map(item => ({
                    soal_id:           item.soalId,
                    jawaban:           this.formatJawabanForApi(item.jawaban),
                    idempotency_key:   item.idempotencyKey,
                    client_timestamp:  item.updatedAt,
                }));
            } catch (e) {
                console.warn('[Submit] Could not read IndexedDB:', e.message);
            }

            if (!navigator.onLine) {
                // Offline submit — queue untuk dikirim nanti
                await this.queueOfflineSubmit(cfg);
                window.location.href = '/ujian/' + cfg.sesiPesertaId + '/selesai';
                return;
            }

            try {
                const res = await fetch(cfg.submitUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        sesi_token: cfg.sesiToken,
                        answers:    allAnswers,
                    }),
                });

                const data = await res.json();
                if (res.ok) {
                    // Clear IndexedDB after successful submit
                    try {
                        await db.exam_answers
                            .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                            .delete();
                    } catch (e) { /* ignore */ }
                    window.location.href = data.redirect ?? '/ujian/' + cfg.sesiPesertaId + '/selesai';
                }
            } catch (err) {
                // Fallback: form submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/ujian/${cfg.sesiPesertaId}/submit`;
                const csrf = document.createElement('input');
                csrf.type = 'hidden'; csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                form.appendChild(csrf);
                // Include answers in hidden field as fallback
                if (allAnswers.length > 0) {
                    const answersInput = document.createElement('input');
                    answersInput.type = 'hidden';
                    answersInput.name = 'answers_json';
                    answersInput.value = JSON.stringify(allAnswers);
                    form.appendChild(answersInput);
                }
                document.body.appendChild(form);
                form.submit();
            }
        },

        async autoSubmit() {
            console.log('[Timer] Waktu habis, auto-submit...');
            await this.doSubmit();
        },

        async queueOfflineSubmit(cfg) {
            // Tag semua jawaban sebagai perlu submit
            await db.exam_state.put({
                sesiPesertaId:  cfg.sesiPesertaId,
                currentIndex:   this.currentIndex,
                tandaiList:     this.tandaiList,
                pendingSubmit:  true,
                lastSyncAt:     Date.now(),
            });
        },

        // ===== ANTI-CHEAT =====
        onVisibilityChange() {
            if (document.hidden) {
                this.logCheating('ganti_tab');
            }
        },

        logCheating(event) {
            const cfg = window.UJIAN_CONFIG;
            fetch('/api/ujian/status/' + cfg.sesiToken, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ event, token: cfg.sesiToken }),
            }).catch(() => {}); // silent fail
        },

        requestFullscreen() {
            const el = document.documentElement;
            if (el.requestFullscreen) {
                el.requestFullscreen().catch(() => {});
            }
        },

        // ===== NETWORK EVENTS =====
        onOnline() {
            this.isOffline = false;
            this.syncToServer();
        },

        onOffline() {
            this.isOffline = true;
        },

        // ===== PRE-CACHE IMAGES =====
        async preCacheImages(soalList) {
            const imageUrls = [];

            soalList.forEach(soal => {
                if (soal.gambar_soal) imageUrls.push(soal.gambar_soal);
                (soal.opsi ?? []).forEach(o => {
                    if (o.gambar) imageUrls.push(o.gambar);
                });
            });

            this.cacheTotal = imageUrls.length;
            if (this.cacheTotal === 0) { this.cacheReady = true; return; }

            const cache = await caches.open('ujian-images-v1').catch(() => null);
            if (!cache) { this.cacheReady = true; return; }

            // Batch 5 images at a time
            for (let i = 0; i < imageUrls.length; i += 5) {
                const batch = imageUrls.slice(i, i + 5);
                await Promise.all(batch.map(async url => {
                    try {
                        const resp = await fetch(url);
                        await cache.put(url, resp);
                        await db.image_status.put({ url, cached: true, error: false });
                    } catch {
                        await db.image_status.put({ url, cached: false, error: true });
                    }
                    this.cacheDone++;
                }));
            }

            this.cacheReady = true;
        },
    };
}

export { ujianApp };
