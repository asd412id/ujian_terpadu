import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dexie from 'dexie';
import fs from 'node:fs';
import path from 'node:path';
import { ujianApp } from '../../resources/js/ujian';

describe('ujian offline and slow-network submit flow', () => {
    beforeEach(async () => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        window.UJIAN_CONFIG = {
            sesiPesertaId: 'sesi-1',
            sesiToken: 't'.repeat(64),
            autoSaveInterval: 30,
            soalList: [],
            jawabanExisting: [],
            syncUrl: '/api/ujian/sync-jawaban',
            statusUrl: '/api/ujian/status/token',
            submitUrl: '/api/ujian/submit/token',
            antiCurang: false,
        };

        Object.defineProperty(window.navigator, 'onLine', {
            configurable: true,
            value: true,
        });

        await Dexie.delete('UjianTerpaduDB');
    });

    it('stores a submit snapshot when offline before redirecting', async () => {
        Object.defineProperty(window.navigator, 'onLine', {
            configurable: true,
            value: false,
        });

        const navigations = [];

        const app = ujianApp();
        app._navigateOverride = (url) => navigations.push(url);
        app.syncedSoalIds = new Set();
        app._memoryFallbackAnswers = {};
        app.answers = {
            'soal-1': { pg: ['A', 'C'], terjawab: true },
        };

        await app.saveJawaban('soal-1', { pg: ['A', 'C'], terjawab: true });
        await app.doSubmit();

        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        const state = await db.table('exam_state').get('sesi-1');
        expect(state.pendingSubmit).toBe(true);
        expect(state.pendingSubmitPayload).toEqual([
            expect.objectContaining({
                soal_id: 'soal-1',
                jawaban: ['A', 'C'],
            }),
        ]);
        expect(navigations).toEqual(['/ujian/sesi-1/selesai']);

        await db.close();
    });

    it('falls back to queued offline submit when submit response is logically rejected', async () => {
        const navigations = [];
        const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({ accepted: false, error: 'Server masih memproses' }),
        });

        const app = ujianApp();
        app._navigateOverride = (url) => navigations.push(url);
        app.syncedSoalIds = new Set();
        app._memoryFallbackAnswers = {};
        app.answers = {
            'soal-2': { pg: ['B'], terjawab: true },
        };

        await app.saveJawaban('soal-2', { pg: ['B'], terjawab: true });
        await app.doSubmit();

        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        const state = await db.table('exam_state').get('sesi-1');
        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect(state.pendingSubmit).toBe(true);
        expect(state.pendingSubmitPayload[0].jawaban).toEqual(['B']);
        expect(navigations).toEqual(['/ujian/sesi-1/selesai']);

        await db.close();
    });

    it('restores pending submit state without wiping current-session answers', async () => {
        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        await db.table('exam_answers').add({
            sesiPesertaId: 'sesi-1',
            soalId: 'soal-1',
            jawaban: { pg: ['A'], terjawab: true },
            synced: false,
            idempotencyKey: 'k-1',
            updatedAt: Date.now(),
        });
        await db.table('exam_state').put({
            sesiPesertaId: 'sesi-1',
            currentIndex: 0,
            tandaiList: [],
            lastSyncAt: Date.now(),
            pendingSubmit: true,
            pendingSubmitPayload: [{ soal_id: 'soal-1', jawaban: ['A'] }],
            pendingSubmitCount: 1,
        });

        window.UJIAN_CONFIG = {
            ...window.UJIAN_CONFIG,
            jawabanExisting: [],
        };

        const app = ujianApp();
        await app.restoreState('sesi-1');

        expect(app.answers['soal-1']?.pg).toEqual(['A']);
        expect(app.pendingSync).toBe(1);
        expect(await db.table('exam_answers').count()).toBe(1);

        await db.close();
    });

    it('merges memory fallback answers into final submit payload', async () => {
        const app = ujianApp();
        app._navigateOverride = vi.fn();
        app._memoryFallbackAnswers = {
            'soal-mem': { jawaban: { pg: ['B'], terjawab: true }, idempotencyKey: 'mem-1', updatedAt: 1 },
        };
        app.answers = {
            'soal-idb': { pg: ['A'], terjawab: true },
            'soal-mem': { pg: ['B'], terjawab: true },
        };

        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        Object.defineProperty(window.navigator, 'onLine', {
            configurable: true,
            value: false,
        });

        await db.table('exam_answers').add({
            sesiPesertaId: 'sesi-1',
            soalId: 'soal-idb',
            jawaban: { pg: ['A'], terjawab: true },
            synced: false,
            idempotencyKey: 'k-idb',
            updatedAt: Date.now(),
        });
        await db.table('exam_state').put({
            sesiPesertaId: 'sesi-1',
            currentIndex: 0,
            tandaiList: [],
            lastSyncAt: Date.now(),
            pendingSubmit: true,
            pendingSubmitPayload: [
                { soal_id: 'soal-idb', jawaban: ['A'] },
                { soal_id: 'soal-mem', jawaban: ['B'] },
            ],
            pendingSubmitCount: 2,
        });

        await app.doSubmit();

        const state = await db.table('exam_state').get('sesi-1');
        expect(state.pendingSubmitPayload).toEqual([
            expect.objectContaining({ soal_id: 'soal-idb' }),
            expect.objectContaining({ soal_id: 'soal-mem' }),
        ]);

        await db.close();
    });

    it('keeps selesai submission using locked status transition and safe image caching', () => {
        const selesaiPath = path.resolve('resources/views/ujian/selesai.blade.php');
        const selesaiSource = fs.readFileSync(selesaiPath, 'utf8');
        const swPath = path.resolve('public/sw.js');
        const swSource = fs.readFileSync(swPath, 'utf8');
        const jawabanServicePath = path.resolve('app/Services/JawabanService.php');
        const jawabanServiceSource = fs.readFileSync(jawabanServicePath, 'utf8');
        const ujianSource = fs.readFileSync(path.resolve('resources/js/ujian.js'), 'utf8');

        expect(selesaiSource).toContain('const answersBySoalId = new Map();');
        expect(selesaiSource).toContain('const answersToSync = Array.from(answersBySoalId.values());');
        expect(selesaiSource).not.toContain('await Promise.all(rows.map(async (row) => {');
        expect(swSource).toContain("if (!r.ok || !contentType.startsWith('image/')) return null;");
        expect(swSource).toContain("if (response.ok && (cacheName !== IMAGE_CACHE || contentType.startsWith('image/'))) {");
        expect(jawabanServiceSource).toContain("SesiPeserta::whereKey($sesiPeserta->id)->lockForUpdate()->firstOrFail();");
        expect(jawabanServiceSource).toContain("'message'         => $wasNewSubmit ? 'Ujian berhasil disubmit' : 'Sudah disubmit'");
        expect(ujianSource).toContain("if (!resp.ok || !contentType.startsWith('image/')) {");
        expect(ujianSource).toContain('const answersBySoalId = new Map(allAnswers.map(item => [String(item.soal_id), item]));');
    });
});
