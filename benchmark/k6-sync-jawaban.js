import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// =============================================================================
// Skenario B: Concurrent Sync Jawaban (TEST UTAMA)
// Tujuan: Simulate N peserta sync jawaban bersamaan
// Ini adalah endpoint paling kritikal — dipanggil setiap 15-30 detik per peserta
// =============================================================================

const errorRate = new Rate('errors');
const syncDuration = new Trend('sync_duration', true);
const syncSuccess = new Counter('sync_success');
const syncFailed = new Counter('sync_failed');
const dbErrors = new Counter('db_errors');

// Load tokens dari file yang di-generate oleh artisan benchmark:export-tokens
const tokenData = JSON.parse(open('./tokens.json'));
const tokens = new SharedArray('tokens', function () {
    return tokenData.tokens;
});
const soalIds = tokenData.soal_ids;

export const options = {
    scenarios: {
        sync_jawaban: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 100 },    // warm-up
                { duration: '1m', target: 500 },     // moderate
                { duration: '1m', target: 1000 },    // high
                { duration: '1m', target: 2000 },    // very high
                { duration: '1m', target: 3000 },    // target max
                { duration: '30s', target: 0 },      // ramp-down
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<2000', 'p(99)<5000'],
        errors: ['rate<0.05'],
        sync_duration: ['p(95)<2000'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    // Setiap VU mendapat token unik berdasarkan VU ID
    const tokenIndex = (__VU - 1) % tokens.length;
    const myToken = tokens[tokenIndex];

    // Simulate sync 5 jawaban sekaligus (realistic: peserta jawab 5 soal lalu sync)
    const answersCount = 5;
    const startSoalIndex = Math.floor(Math.random() * (soalIds.length - answersCount));
    const answers = [];

    for (let i = 0; i < answersCount; i++) {
        const soalIndex = startSoalIndex + i;
        answers.push({
            soal_id: soalIds[soalIndex],
            jawaban: ['A', 'B', 'C', 'D', 'E'][Math.floor(Math.random() * 5)],
            idempotency_key: `${myToken.token.substring(0, 8)}-${__ITER}-${soalIndex}`,
            client_timestamp: Date.now(),
        });
    }

    const payload = JSON.stringify({
        sesi_token: myToken.token,
        answers: answers,
        soal_ditandai: Math.floor(Math.random() * 3),
        tandai_list: [],
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        timeout: '10s',
    };

    const res = http.post(`${BASE_URL}/api/ujian/sync-jawaban`, payload, params);

    syncDuration.add(res.timings.duration);

    const success = check(res, {
        'status is 200': (r) => r.status === 200,
        'response has success': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.success === true || body.status === 'ok';
            } catch (e) {
                return false;
            }
        },
        'response time < 2s': (r) => r.timings.duration < 2000,
    });

    if (success) {
        syncSuccess.add(1);
        errorRate.add(0);
    } else {
        syncFailed.add(1);
        errorRate.add(1);

        if (res.status >= 500) {
            dbErrors.add(1);
        }

        // Log error detail untuk debugging
        if (res.status !== 200 && res.status !== 429) {
            console.warn(`VU ${__VU} | Status: ${res.status} | Body: ${res.body?.substring(0, 200)}`);
        }
    }

    // Think time: simulate peserta mengerjakan soal (15-30 detik realistic)
    // Untuk benchmark, kita pakai 1-3 detik agar load cukup tinggi
    sleep(Math.random() * 2 + 1);
}

export function handleSummary(data) {
    const now = new Date().toISOString().replace(/[:.]/g, '-');
    return {
        [`benchmark/results/sync-jawaban-${now}.json`]: JSON.stringify(data, null, 2),
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const m = data.metrics;
    return `
=== BENCHMARK RESULT: Sync Jawaban (POST /api/ujian/sync-jawaban) ===
Total Requests:   ${m.http_reqs?.values?.count || 'N/A'}
RPS:              ${(m.http_reqs?.values?.rate || 0).toFixed(2)}
Duration (avg):   ${(m.http_req_duration?.values?.avg || 0).toFixed(2)}ms
Duration (p50):   ${(m.http_req_duration?.values?.['p(50)'] || 0).toFixed(2)}ms
Duration (p95):   ${(m.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
Duration (p99):   ${(m.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms
Duration (max):   ${(m.http_req_duration?.values?.max || 0).toFixed(2)}ms
Sync Success:     ${m.sync_success?.values?.count || 0}
Sync Failed:      ${m.sync_failed?.values?.count || 0}
DB Errors (5xx):  ${m.db_errors?.values?.count || 0}
Error Rate:       ${((m.errors?.values?.rate || 0) * 100).toFixed(2)}%
=====================================================================
`;
}
