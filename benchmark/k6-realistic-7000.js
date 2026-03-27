import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// =============================================================================
// Realistic 7000-Peserta Exam Simulation (Arrival-Rate)
//
// Pola request nyata:
// - 7000 peserta, masing-masing sync setiap ~15-30 detik
// - Setiap sync cycle: 1 POST sync-jawaban + 1 GET status
// - Target RPS: 7000/22.5s ≈ 311 iterations/sec (each iter = 2 requests)
// - Total target: ~622 HTTP req/sec
//
// Menggunakan constant-arrival-rate agar VU count tetap rendah (~200-300)
// tapi request rate tetap tinggi — tidak OOM di client.
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
        // Phase 1: Warm-up (1000 peserta pace)
        warmup: {
            executor: 'constant-arrival-rate',
            rate: 45,              // 1000 peserta / 22.5s ≈ 45 iter/sec
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 100,
            maxVUs: 300,
            exec: 'examIteration',
            startTime: '0s',
        },
        // Phase 2: Moderate (3000 peserta pace)
        moderate: {
            executor: 'constant-arrival-rate',
            rate: 135,             // 3000 / 22.5 ≈ 133 iter/sec
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 200,
            maxVUs: 500,
            exec: 'examIteration',
            startTime: '30s',
        },
        // Phase 3: High (5000 peserta pace)
        high: {
            executor: 'constant-arrival-rate',
            rate: 222,             // 5000 / 22.5 ≈ 222 iter/sec
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 300,
            maxVUs: 600,
            exec: 'examIteration',
            startTime: '60s',
        },
        // Phase 4: Full load (7000 peserta pace) — 2 minutes sustained
        full_load: {
            executor: 'constant-arrival-rate',
            rate: 311,             // 7000 / 22.5 ≈ 311 iter/sec
            timeUnit: '1s',
            duration: '120s',
            preAllocatedVUs: 400,
            maxVUs: 800,
            exec: 'examIteration',
            startTime: '90s',
        },
        // Phase 5: Ramp-down (3000 peserta submit, sisanya masih)
        cooldown: {
            executor: 'constant-arrival-rate',
            rate: 135,
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 200,
            maxVUs: 400,
            exec: 'examIteration',
            startTime: '210s',
        },
    },
    thresholds: {
        sync_errors: ['rate<0.10'],
        status_errors: ['rate<0.10'],
        sync_duration: ['p(95)<3000'],
        status_duration: ['p(95)<2000'],
        http_req_duration: ['p(95)<3000'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// Atomic counter for unique token assignment
let iterCounter = 0;

export function examIteration() {
    // Distribute tokens across iterations (not VUs) for better spread
    const tokenIndex = (iterCounter++) % tokens.length;
    const myToken = tokens[tokenIndex];

    // === 1. Sync Jawaban (POST) ===
    const answersCount = Math.floor(Math.random() * 3) + 1;
    const startSoalIndex = Math.floor(Math.random() * (soalIds.length - answersCount));
    const answers = [];

    for (let i = 0; i < answersCount; i++) {
        answers.push({
            soal_id: soalIds[startSoalIndex + i],
            jawaban: ['A', 'B', 'C', 'D', 'E'][Math.floor(Math.random() * 5)],
            idempotency_key: `${myToken.token.substring(0, 8)}-${__VU}-${__ITER}-${startSoalIndex + i}-${Date.now()}`,
            client_timestamp: Date.now(),
        });
    }

    const syncRes = http.post(`${BASE_URL}/api/ujian/sync-jawaban`, JSON.stringify({
        sesi_token: myToken.token,
        answers: answers,
        soal_ditandai: 0,
        tandai_list: [],
    }), {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        timeout: '15s',
    });

    syncDuration.add(syncRes.timings.duration);
    const syncOk = check(syncRes, { 'sync: 200': (r) => r.status === 200 });
    if (syncOk) {
        syncSuccess.add(1);
        syncErrors.add(0);
    } else {
        syncFailed.add(1);
        syncErrors.add(1);
    }

    // Brief pause (client debounce ~800ms)
    sleep(Math.random() * 0.5 + 0.3);

    // === 2. Status Polling (GET) ===
    const statusRes = http.get(`${BASE_URL}/api/ujian/status/${myToken.token}`, {
        headers: { 'Accept': 'application/json' },
        timeout: '10s',
    });

    statusDuration.add(statusRes.timings.duration);
    const statusOk = check(statusRes, { 'status: 200': (r) => r.status === 200 });
    statusErrors.add(statusOk ? 0 : 1);
}

export function handleSummary(data) {
    return { stdout: textSummary(data) };
}

function textSummary(data) {
    const m = data.metrics;
    return `
=== BENCHMARK: Realistic 7000 Peserta Exam (Arrival-Rate) ===
VM Target: e2-standard-4 (4 vCPU, 16GB RAM)
k6 Client: disdik server (4 CPU, 8GB RAM) → Cloudflare Tunnel

--- Traffic ---
Total HTTP Requests:  ${m.http_reqs?.values?.count || 'N/A'}
RPS (avg):            ${(m.http_reqs?.values?.rate || 0).toFixed(2)}
Max VUs Used:         ${m.vus_max?.values?.max || 'N/A'}

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

--- Verdict ---
HTTP Duration (avg):  ${(m.http_req_duration?.values?.avg || 0).toFixed(0)}ms
HTTP Duration (p95):  ${(m.http_req_duration?.values?.['p(95)'] || 0).toFixed(0)}ms
HTTP Failed:          ${m.http_req_failed?.values?.rate !== undefined ? (m.http_req_failed.values.rate * 100).toFixed(2) + '%' : 'N/A'}

Estimated Capacity:   ${((m.sync_errors?.values?.rate || 0) < 0.05) ? '7000 peserta ✅ LULUS' : ((m.sync_errors?.values?.rate || 0) < 0.10) ? '7000 peserta ⚠️ MARGINAL' : '7000 peserta ❌ TIDAK CUKUP'}
===================================================================
`;
}
