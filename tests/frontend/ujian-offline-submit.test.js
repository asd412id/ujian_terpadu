import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dexie from 'dexie';
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
});
