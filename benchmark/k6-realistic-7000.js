import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// =============================================================================
// Realistic 7000-Peserta Exam Simulation
//
// Pola request nyata per peserta selama ujian:
// - sync-jawaban: setiap jawab soal (debounce 800ms client) + auto-sync 30s
// - status polling: setiap 30 detik
// - Rata-rata 1 peserta = 1 sync + 1 status setiap ~15-30 detik
//
// Untuk 7000 peserta concurrent:
// - sync-jawaban: ~7000/30 = ~233 req/sec
// - status: ~7000/30 = ~233 req/sec
// - Total: ~466 req/sec sustained
//
// k6 VU model: setiap VU = 1 peserta, melakukan sync lalu status, lalu sleep
// =============================================================================

const syncErrors = new Rate('sync_errors');
const statusErrors = new Rate('status_errors');
const syncDuration = new Trend('sync_duration', true);
const statusDuration = new Trend('status_duration', true);
const syncSuccess = new Counter('sync_success');
const syncFailed = new Counter('sync_failed');

const tokenData = JSON.parse(open('./tokens.json'));
const tokens = new SharedArray('tokens', function () {
    return tokenData.tokens;
});
const soalIds = tokenData.soal_ids;

export const options = {
    scenarios: {
        realistic_exam: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 1000 },   // peserta mulai login (batch 1)
                { duration: '30s', target: 3000 },   // batch 2-3 masuk
                { duration: '30s', target: 5000 },   // batch 4-5
                { duration: '30s', target: 7000 },   // semua peserta aktif
                { duration: '120s', target: 7000 },  // steady state — ujian berlangsung 2 menit
                { duration: '30s', target: 3000 },   // sebagian submit
                { duration: '20s', target: 0 },      // ramp down
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        sync_errors: ['rate<0.10'],         // < 10% sync errors
        status_errors: ['rate<0.10'],       // < 10% status errors
        sync_duration: ['p(95)<3000'],      // p95 < 3s
        status_duration: ['p(95)<2000'],    // p95 < 2s
        http_req_duration: ['p(95)<3000'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    // Setiap VU = 1 peserta unik
    const tokenIndex = (__VU - 1) % tokens.length;
    const myToken = tokens[tokenIndex];

    // === 1. Sync Jawaban (POST) ===
    // Peserta jawab 1-3 soal lalu sync (realistic pattern)
    const answersCount = Math.floor(Math.random() * 3) + 1;
    const startSoalIndex = Math.floor(Math.random() * (soalIds.length - answersCount));
    const answers = [];

    for (let i = 0; i < answersCount; i++) {
        answers.push({
            soal_id: soalIds[startSoalIndex + i],
            jawaban: ['A', 'B', 'C', 'D', 'E'][Math.floor(Math.random() * 5)],
            idempotency_key: `${myToken.token.substring(0, 8)}-${__ITER}-${startSoalIndex + i}-${Date.now()}`,
            client_timestamp: Date.now(),
        });
    }

    const syncPayload = JSON.stringify({
        sesi_token: myToken.token,
        answers: answers,
        soal_ditandai: 0,
        tandai_list: [],
    });

    const syncRes = http.post(`${BASE_URL}/api/ujian/sync-jawaban`, syncPayload, {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        timeout: '15s',
    });

    syncDuration.add(syncRes.timings.duration);
    const syncOk = check(syncRes, {
        'sync: status 200': (r) => r.status === 200,
    });

    if (syncOk) {
        syncSuccess.add(1);
        syncErrors.add(0);
    } else {
        syncFailed.add(1);
        syncErrors.add(1);
    }

    // Brief pause between sync and status (realistic: not simultaneous)
    sleep(Math.random() * 2 + 1);

    // === 2. Status Polling (GET) ===
    const statusRes = http.get(`${BASE_URL}/api/ujian/status/${myToken.token}`, {
        headers: { 'Accept': 'application/json' },
        timeout: '10s',
    });

    statusDuration.add(statusRes.timings.duration);
    const statusOk = check(statusRes, {
        'status: status 200': (r) => r.status === 200,
    });
    statusErrors.add(statusOk ? 0 : 1);

    // === 3. Think time ===
    // Realistic: peserta baca soal + jawab = 15-30 detik per soal
    // Untuk benchmark: 10-20 detik (agar total duration manageable)
    sleep(Math.random() * 10 + 10);
}

export function handleSummary(data) {
    const now = new Date().toISOString().replace(/[:.]/g, '-');
    return {
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const m = data.metrics;
    return `
=== BENCHMARK: Realistic 7000 Peserta Exam Simulation ===
VM: e2-standard-4 (4 vCPU, 16GB RAM)
App Replicas: 4

--- Traffic ---
Total HTTP Requests:  ${m.http_reqs?.values?.count || 'N/A'}
RPS (avg):            ${(m.http_reqs?.values?.rate || 0).toFixed(2)}
Peak VUs:             ${m.vus_max?.values?.max || 'N/A'}

--- Sync Jawaban (POST /api/ujian/sync-jawaban) ---
Success:              ${m.sync_success?.values?.count || 0}
Failed:               ${m.sync_failed?.values?.count || 0}
Error Rate:           ${((m.sync_errors?.values?.rate || 0) * 100).toFixed(2)}%
Duration (avg):       ${(m.sync_duration?.values?.avg || 0).toFixed(0)}ms
Duration (p50):       ${(m.sync_duration?.values?.['p(50)'] || 0).toFixed(0)}ms
Duration (p95):       ${(m.sync_duration?.values?.['p(95)'] || 0).toFixed(0)}ms
Duration (p99):       ${(m.sync_duration?.values?.['p(99)'] || 0).toFixed(0)}ms
Duration (max):       ${(m.sync_duration?.values?.max || 0).toFixed(0)}ms

--- Status Polling (GET /api/ujian/status/{token}) ---
Error Rate:           ${((m.status_errors?.values?.rate || 0) * 100).toFixed(2)}%
Duration (avg):       ${(m.status_duration?.values?.avg || 0).toFixed(0)}ms
Duration (p95):       ${(m.status_duration?.values?.['p(95)'] || 0).toFixed(0)}ms
Duration (p99):       ${(m.status_duration?.values?.['p(99)'] || 0).toFixed(0)}ms

--- Overall ---
HTTP Duration (avg):  ${(m.http_req_duration?.values?.avg || 0).toFixed(0)}ms
HTTP Duration (p95):  ${(m.http_req_duration?.values?.['p(95)'] || 0).toFixed(0)}ms
HTTP Failed:          ${m.http_req_failed?.values?.rate !== undefined ? (m.http_req_failed.values.rate * 100).toFixed(2) + '%' : 'N/A'}
===================================================================
`;
}
