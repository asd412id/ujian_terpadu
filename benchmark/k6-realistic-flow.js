import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// =============================================================================
// Skenario C: Realistic Full Flow
// Flow per VU: Login → Lobby → Mengerjakan → Sync (10x loop) → Submit
// Tujuan: Simulate real user behavior end-to-end
// =============================================================================

const errorRate = new Rate('errors');
const loginDuration = new Trend('login_duration', true);
const lobbyDuration = new Trend('lobby_duration', true);
const mengerjakanDuration = new Trend('mengerjakan_duration', true);
const syncDuration = new Trend('sync_duration', true);
const submitDuration = new Trend('submit_duration', true);
const flowSuccess = new Counter('flow_complete');
const flowFailed = new Counter('flow_failed');

const tokenData = JSON.parse(open('./tokens.json'));
const tokens = new SharedArray('tokens', function () {
    return tokenData.tokens;
});
const soalIds = tokenData.soal_ids;

export const options = {
    scenarios: {
        realistic_flow: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '20s', target: 30 },     // warm-up
                { duration: '30s', target: 100 },    // moderate
                { duration: '1m', target: 300 },     // high
                { duration: '1m', target: 500 },     // very high
                { duration: '20s', target: 0 },      // ramp-down
            ],
            gracefulRampDown: '30s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<3000', 'p(99)<5000'],
        errors: ['rate<0.10'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    const tokenIndex = (__VU - 1) % tokens.length;
    const myToken = tokens[tokenIndex];
    let success = true;

    // === STEP 1: Login ===
    group('01_login', function () {
        const loginRes = http.post(`${BASE_URL}/ujian/login`, {
            username: myToken.username,
            password: 'bench123',
        }, {
            redirects: 0,
            timeout: '10s',
        });

        loginDuration.add(loginRes.timings.duration);

        const loginOk = check(loginRes, {
            'login redirects (302)': (r) => r.status === 302 || r.status === 200,
        });

        if (!loginOk) {
            success = false;
            errorRate.add(1);
            console.warn(`VU ${__VU} login failed: ${loginRes.status}`);
            return;
        }
        errorRate.add(0);
    });

    if (!success) {
        flowFailed.add(1);
        sleep(5);
        return;
    }

    sleep(1);

    // === STEP 2: Lobby ===
    group('02_lobby', function () {
        const lobbyRes = http.get(`${BASE_URL}/ujian/lobby`, {
            timeout: '10s',
        });

        lobbyDuration.add(lobbyRes.timings.duration);

        const lobbyOk = check(lobbyRes, {
            'lobby status 200': (r) => r.status === 200,
        });

        if (!lobbyOk) {
            success = false;
            errorRate.add(1);
        } else {
            errorRate.add(0);
        }
    });

    if (!success) {
        flowFailed.add(1);
        sleep(5);
        return;
    }

    sleep(2);

    // === STEP 3: Mengerjakan (page load) ===
    group('03_mengerjakan', function () {
        const mengRes = http.get(`${BASE_URL}/ujian/${myToken.sesi_peserta_id}/mengerjakan`, {
            timeout: '15s',
        });

        mengerjakanDuration.add(mengRes.timings.duration);

        const mengOk = check(mengRes, {
            'mengerjakan status 200': (r) => r.status === 200,
        });

        if (!mengOk) {
            success = false;
            errorRate.add(1);
        } else {
            errorRate.add(0);
        }
    });

    if (!success) {
        flowFailed.add(1);
        sleep(5);
        return;
    }

    sleep(3);

    // === STEP 4: Sync Jawaban (10x loop, simulate menjawab soal) ===
    group('04_sync_jawaban', function () {
        for (let round = 0; round < 10; round++) {
            const answersCount = Math.floor(Math.random() * 3) + 2; // 2-4 jawaban per sync
            const startIdx = round * 4;
            const answers = [];

            for (let i = 0; i < answersCount && (startIdx + i) < soalIds.length; i++) {
                answers.push({
                    soal_id: soalIds[startIdx + i],
                    jawaban: ['A', 'B', 'C', 'D', 'E'][Math.floor(Math.random() * 5)],
                    idempotency_key: `${myToken.token.substring(0, 8)}-flow-${__ITER}-${round}-${i}`,
                    client_timestamp: Date.now(),
                });
            }

            const syncRes = http.post(`${BASE_URL}/api/ujian/sync-jawaban`, JSON.stringify({
                sesi_token: myToken.token,
                answers: answers,
                soal_ditandai: 0,
                tandai_list: [],
            }), {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: '10s',
            });

            syncDuration.add(syncRes.timings.duration);

            const syncOk = check(syncRes, {
                'sync status 200': (r) => r.status === 200,
            });

            if (!syncOk) {
                errorRate.add(1);
                if (syncRes.status === 429) {
                    sleep(5); // throttled, wait longer
                }
            } else {
                errorRate.add(0);
            }

            // Think time antara sync (simulate menjawab soal)
            sleep(Math.random() * 3 + 2);
        }
    });

    sleep(2);

    // === STEP 5: Submit ===
    group('05_submit', function () {
        const submitRes = http.post(`${BASE_URL}/api/ujian/submit/${myToken.token}`, null, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            timeout: '15s',
        });

        submitDuration.add(submitRes.timings.duration);

        const submitOk = check(submitRes, {
            'submit status 200': (r) => r.status === 200,
        });

        if (!submitOk) {
            errorRate.add(1);
        } else {
            errorRate.add(0);
        }
    });

    flowSuccess.add(1);
}

export function handleSummary(data) {
    const now = new Date().toISOString().replace(/[:.]/g, '-');
    return {
        [`benchmark/results/realistic-flow-${now}.json`]: JSON.stringify(data, null, 2),
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const m = data.metrics;
    return `
=== BENCHMARK RESULT: Realistic Flow (Login → Lobby → Mengerjakan → Sync x10 → Submit) ===
Total Requests:     ${m.http_reqs?.values?.count || 'N/A'}
RPS:                ${(m.http_reqs?.values?.rate || 0).toFixed(2)}
Duration (avg):     ${(m.http_req_duration?.values?.avg || 0).toFixed(2)}ms
Duration (p95):     ${(m.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
Duration (p99):     ${(m.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms
Login (avg):        ${(m.login_duration?.values?.avg || 0).toFixed(2)}ms
Lobby (avg):        ${(m.lobby_duration?.values?.avg || 0).toFixed(2)}ms
Mengerjakan (avg):  ${(m.mengerjakan_duration?.values?.avg || 0).toFixed(2)}ms
Sync (avg):         ${(m.sync_duration?.values?.avg || 0).toFixed(2)}ms
Sync (p95):         ${(m.sync_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
Submit (avg):       ${(m.submit_duration?.values?.avg || 0).toFixed(2)}ms
Flow Complete:      ${m.flow_complete?.values?.count || 0}
Flow Failed:        ${m.flow_failed?.values?.count || 0}
Error Rate:         ${((m.errors?.values?.rate || 0) * 100).toFixed(2)}%
==========================================================================================
`;
}
