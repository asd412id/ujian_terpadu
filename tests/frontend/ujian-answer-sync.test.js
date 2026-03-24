import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dexie from 'dexie';
import { liveQuery } from 'dexie';
import { ujianApp } from '../../resources/js/ujian';

describe('ujian answer sync buffering', () => {
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

        await Dexie.delete('UjianTerpaduDB');
    });

    it('serializes rapid pg_kompleks changes so the latest selection persists locally', async () => {
        const app = ujianApp();
        app.syncedSoalIds = new Set();
        app._memoryFallbackAnswers = {};

        const saveOne = app.saveJawaban('soal-1', { pg: ['A'], terjawab: true });
        const saveTwo = app.saveJawaban('soal-1', { pg: ['A', 'C'], terjawab: true });

        await Promise.all([saveOne, saveTwo]);

        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        const stored = await db.table('exam_answers').where('soalId').equals('soal-1').first();
        expect(stored.jawaban.pg).toEqual(['A', 'C']);
        expect(stored.synced).toBe(false);
        expect(stored.idempotencyKey).toMatch(/^sesi-1-soal-1-\d+-\d+$/);

        await db.close();
    });

    it('generates unique idempotency keys even within the same millisecond', async () => {
        vi.spyOn(Date, 'now').mockReturnValue(1742920000000);

        const app = ujianApp();
        app.syncedSoalIds = new Set();
        app._memoryFallbackAnswers = {};

        await Promise.all([
            app.saveJawaban('soal-2', { pg: ['A'], terjawab: true }),
            app.saveJawaban('soal-2', { pg: ['A', 'B'], terjawab: true }),
        ]);

        const db = new Dexie('UjianTerpaduDB');
        db.version(1).stores({
            exam_answers: '++id, sesiPesertaId, soalId, jawaban, synced, idempotencyKey, updatedAt',
            exam_state: 'sesiPesertaId, currentIndex, tandaiList, lastSyncAt',
            image_status: 'url, cached, error',
        });

        const stored = await db.table('exam_answers').where('soalId').equals('soal-2').first();
        expect(stored.jawaban.pg).toEqual(['A', 'B']);
        expect(stored.idempotencyKey).toBe('sesi-1-soal-2-1742920000000-2');

        await db.close();
    });

    it('waits for queued writes before building sync payloads', async () => {
        const app = ujianApp();
        app.syncedSoalIds = new Set();
        app._memoryFallbackAnswers = {};

        const originalFirst = Dexie.prototype.table;
        const gate = {};
        gate.promise = new Promise((resolve) => {
            gate.resolve = resolve;
        });
        let intercepted = false;

        vi.spyOn(Dexie.prototype, 'table').mockImplementation(function tableProxy(name) {
            const table = originalFirst.call(this, name);
            if (name !== 'exam_answers') {
                return table;
            }

            const originalUpdate = table.update.bind(table);
            table.update = async (key, changes) => {
                if (!intercepted) {
                    intercepted = true;
                    await gate.promise;
                }

                return originalUpdate(key, changes);
            };

            return table;
        });

        await app.saveJawaban('soal-3', { pg: ['A'], terjawab: true });
        const pendingSave = app.saveJawaban('soal-3', { pg: ['A', 'D'], terjawab: true });

        const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({ accepted: true }),
        });

        const syncPromise = app.syncToServer();
        await Promise.resolve();
        expect(fetchSpy).not.toHaveBeenCalled();

        gate.resolve();
        await pendingSave;
        await syncPromise;

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        const requestPayload = JSON.parse(fetchSpy.mock.calls[0][1].body);
        expect(requestPayload.answers).toHaveLength(1);
        expect(requestPayload.answers[0].jawaban).toEqual(['A', 'D']);
    });
});
