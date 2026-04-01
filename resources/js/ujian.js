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

        // Jawaban & status
        answers:         {}, // { soalId: { pg: [], teks: '', pasangan: {}, benarSalah: {}, terjawab: false } }
        tandaiList:      [], // [soalId, ...]
        pendingSync:     0,
        syncedSoalIds:   new Set(),

        // Sync control
        isSyncing:       false,
        _syncTimer:      null,
        _syncRetries:    0,
        _maxRetries:     5,
        _retryTimer:     null,
        _saveQueues:     {},
        _saveSequence:   0,
        _onlineSyncTimer: null,

        // Timer
        sisaWaktu:       0,
        durasiDetik:     0,
        mulaiAtTimestamp: null,
        waktuSelesaiSesi: null,
        timerInterval:   null,
        serverTimestamp: null,
        _timerDeadlineAt: null,
        showDurasiToast: false,
        durasiToastMsg:  '',

        // Incomplete BS/PGK warning
        showIncompleteWarning: false,
        incompleteWarningMsg:  '',
        _pendingNavIndex:      null,

        // Cache progress (pre-warm)
        cacheTotal:      0,
        cacheDone:       0,
        cacheReady:      false,

        // Anti-cheat
        violationCount:        0,
        maxViolations:         (window.UJIAN_CONFIG?.maxViolations || 3),
        showViolationOverlay:  false,
        violationMessage:      '',
        violationType:         '',
        isFullscreen:          false,
        isMobile:              /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
        _cheatingQueue:        [],
        _cheatingFlushTimer:   null,
        _lastViolationAt:      0,
        _orientationChanging:  false,
        _resizeDebounceTimer:  null,
        antiCurangDisabled:    false,
        _memoryFallbackAnswers: {},
        _ignoreFullscreenUntil: 0,
        showStartFullscreenOverlay: false,
        startFullscreenMessage: '',
        _navigateOverride: null,

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
            this.durasiDetik = cfg.durasiMenit * 60;
            this.serverTimestamp = cfg.serverTimestamp ?? null;
            this.mulaiAtTimestamp = cfg.mulaiAt ?? null;
            this.waktuSelesaiSesi = cfg.waktuSelesaiSesi ?? null;

            // Clear stale IDB data from other/previous sessions
            await this.clearStaleSessions(cfg.sesiPesertaId);

            // Restore state from IndexedDB
            await this.restoreState(cfg.sesiPesertaId);
            if (navigator.onLine) {
                await this.recalcPendingSync();
                if (this.pendingSync > 0) {
                    await this.syncToServer();
                }
            }

            // Sync timer dengan server
            this.startTimer(cfg.mulaiAt, cfg.durasiMenit);

            // Auto-save interval
            setInterval(() => this.autoSync(), cfg.autoSaveInterval * 1000);

            // Poll sesi status every 30s to detect admin ending session
            this.startStatusPolling();

            // Pre-cache semua gambar soal + opsi
            this.preCacheImages(cfg.soalList);

            // Init anti-cheat system (only if enabled in paket settings)
            if (cfg.antiCurang !== false) {
                this.initAntiCheat();
            } else {
                this.antiCurangDisabled = true;
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data?.type === 'TRIGGER_SYNC') {
                        this.syncTriggeredByServiceWorker();
                    }
                });
            }

            // Always warn on page unload (UX protection, not anti-cheat)
            window.addEventListener('beforeunload', (e) => {
                if (!this.isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'Ujian sedang berlangsung. Yakin ingin keluar?';
                    this._beaconSync();
                }
            });
        },

        // ===== SESI STATUS POLLING =====
        _statusCheckInterval: null,
        _forceSubmitted: false,

        startStatusPolling() {
            const cfg = window.UJIAN_CONFIG;
            if (!cfg.statusUrl) return;

            this._statusCheckInterval = setInterval(() => this.checkSesiStatus(), 30000);
        },

        async checkSesiStatus() {
            if (this._forceSubmitted || this.isSubmitting) return;

            const cfg = window.UJIAN_CONFIG;
            if (!cfg.statusUrl || !navigator.onLine) return;

            try {
                const res = await fetch(cfg.statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) return;

                const data = await res.json();

                // If sesi is no longer berlangsung, force submit
                if (data.sesi_status && data.sesi_status !== 'berlangsung') {
                    console.log('[StatusPoll] Sesi status:', data.sesi_status, '- force submitting...');
                    this._forceSubmitted = true;
                    clearInterval(this._statusCheckInterval);
                    await this.doSubmit();
                    return;
                }

                // If peserta status is already submit/dinilai server-side (admin forced)
                if (data.status && ['submit', 'dinilai'].includes(data.status)) {
                    console.log('[StatusPoll] Peserta status:', data.status, '- redirecting...');
                    this._forceSubmitted = true;
                    clearInterval(this._statusCheckInterval);
                    this.markIntentionalFullscreenExit();
                    window.location.href = '/ujian/' + cfg.sesiPesertaId + '/selesai';
                    return;
                }

                // Sync remaining time from server (drift correction)
                if (data.remaining_seconds !== undefined && data.remaining_seconds >= 0) {
                    const drift = Math.abs(this.sisaWaktu - data.remaining_seconds);
                    if (drift > 1) {
                        this.syncTimerFromServer(data.remaining_seconds, data.server_timestamp ?? null);
                    }
                }

                // Sync sesi waktu_selesai from server (may be set/changed by admin mid-exam)
                if (data.waktu_selesai_sesi !== undefined) {
                    this.waktuSelesaiSesi = data.waktu_selesai_sesi;
                }

                // Detect duration change from admin (durasi paket diubah saat ujian berlangsung)
                if (data.durasi_menit !== undefined) {
                    const newDurasiDetik = data.durasi_menit * 60;
                    if (newDurasiDetik !== this.durasiDetik) {
                        const oldMenit = Math.round(this.durasiDetik / 60);
                        const newMenit = data.durasi_menit;
                        console.log(`[StatusPoll] Duration changed: ${oldMenit} → ${newMenit} menit`);
                        this.durasiDetik = newDurasiDetik;

                        // Force-sync sisaWaktu from server immediately
                        if (data.remaining_seconds !== undefined) {
                            this.syncTimerFromServer(data.remaining_seconds, data.server_timestamp ?? null);
                        }

                        // Show toast notification to student
                        const diff = newMenit - oldMenit;
                        if (diff > 0) {
                            this.durasiToastMsg = `Durasi ujian ditambah ${diff} menit (sekarang ${newMenit} menit)`;
                        } else {
                            this.durasiToastMsg = `Durasi ujian dikurangi ${Math.abs(diff)} menit (sekarang ${newMenit} menit)`;
                        }
                        this.showDurasiToast = true;
                        setTimeout(() => { this.showDurasiToast = false; }, 8000);
                    }
                }

                // Runtime anti-curang toggle — admin can disable mid-exam
                if (data.anti_curang === false && !this.antiCurangDisabled) {
                    console.log('[StatusPoll] Anti-curang disabled by admin, destroying anti-cheat listeners...');
                    this.destroyAntiCheat();
                }

                // Server-side anti-cheat enforcement — sync violation count from server
                // Prevents client-side tampering (resetting violationCount via DevTools)
                if (!this.antiCurangDisabled && data.violation_count !== undefined && data.violation_count > this.violationCount) {
                    this.violationCount = data.violation_count;
                    if (this.violationCount >= this.maxViolations && !this._forceSubmitted) {
                        console.log('[StatusPoll] Server violation count exceeded, force submitting...');
                        this._forceSubmitted = true;
                        clearInterval(this._statusCheckInterval);
                        this.violationMessage = 'Anda telah melakukan pelanggaran sebanyak ' + this.maxViolations + ' kali. Ujian akan otomatis dikumpulkan.';
                        this.showViolationOverlay = true;
                        setTimeout(() => {
                            this.showViolationOverlay = false;
                            this.doSubmit();
                        }, 3000);
                        return;
                    }
                }
            } catch (err) {
                console.warn('[StatusPoll] Check failed:', err.message);
            }
        },

        // ===== ANTI-CHEAT SYSTEM =====
        markIntentionalFullscreenExit(duration = 8000) {
            this._ignoreFullscreenUntil = Date.now() + duration;
        },

        shouldIgnoreFullscreenExit() {
            return this.antiCurangDisabled || this.isSubmitting || Date.now() < this._ignoreFullscreenUntil;
        },

        async syncTriggeredByServiceWorker() {
            await this.recalcPendingSync();
            if (this.pendingSync > 0) {
                await this.syncToServer();
            }
        },

        async markRowsSyncedIfCurrent(rows) {
            if (!rows.length) return [];

            const markedSoalIds = [];
            await Promise.all(rows.map(async (item) => {
                const current = await db.exam_answers.get(item.id);
                if (!current || current.idempotencyKey !== item.idempotencyKey) {
                    return;
                }

                await db.exam_answers.update(item.id, { synced: true });
                markedSoalIds.push(String(item.soalId));
            }));

            return markedSoalIds;
        },

        // ===== ANTI-CHEAT SYSTEM =====
        destroyAntiCheat() {
            this.antiCurangDisabled = true;
            this.showViolationOverlay = false;
            this.violationCount = 0;

            // Remove bound listeners
            if (this._acHandlers) {
                document.removeEventListener('fullscreenchange', this._acHandlers.fullscreen);
                document.removeEventListener('webkitfullscreenchange', this._acHandlers.fullscreen);
                window.removeEventListener('blur', this._acHandlers.blur);
                document.removeEventListener('copy', this._acHandlers.clipboard);
                document.removeEventListener('cut', this._acHandlers.clipboard);
                document.removeEventListener('paste', this._acHandlers.clipboard);
                document.removeEventListener('keydown', this._acHandlers.keydown);
                document.removeEventListener('contextmenu', this._acHandlers.contextmenu);
                document.removeEventListener('keyup', this._acHandlers.printscreen);
                window.removeEventListener('resize', this._acHandlers.resize);
                this._acHandlers = null;
            }

            // Exit fullscreen if currently in it
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                this.markIntentionalFullscreenExit();
                (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
            }

            // Remove select-none restriction
            document.body.classList.remove('select-none');

            console.log('[AntiCheat] Destroyed — anti-curang disabled by admin');
        },

        initAntiCheat() {
            // 1. Track fullscreen state
            this.isFullscreen = !!document.fullscreenElement || !!document.webkitFullscreenElement;

            // 2. Handle desktop exam start without requesting fullscreen on the previous page.
            if (!this.isMobile) {
                this.handlePendingFullscreenStart();
                this.ensureFullscreenOnStart();
            }

            // Store bound handlers for later removal (destroyAntiCheat)
            this._acHandlers = {
                fullscreen:  () => this.handleFullscreenChange(),
                blur:        () => this.handleWindowBlur(),
                clipboard:   (e) => this.handleClipboard(e),
                keydown:     (e) => this.handleKeydown(e),
                contextmenu: (e) => { e.preventDefault(); this.logCheating('klik_kanan', { target: e.target?.tagName }); },
                printscreen: (e) => { if (e.key === 'PrintScreen') this.logCheating('screenshot_attempt', { key: 'PrintScreen' }); },
                resize:      () => this.handleResizeFullscreenCheck(),
            };

            // 3. Fullscreen change listener (desktop only)
            if (!this.isMobile) {
                document.addEventListener('fullscreenchange', this._acHandlers.fullscreen);
                document.addEventListener('webkitfullscreenchange', this._acHandlers.fullscreen);
            }

            // 4. Window blur detection
            window.addEventListener('blur', this._acHandlers.blur);

            // 5. Copy/Cut/Paste blocking
            document.addEventListener('copy', this._acHandlers.clipboard);
            document.addEventListener('cut', this._acHandlers.clipboard);
            document.addEventListener('paste', this._acHandlers.clipboard);

            // 6. Keyboard shortcut blocking (DevTools, etc)
            document.addEventListener('keydown', this._acHandlers.keydown);

            // 7. Right-click blocking
            document.addEventListener('contextmenu', this._acHandlers.contextmenu);

            // 8. Print screen detection
            document.addEventListener('keyup', this._acHandlers.printscreen);

            // 9. Resize fallback — detect fullscreen exit (desktop only)
            if (!this.isMobile) {
                this._lastInnerHeight = window.innerHeight;
                window.addEventListener('resize', this._acHandlers.resize);
            }

            // 10. Mobile: track orientation changes to suppress false positives
            if (this.isMobile) {
                window.addEventListener('orientationchange', () => {
                    this._orientationChanging = true;
                    // Give browser time to complete orientation transition + layout reflow
                    clearTimeout(this._resizeDebounceTimer);
                    this._resizeDebounceTimer = setTimeout(() => {
                        this._orientationChanging = false;
                    }, 2000);
                });
            }
        },

        handleResizeFullscreenCheck() {
            // If screen height shrunk significantly and we're not in API fullscreen,
            // it likely means the user exited browser fullscreen (F11 or ESC)
            const heightDropped = window.innerHeight < screen.height * 0.85;
            const notInApiFullscreen = !document.fullscreenElement && !document.webkitFullscreenElement;

            if (heightDropped && notInApiFullscreen && !this.showViolationOverlay && !this.shouldIgnoreFullscreenExit()) {
                this.logCheating('fullscreen_exit', { trigger: 'resize_detection' });
                this.recordViolation('fullscreen_exit', 'Anda keluar dari mode layar penuh. Klik tombol di bawah untuk kembali ke mode fullscreen.');
            }
        },

        handleFullscreenChange() {
            const wasFullscreen = this.isFullscreen;
            this.isFullscreen = !!document.fullscreenElement || !!document.webkitFullscreenElement;

            if (wasFullscreen && !this.isFullscreen) {
                if (this.shouldIgnoreFullscreenExit()) {
                    return;
                }

                // Exited fullscreen
                this.logCheating('fullscreen_exit');
                this.recordViolation('fullscreen_exit', 'Anda keluar dari mode layar penuh. Klik tombol di bawah untuk kembali ke mode fullscreen.');
            } else if (!wasFullscreen && this.isFullscreen) {
                // Entered fullscreen
                this.logCheating('fullscreen_enter');
            }
        },

        handleWindowBlur() {
            // Only count if not caused by our own overlay
            if (!this.showViolationOverlay && !this.showSubmitModal && !this.showStartFullscreenOverlay) {
                this.logCheating('tidak_fokus', { timestamp: Date.now() });
            }
        },

        handleClipboard(e) {
            e.preventDefault();
            this.logCheating('copy_paste', { action: e.type });
        },

        handleKeydown(e) {
            // Block F11 (browser fullscreen toggle — bypasses Fullscreen API)
            if (e.key === 'F11') {
                e.preventDefault();
                // If not in fullscreen, re-enter via API
                if (!document.fullscreenElement) {
                    this.requestFullscreen();
                }
                return;
            }

            // Block F5 (refresh)
            if (e.key === 'F5') {
                e.preventDefault();
                return;
            }

            // Block F12 (DevTools)
            if (e.key === 'F12') {
                e.preventDefault();
                this.logCheating('klik_kanan', { key: 'F12' });
                return;
            }

            // Block Ctrl+Shift+I/J/C (DevTools)
            if (e.ctrlKey && e.shiftKey && ['I','i','J','j','C','c'].includes(e.key)) {
                e.preventDefault();
                this.logCheating('klik_kanan', { key: `Ctrl+Shift+${e.key}` });
                return;
            }

            // Block Ctrl+U (view source)
            if (e.ctrlKey && (e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                this.logCheating('klik_kanan', { key: 'Ctrl+U' });
                return;
            }

            // Block Ctrl+S (save)
            if (e.ctrlKey && (e.key === 's' || e.key === 'S') && !e.shiftKey) {
                e.preventDefault();
                return;
            }

            // Block Ctrl+P (print)
            if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                return;
            }

            // Block Escape (prevent exiting fullscreen without overlay)
            if (e.key === 'Escape') {
                e.preventDefault();
                return;
            }
        },

        recordViolation(type, message) {
            if (this.antiCurangDisabled) return;
            // Debounce: ignore violations within 2s of each other
            // (single Alt+Tab can trigger fullscreenchange + resize + visibilitychange simultaneously)
            const now = Date.now();
            if (now - this._lastViolationAt < 2000) return;
            this._lastViolationAt = now;

            this.violationCount++;
            this.violationType = type;
            this.violationMessage = message;
            this.showViolationOverlay = true;

            if (this.violationCount >= this.maxViolations) {
                // Auto-submit after short delay so they see the message
                this.violationMessage = 'Anda telah melakukan pelanggaran sebanyak ' + this.maxViolations + ' kali. Ujian akan otomatis dikumpulkan.';
                setTimeout(() => {
                    this.showViolationOverlay = false;
                    this.logCheating('tidak_fokus', { reason: 'auto_submit_pelanggaran', count: this.violationCount });
                    this.autoSubmitViolation();
                }, 3000);
            }
        },

        async returnToFullscreen() {
            this.showViolationOverlay = false;
            if (!this.isMobile) {
                await this.requestFullscreen();
            }
        },

        handlePendingFullscreenStart() {
            const pendingFullscreen = window.sessionStorage.getItem('ujian-pending-fullscreen') === '1';

            if (!pendingFullscreen) {
                if (!this.isFullscreen) {
                    this.requestFullscreen();
                }
                return;
            }

            window.sessionStorage.removeItem('ujian-pending-fullscreen');
            this.markIntentionalFullscreenExit(12000);
            this.showStartFullscreenOverlay = true;
            this.startFullscreenMessage = 'Klik tombol di bawah untuk masuk fullscreen dan mulai mengerjakan ujian.';
        },

        async enterExamFullscreen() {
            this.startFullscreenMessage = 'Meminta izin fullscreen...';
            const enteredFullscreen = await this.requestFullscreen();

            if (enteredFullscreen) {
                this.showStartFullscreenOverlay = false;
                this.startFullscreenMessage = '';
                return;
            }

            this.showStartFullscreenOverlay = true;
            this.startFullscreenMessage = 'Browser menolak fullscreen otomatis. Klik lagi tombol fullscreen atau aktifkan izin fullscreen pada browser Anda.';
        },

        ensureFullscreenOnStart() {
            const retryDelays = [250, 700, 1400];

            retryDelays.forEach((delay) => {
                window.setTimeout(() => {
                    if (
                        this.isMobile ||
                        this.isFullscreen ||
                        this.showStartFullscreenOverlay ||
                        document.fullscreenElement ||
                        document.webkitFullscreenElement
                    ) {
                        return;
                    }

                    this.requestFullscreen();
                }, delay);
            });
        },

        async autoSubmitViolation() {
            console.log('[Anti-Cheat] Max violations reached, auto-submit...');
            await this.doSubmit();
        },

        async clearStaleSessions(currentSesiPesertaId) {
            try {
                // Delete all answer rows NOT belonging to the current session
                const staleAnswers = await db.exam_answers
                    .where('sesiPesertaId').notEqual(currentSesiPesertaId)
                    .toArray();
                if (staleAnswers.length > 0) {
                    await db.exam_answers.bulkDelete(staleAnswers.map(a => a.id));
                    console.log(`[Init] Cleared ${staleAnswers.length} stale IDB answer rows`);
                }

                // Delete all exam_state rows NOT belonging to the current session
                const allStates = await db.exam_state.toArray();
                const staleStates = allStates.filter(s => s.sesiPesertaId !== currentSesiPesertaId);
                if (staleStates.length > 0) {
                    await db.exam_state.bulkDelete(staleStates.map(s => s.sesiPesertaId));
                    console.log(`[Init] Cleared ${staleStates.length} stale IDB state rows`);
                }
            } catch (e) {
                console.warn('[Init] Could not clear stale IDB sessions:', e.message);
            }
        },

        // ===== RESTORE STATE =====
        async restoreState(sesiPesertaId) {
            const cfg = window.UJIAN_CONFIG;

            let state = null;
            try {
                state = await db.exam_state.get(sesiPesertaId);
            } catch (e) {
                console.warn('[Restore] IndexedDB state read failed:', e.message);
            }

            // Muat jawaban dari IndexedDB (graceful fallback if IDB unavailable)
            let localAnswers = [];
            try {
                localAnswers = await db.exam_answers
                    .where('sesiPesertaId').equals(sesiPesertaId)
                    .toArray();
            } catch (e) {
                console.warn('[Restore] IndexedDB read failed, using server data only:', e.message);
            }

            // Detect exam reset: server has no answers but IDB has old data.
            // Do not wipe the current session if a pending submit snapshot still exists.
            const serverAnswerCount = cfg.jawabanExisting?.length ?? 0;
            const hasPendingSubmit = Boolean(state?.pendingSubmit)
                || (typeof state?.pendingSubmitCount === 'number' && state.pendingSubmitCount > 0)
                || (Array.isArray(state?.pendingSubmitPayload) && state.pendingSubmitPayload.length > 0);
            if (localAnswers.length > 0 && serverAnswerCount === 0 && !hasPendingSubmit) {
                console.log(`[Restore] Server has 0 answers but IDB has ${localAnswers.length} — exam was reset, clearing IDB`);
                try {
                    await db.exam_answers.where('sesiPesertaId').equals(sesiPesertaId).delete();
                    await db.exam_state.delete(sesiPesertaId);
                } catch (e) {
                    console.warn('[Restore] IDB cleanup failed:', e.message);
                }
                localAnswers = [];
            }

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
                                map[String(pair[0])] = String(pair[1]);
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
                const soalId = String(ans.soalId);
                this.answers[soalId] = {
                    pg:         ans.jawaban?.pg         ?? [],
                    teks:       ans.jawaban?.teks       ?? '',
                    pasangan:   ans.jawaban?.pasangan   ?? {},
                    benarSalah: ans.jawaban?.benarSalah ?? {},
                    terjawab:   ans.jawaban?.terjawab   ?? false,
                };
            });

            this.syncedSoalIds = new Set(
                localAnswers
                    .filter(a => a.synced)
                    .map(a => String(a.soalId))
            );

            this.pendingSync = localAnswers.filter(a => !a.synced).length;

            // Muat state (posisi, tandai) — IndexedDB tandaiList takes priority if exists
            try {
                const state = await db.exam_state.get(sesiPesertaId);
                if (state) {
                    this.currentIndex = state.currentIndex ?? 0;
                    if (state.tandaiList?.length > 0) {
                        this.tandaiList = state.tandaiList;
                    }
                }
            } catch (e) {
                console.warn('[Restore] IndexedDB state read failed:', e.message);
            }
        },

        // ===== TIMER (SERVER-AUTHORITATIVE) =====
        syncTimerFromServer(remainingSeconds, serverTimestamp = null) {
            const safeRemaining = Math.max(0, Number(remainingSeconds) || 0);
            this.serverTimestamp = serverTimestamp ?? this.serverTimestamp;
            this.sisaWaktu = safeRemaining;
            this._timerDeadlineAt = Date.now() + (safeRemaining * 1000);
        },

        startTimer(mulaiAtTimestamp, durasiMenit) {
            this.syncTimerFromServer(this.sisaWaktu, this.serverTimestamp);

            const tick = () => {
                if (this._timerDeadlineAt === null) {
                    this.syncTimerFromServer(this.sisaWaktu, this.serverTimestamp);
                }

                this.sisaWaktu = Math.max(0, Math.ceil((this._timerDeadlineAt - Date.now()) / 1000));

                if (this.sisaWaktu <= 0) {
                    clearInterval(this.timerInterval);
                    this.autoSubmit();
                }
            };

            tick();
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
        goToSoal(index, force = false) {
            if (!force) {
                const warning = this.checkIncompleteAnswer();
                if (warning) {
                    this._pendingNavIndex = index;
                    this.incompleteWarningMsg = warning;
                    this.showIncompleteWarning = true;
                    return;
                }
            }
            this.currentIndex = index;
            this.saveState();
            // Scroll to top
            document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' });
        },
        nextSoal() { if (this.currentIndex < this.totalSoal - 1) this.goToSoal(this.currentIndex + 1); },
        prevSoal() { if (this.currentIndex > 0) this.goToSoal(this.currentIndex - 1); },

        dismissIncompleteWarning() {
            this.showIncompleteWarning = false;
        },
        forceNavigate() {
            this.showIncompleteWarning = false;
            this.showNavigator = false;
            if (this._pendingNavIndex !== null) {
                this.goToSoal(this._pendingNavIndex, true);
                this._pendingNavIndex = null;
            }
        },

        checkIncompleteAnswer() {
            const cfg = window.UJIAN_CONFIG;
            const currentSoal = cfg.soalList?.[this.currentIndex];
            if (!currentSoal) return null;

            const soalId = currentSoal.id;
            const tipe = currentSoal.tipe_soal;
            const opsiCount = currentSoal.opsi?.length ?? 0;

            if (tipe === 'benar_salah') {
                const answered = Object.keys(this.answers[soalId]?.benarSalah ?? {}).length;
                if (answered > 0 && answered < opsiCount) {
                    return `Anda baru menjawab ${answered} dari ${opsiCount} pernyataan. Pastikan semua pernyataan sudah dijawab Benar atau Salah.`;
                }
            }

            return null;
        },

        // ===== JAWABAN =====
        getAnswer(soalId) {
            return this.answers[soalId] ?? { pg: [], teks: '', pasangan: {}, benarSalah: {}, terjawab: false };
        },

        isSelected(soalId, label) {
            return (this.answers[soalId]?.pg ?? []).includes(label);
        },

        cloneJawabanData(jawabanData) {
            return {
                ...jawabanData,
                pg: Array.isArray(jawabanData?.pg) ? [...jawabanData.pg] : jawabanData?.pg,
                pasangan: jawabanData?.pasangan ? { ...jawabanData.pasangan } : jawabanData?.pasangan,
                benarSalah: jawabanData?.benarSalah ? { ...jawabanData.benarSalah } : jawabanData?.benarSalah,
            };
        },

        nextAnswerIdempotencyKey(soalId) {
            this._saveSequence += 1;
            return `${window.UJIAN_CONFIG.sesiPesertaId}-${soalId}-${Date.now()}-${this._saveSequence}`;
        },

        queueAnswerSave(soalId, saveOperation) {
            const previousQueue = this._saveQueues[soalId] ?? Promise.resolve();
            const nextQueue = previousQueue
                .catch(() => {})
                .then(saveOperation);

            this._saveQueues[soalId] = nextQueue.finally(() => {
                if (this._saveQueues[soalId] === nextQueue) {
                    delete this._saveQueues[soalId];
                }
            });

            return this._saveQueues[soalId];
        },

        isAnswered(soalId) {
            return this.answers[soalId]?.terjawab ?? false;
        },

        isSynced(soalId) {
            return this.syncedSoalIds.has(soalId);
        },

        isPendingSync(soalId) {
            return this.isAnswered(soalId) && !this.syncedSoalIds.has(soalId);
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

        savePasangan(soalId, kiriId, kananValue) {
            const ans = this.getAnswer(soalId);
            if (!kananValue) {
                delete ans.pasangan[kiriId];
            } else {
                ans.pasangan[kiriId] = kananValue;
            }
            ans.terjawab = Object.keys(ans.pasangan).length > 0;
            this.answers[soalId] = ans;
            this.saveJawaban(soalId, { pasangan: ans.pasangan, terjawab: ans.terjawab });
        },

        getPasanganJawaban(soalId, kiriId) {
            return this.answers[soalId]?.pasangan?.[kiriId] ?? null;
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

        getBsAnsweredCount(soalId) {
            return Object.keys(this.answers[soalId]?.benarSalah ?? {}).length;
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
            const cfg = window.UJIAN_CONFIG;
            const payload = this.cloneJawabanData(jawabanData);
            const idempotencyKey = this.nextAnswerIdempotencyKey(soalId);
            const updatedAt = Date.now();

            // 1. Simpan ke IndexedDB lebih dulu dan serial per soal agar perubahan cepat tidak saling menimpa.
            this.isSaving = true;
            this.syncedSoalIds.delete(soalId);

            await this.queueAnswerSave(soalId, async () => {
                try {
                    const existing = await db.exam_answers
                        .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                        .and(item => item.soalId === soalId)
                        .first();

                    if (existing) {
                        await db.exam_answers.update(existing.id, {
                            jawaban: payload,
                            synced: false,
                            idempotencyKey,
                            updatedAt,
                        });
                    } else {
                        await db.exam_answers.add({
                            sesiPesertaId: cfg.sesiPesertaId,
                            soalId,
                            jawaban: payload,
                            synced: false,
                            idempotencyKey,
                            updatedAt,
                        });
                    }
                } catch (idbErr) {
                    // IndexedDB unavailable (private browsing, storage full, etc.)
                    // Fallback: keep in-memory and force immediate server sync
                    console.warn('[Save] IndexedDB write failed, using memory fallback:', idbErr.message);
                    this._memoryFallbackAnswers = this._memoryFallbackAnswers || {};
                    this._memoryFallbackAnswers[soalId] = { jawaban: payload, idempotencyKey, updatedAt };
                }
            });

            this.lastSaved = true;
            this.isSaving = Object.keys(this._saveQueues).length > 0;

            // Recalculate pendingSync from IndexedDB (prevents drift)
            await this.recalcPendingSync();

            // 2. Debounced sync ke server jika online setelah jeda perubahan (coalesce rapid clicks)
            if (navigator.onLine) {
                this.debouncedSync();
            }
        },

        async saveState() {
            try {
                const cfg = window.UJIAN_CONFIG;
                await db.exam_state.put({
                    sesiPesertaId: cfg.sesiPesertaId,
                    currentIndex:  this.currentIndex,
                    tandaiList:    this.tandaiList,
                    sesiToken:     cfg.sesiToken,
                    lastSyncAt:    Date.now(),
                });
            } catch (e) {
                console.warn('[State] IndexedDB save failed:', e.message);
            }
        },

        // ===== SYNC TO SERVER =====
        async recalcPendingSync() {
            try {
                const cfg = window.UJIAN_CONFIG;
                const count = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .and(item => !item.synced)
                    .count();
                this.pendingSync = count;
            } catch { /* ignore */ }
        },

        async flushPendingAnswerWrites() {
            const queues = Object.values(this._saveQueues);
            if (queues.length === 0) {
                this.isSaving = false;
                return;
            }

            await Promise.allSettled(queues);
            this.isSaving = Object.keys(this._saveQueues).length > 0;
        },

        debouncedSync() {
            if (this._syncTimer) clearTimeout(this._syncTimer);
            this._syncTimer = setTimeout(() => this.syncToServer(), 800);
        },

        async autoSync() {
            if (navigator.onLine) {
                // Recalculate in case something drifted
                await this.recalcPendingSync();
                if (this.pendingSync > 0) {
                    await this.syncToServer();
                }
            }
        },

        async syncToServer() {
            if (this.isSyncing) return;
            this.isSyncing = true;

            await this.flushPendingAnswerWrites();

            const cfg     = window.UJIAN_CONFIG;
            let pending;
            try {
                pending = await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .and(item => !item.synced)
                    .toArray();
            } catch {
                pending = [];
            }

            // Merge memory-fallback answers (from IDB failure), skip duplicates already in IDB
            const memAnswers = this._memoryFallbackAnswers || {};
            const memEntries = Object.entries(memAnswers);
            const pendingSoalIds = new Set(pending.map(p => String(p.soalId)));

            if (pending.length === 0 && memEntries.length === 0) {
                this.isSyncing = false;
                this.pendingSync = 0;
                return;
            }

            // Format untuk API
            const answers = pending.map(item => ({
                soal_id:           item.soalId,
                jawaban:           this.formatJawabanForApi(item.jawaban),
                idempotency_key:   item.idempotencyKey,
                client_timestamp:  item.updatedAt,
            }));

            // Add memory-fallback answers (skip duplicates already in IDB pending)
            memEntries.forEach(([soalId, item]) => {
                if (pendingSoalIds.has(String(soalId))) return;
                answers.push({
                    soal_id:           soalId,
                    jawaban:           this.formatJawabanForApi(item.jawaban),
                    idempotency_key:   item.idempotencyKey,
                    client_timestamp:  item.updatedAt,
                });
            });

            const controller = new AbortController();
            const syncTimeoutId = setTimeout(() => controller.abort(), 20000);

            try {
                const res = await fetch(cfg.syncUrl, {
                    method:  'POST',
                    signal:  controller.signal,
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        sesi_token: cfg.sesiToken,
                        answers,
                        soal_ditandai: this.tandaiList.length,
                        tandai_list: this.tandaiList,
                    }),
                });

                clearTimeout(syncTimeoutId);

                let data = {};
                try { data = await res.json(); } catch (e) { /* non-JSON response */ }

                if (res.ok && data.accepted !== false) {
                    const markedSoalIds = await this.markRowsSyncedIfCurrent(pending);

                    // Clear only memory-fallback answers that were actually sent in this request.
                    memEntries.forEach(([soalId, item]) => {
                        if (this._memoryFallbackAnswers?.[soalId]?.idempotencyKey === item.idempotencyKey) {
                            delete this._memoryFallbackAnswers[soalId];
                        }
                    });

                    // Recalculate from IndexedDB (source of truth)
                    await this.recalcPendingSync();
                    this._syncRetries = 0;

                    // Track synced soal IDs for navigator indicator
                    markedSoalIds.forEach(soalId => this.syncedSoalIds.add(soalId));
                    memEntries.forEach(([soalId]) => this.syncedSoalIds.add(String(soalId)));
                } else if (res.status === 422 || data.accepted === false) {
                    const errMsg = data.error || data.message || (Array.isArray(data.errors) ? data.errors.join(', ') : '');
                    console.warn('[Sync] Validation error (422):', errMsg);

                    const isExpired = /(habis|expired)/i.test(errMsg);
                    const isAlreadySubmitted = /(selesai|sudah (di)?submit|sudah (di)?kumpul)/i.test(errMsg);
                    if (isExpired && !this.isSubmitting) {
                        this.syncTimerFromServer(0, data.server_time ?? null);
                        await this.doSubmit();
                        return;
                    }

                    if (isAlreadySubmitted) {
                        this.markIntentionalFullscreenExit();
                        window.location.replace('/ujian/' + cfg.sesiPesertaId + '/selesai');
                        return;
                    }

                    if (this._syncRetries < 2) {
                        this._scheduleRetry(5000);
                    } else {
                        console.warn('[Sync] 422 persistent, waiting for next auto-sync');
                        this._syncRetries = 0;
                    }
                } else if (res.status === 429) {
                    // Rate limited — retry after longer delay
                    console.warn('[Sync] Rate limited (429), backing off...');
                    this._scheduleRetry(10000);
                } else {
                    // 500, 503, etc — retry with backoff
                    console.warn('[Sync] Server error:', res.status);
                    this._scheduleRetry();
                }
            } catch (err) {
                clearTimeout(syncTimeoutId);
                // Network error or timeout — retry with backoff
                console.warn('[Sync] Network error, will retry:', err.message);
                this._scheduleRetry();
            } finally {
                this.isSyncing = false;
            }
        },

        _scheduleRetry(forceDelay) {
            this._syncRetries++;
            if (this._syncRetries > this._maxRetries) {
                console.warn('[Sync] Max retries reached, waiting for next auto-sync interval');
                this._syncRetries = 0;
                // Register SW background sync as fallback
                this._registerBackgroundSync();
                return;
            }
            const delay = forceDelay ?? Math.min(1000 * Math.pow(2, this._syncRetries - 1), 30000);
            if (this._retryTimer) clearTimeout(this._retryTimer);
            this._retryTimer = setTimeout(() => this.syncToServer(), delay);
        },

        async _registerBackgroundSync() {
            try {
                if ('serviceWorker' in navigator && 'SyncManager' in window) {
                    const reg = await navigator.serviceWorker.ready;
                    await reg.sync.register('jawaban-sync');
                    console.log('[Sync] Background sync registered');
                }
            } catch (e) {
                console.warn('[Sync] Background sync registration failed:', e.message);
            }
        },

        _beaconSync() {
            // Fire-and-forget sync via sendBeacon — works even during page unload/close
            // This is a last resort to push any unsent answers before the browser closes
            try {
                const cfg = window.UJIAN_CONFIG;
                if (!cfg || !navigator.sendBeacon) return;

                // Collect answers from in-memory state (this.answers) since IDB read is async
                const allAnswers = [];
                Object.entries(this.answers).forEach(([soalId, ans]) => {
                    const formatted = this.formatJawabanForApi(ans);
                    if (formatted !== null) {
                        allAnswers.push({
                            soal_id:          soalId,
                            jawaban:          formatted,
                            idempotency_key:  `beacon-${cfg.sesiPesertaId}-${soalId}`,
                            client_timestamp: Date.now(),
                        });
                    }
                });

                if (allAnswers.length === 0) return;

                const blob = new Blob([JSON.stringify({
                    sesi_token:    cfg.sesiToken,
                    answers:       allAnswers,
                    soal_ditandai: this.tandaiList.length,
                    tandai_list:   this.tandaiList,
                })], { type: 'application/json' });

                navigator.sendBeacon(cfg.syncUrl, blob);
            } catch (e) {
                // Silent — this is a best-effort last-chance sync
            }
        },

        formatJawabanForApi(jawaban) {
            if (jawaban.pg?.length > 0)                        return jawaban.pg;
            if (jawaban.benarSalah && Object.keys(jawaban.benarSalah).length > 0) return jawaban.benarSalah;
            if (jawaban.pasangan && Object.keys(jawaban.pasangan).length > 0)     return Object.entries(jawaban.pasangan).map(([k,v]) => [k, v]);
            if (jawaban.teks !== undefined && jawaban.teks !== '')                 return jawaban.teks;
            if (jawaban.terjawab === false)                                        return '';
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
            const selesaiUrl = '/ujian/' + cfg.sesiPesertaId + '/selesai';

            const navigateTo = (url) => {
                if (typeof this._navigateOverride === 'function') {
                    this._navigateOverride(url);
                    return;
                }

                // Force navigation — use replace to avoid back-button returning to exam
                try { window.location.replace(url); } catch (e) { window.location.href = url; }
                // Fallback: if still here after 3s, force reload
                setTimeout(() => { window.location.href = url; }, 3000);
            };

            // Safety timeout: force navigate after 35s no matter what
            const submitSafetyTimer = setTimeout(() => {
                console.warn('[Submit] Safety timeout reached (35s), forcing navigation');
                navigateTo(selesaiUrl);
            }, 35000);

            try {
                await this.flushPendingAnswerWrites();

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
                    })).filter(a => a.jawaban !== null);
                } catch (e) {
                    console.warn('[Submit] Could not read IndexedDB, using in-memory answers:', e.message);
                }

                // Merge memory fallback answers so partial IDB failures do not lose unsent answers.
                const answersBySoalId = new Map(allAnswers.map(item => [String(item.soal_id), item]));
                Object.entries(this._memoryFallbackAnswers || {}).forEach(([soalId, ans]) => {
                    const formatted = this.formatJawabanForApi(ans.jawaban);
                    if (formatted === null || answersBySoalId.has(String(soalId))) {
                        return;
                    }

                    answersBySoalId.set(String(soalId), {
                        soal_id: soalId,
                        jawaban: formatted,
                        idempotency_key: ans.idempotencyKey || `mem-${cfg.sesiPesertaId}-${soalId}-${Date.now()}`,
                        client_timestamp: ans.updatedAt || Date.now(),
                    });
                });
                allAnswers = Array.from(answersBySoalId.values());

                // Fallback: if IDB failed and we still have no answers, gather from in-memory state.
                if (allAnswers.length === 0 && Object.keys(this.answers).length > 0) {
                    Object.entries(this.answers).forEach(([soalId, ans]) => {
                        const formatted = this.formatJawabanForApi(ans);
                        if (formatted !== null) {
                            allAnswers.push({
                                soal_id:          soalId,
                                jawaban:          formatted,
                                idempotency_key:  `mem-${cfg.sesiPesertaId}-${soalId}-${Date.now()}`,
                                client_timestamp: Date.now(),
                            });
                        }
                    });
                }

                this.markIntentionalFullscreenExit();

                if (!navigator.onLine) {
                    await this.queueOfflineSubmit(cfg, allAnswers);
                    clearTimeout(submitSafetyTimer);
                    this.showSubmitModal = false;
                    return navigateTo(selesaiUrl);
                }

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000);

                try {
                    const res = await fetch(cfg.submitUrl, {
                        method:  'POST',
                        signal:  controller.signal,
                        headers: {
                            'Content-Type':  'application/json',
                            'Accept':        'application/json',
                        },
                        body: JSON.stringify({
                            sesi_token: cfg.sesiToken,
                            answers:    allAnswers,
                        }),
                    });

                    clearTimeout(timeoutId);

                    if (res.ok) {
                        let data = {};
                        try { data = await res.json(); } catch (e) { /* non-JSON response */ }

                        if (data.accepted === false || data.error) {
                            throw new Error(data.error || data.message || 'Submit ditolak server');
                        }

                        // Don't delete IDB — selesai page will verify & clean up
                        clearTimeout(submitSafetyTimer);
                        this.showSubmitModal = false;
                        navigateTo(data.redirect ?? selesaiUrl);
                    } else {
                        console.warn('[Submit] Server error:', res.status);
                        await this.queueOfflineSubmit(cfg, allAnswers);
                        clearTimeout(submitSafetyTimer);
                        this.showSubmitModal = false;
                        navigateTo(selesaiUrl);
                    }
                } catch (err) {
                    clearTimeout(timeoutId);
                    console.warn('[Submit] Fetch failed:', err.message);
                    await this.queueOfflineSubmit(cfg, allAnswers);
                    clearTimeout(submitSafetyTimer);
                    navigateTo(selesaiUrl);
                }
            } catch (outerErr) {
                console.error('[Submit] Unexpected error:', outerErr);
                clearTimeout(submitSafetyTimer);
                this.showSubmitModal = false;
                navigateTo(selesaiUrl);
            } finally {
                // Reset isSubmitting so button is usable if navigation somehow fails
                setTimeout(() => { this.isSubmitting = false; }, 5000);
                if (this.showSubmitModal) {
                    this.showSubmitModal = true;
                }
            }
        },

        async autoSubmit() {
            console.log('[Timer] Waktu habis, auto-submit...');
            await this.doSubmit();
        },

        async queueOfflineSubmit(cfg, answersSnapshot = []) {
            // Tag semua jawaban sebagai perlu submit + reset synced flag agar selesai page kirim ulang SEMUA jawaban.
            try {
                // Reset ALL answer rows to synced:false so selesai page re-sends everything
                await db.exam_answers
                    .where('sesiPesertaId').equals(cfg.sesiPesertaId)
                    .modify({ synced: false });

                await db.exam_state.put({
                    sesiPesertaId:  cfg.sesiPesertaId,
                    currentIndex:   this.currentIndex,
                    tandaiList:     this.tandaiList,
                    pendingSubmit:  true,
                    pendingSubmitPayload: Array.isArray(answersSnapshot) ? answersSnapshot : [],
                    pendingSubmitCount: answersSnapshot.length,
                    pendingSubmitQueuedAt: Date.now(),
                    sesiToken:      cfg.sesiToken,
                    lastSyncAt:     Date.now(),
                });

            } catch (e) {
                console.warn('[Submit] Could not queue offline submit to IDB:', e.message);
            }

            // Also try sendBeacon as last-ditch effort
            this._beaconSync();
        },

        // ===== ANTI-CHEAT: LOGGING =====
        onVisibilityChange() {
            if (this.antiCurangDisabled) return;
            if (document.hidden) {
                // Suppress false positive during orientation change on mobile
                if (this.isMobile && this._orientationChanging) {
                    this.logCheating('ganti_tab', { suppressed: true, reason: 'orientation_change' });
                    return;
                }
                this.logCheating('ganti_tab');
                this.recordViolation('ganti_tab', 'Anda berpindah tab atau meminimalkan browser. Tindakan ini tercatat sebagai pelanggaran.');
            }
        },

        logCheating(event, detail = {}) {
            if (this.antiCurangDisabled) return;
            // Queue cheating events and send in batch to reduce requests
            this._cheatingQueue.push({ event, detail, ts: Date.now() });

            if (this._cheatingFlushTimer) clearTimeout(this._cheatingFlushTimer);
            this._cheatingFlushTimer = setTimeout(() => this.flushCheatingQueue(), 1000);
        },

        async flushCheatingQueue() {
            if (this._cheatingQueue.length === 0) return;

            const cfg = window.UJIAN_CONFIG;
            const batch = [...this._cheatingQueue];
            this._cheatingQueue = [];

            // Send each event (batch could be consolidated, but enum constraint means per-event)
            for (const item of batch) {
                try {
                    await fetch('/api/ujian/log-cheating', {
                        method:  'POST',
                        headers: {
                            'Content-Type':  'application/json',
                            'Accept':        'application/json',
                        },
                        body: JSON.stringify({
                            token:  cfg.sesiToken,
                            event:  item.event,
                            detail: item.detail,
                        }),
                    });
                } catch (e) {
                    // Silent fail - don't break exam for logging failure
                    console.warn('[Anti-Cheat] Log failed:', e.message);
                }
            }
        },

        async requestFullscreen() {
            if (this.isMobile) return false;
            const el = document.documentElement;

            try {
                if (el.requestFullscreen) {
                    await el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    await el.webkitRequestFullscreen();
                }

                this.isFullscreen = !!document.fullscreenElement || !!document.webkitFullscreenElement;
                return this.isFullscreen;
            } catch (error) {
                console.warn('[Anti-Cheat] Fullscreen request blocked:', error?.message || error);
                return false;
            }
        },

        // ===== NETWORK EVENTS =====
        onOnline() {
            this.isOffline = false;
            this._syncRetries = 0;
            if (this._onlineSyncTimer) clearTimeout(this._onlineSyncTimer);
            this._onlineSyncTimer = setTimeout(() => {
                this.recalcPendingSync().then(() => this.syncToServer());
                this.flushCheatingQueue();
            }, 1200);
        },


        onOffline() {
            this.isOffline = true;
            // Register background sync so SW can sync when connection returns
            if (this.pendingSync > 0) {
                this._registerBackgroundSync();
            }
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
                        const contentType = resp.headers.get('content-type') || '';
                        if (!resp.ok || !contentType.startsWith('image/')) {
                            await db.image_status.put({ url, cached: false, error: true });
                            this.cacheDone++;
                            return;
                        }

                        await cache.put(url, resp.clone());
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
