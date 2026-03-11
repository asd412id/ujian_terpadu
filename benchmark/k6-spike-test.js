import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// =============================================================================
// Skenario D: Spike Test
// Tujuan: Test perilaku server saat terjadi sudden spike (misalnya, semua
//         peserta mulai ujian bersamaan atau sync bersamaan)
// =============================================================================

const errorRate = new Rate('errors');
const syncDuration = new Trend('sync_duration', true);
const spikeErrors = new Counter('spike_errors');

const tokenData = JSON.parse(open('./tokens.json'));
const tokens = new SharedArray('tokens', function () {
    return tokenData.tokens;
});
const soalIds = tokenData.soal_ids;

export const options = {
    scenarios: {
        spike_test: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 200 },    // warm-up to baseline
                { duration: '1m', target: 200 },     // steady baseline
                { duration: '10s', target: 3000 },   // SPIKE! sudden 15x increase
                { duration: '1m', target: 3000 },    // hold spike
                { duration: '10s', target: 200 },    // drop back
                { duration: '1m', target: 200 },     // recovery period
                { duration: '30s', target: 5000 },   // MEGA SPIKE!
                { duration: '30s', target: 5000 },   // hold mega spike
                { duration: '30s', target: 0 },      // ramp-down
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<5000'],
        errors: ['rate<0.20'],  // allow higher error rate during spike
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    const tokenIndex = (__VU - 1) % tokens.length;
    const myToken = tokens[tokenIndex];

    // Sync 3 jawaban (lighter payload for spike)
    const answersCount = 3;
    const startIdx = Math.floor(Math.random() * (soalIds.length - answersCount));
    const answers = [];

    for (let i = 0; i < answersCount; i++) {
        answers.push({
            soal_id: soalIds[startIdx + i],
            jawaban: ['A', 'B', 'C', 'D'][Math.floor(Math.random() * 4)],
            idempotency_key: `${myToken.token.substring(0, 8)}-spike-${__ITER}-${startIdx + i}`,
            client_timestamp: Date.now(),
        });
    }

    const payload = JSON.stringify({
        sesi_token: myToken.token,
        answers: answers,
        soal_ditandai: 0,
        tandai_list: [],
    });

    const res = http.post(`${BASE_URL}/api/ujian/sync-jawaban`, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        timeout: '15s',
    });

    syncDuration.add(res.timings.duration);

    const success = check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 5s': (r) => r.timings.duration < 5000,
    });

    if (success) {
        errorRate.add(0);
    } else {
        errorRate.add(1);
        spikeErrors.add(1);

        if (res.status !== 429) {
            console.warn(`SPIKE VU ${__VU} | Status: ${res.status} | Duration: ${res.timings.duration.toFixed(0)}ms`);
        }
    }

    // Short think time during spike test
    sleep(Math.random() * 1.5 + 0.5);
}

export function handleSummary(data) {
    const now = new Date().toISOString().replace(/[:.]/g, '-');
    return {
        [`benchmark/results/spike-test-${now}.json`]: JSON.stringify(data, null, 2),
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const m = data.metrics;
    return `
=== BENCHMARK RESULT: Spike Test ===
Total Requests:   ${m.http_reqs?.values?.count || 'N/A'}
RPS:              ${(m.http_reqs?.values?.rate || 0).toFixed(2)}
Duration (avg):   ${(m.http_req_duration?.values?.avg || 0).toFixed(2)}ms
Duration (p50):   ${(m.http_req_duration?.values?.['p(50)'] || 0).toFixed(2)}ms
Duration (p95):   ${(m.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
Duration (p99):   ${(m.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms
Duration (max):   ${(m.http_req_duration?.values?.max || 0).toFixed(2)}ms
Spike Errors:     ${m.spike_errors?.values?.count || 0}
Error Rate:       ${((m.errors?.values?.rate || 0) * 100).toFixed(2)}%
====================================
`;
}
