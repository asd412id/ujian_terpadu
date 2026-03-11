import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// =============================================================================
// Skenario A: Raw Throughput — GET /up (healthcheck)
// Tujuan: Baseline max RPS server tanpa logic aplikasi
// =============================================================================

const errorRate = new Rate('errors');
const responseTime = new Trend('response_time', true);

export const options = {
    stages: [
        { duration: '15s', target: 100 },   // ramp-up
        { duration: '30s', target: 300 },   // moderate load
        { duration: '30s', target: 600 },   // high load
        { duration: '30s', target: 1000 },  // very high load
        { duration: '15s', target: 0 },     // ramp-down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        errors: ['rate<0.01'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    const res = http.get(`${BASE_URL}/up`);

    responseTime.add(res.timings.duration);

    const success = check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    if (!success) {
        errorRate.add(1);
    } else {
        errorRate.add(0);
    }
}

export function handleSummary(data) {
    const now = new Date().toISOString().replace(/[:.]/g, '-');
    return {
        [`benchmark/results/healthcheck-${now}.json`]: JSON.stringify(data, null, 2),
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const metrics = data.metrics;
    return `
=== BENCHMARK RESULT: Healthcheck (GET /up) ===
VUs Max:         ${data.root_group?.checks?.[0]?.passes + data.root_group?.checks?.[0]?.fails || 'N/A'}
Total Requests:  ${metrics.http_reqs?.values?.count || 'N/A'}
RPS:             ${(metrics.http_reqs?.values?.rate || 0).toFixed(2)}
Duration (avg):  ${(metrics.http_req_duration?.values?.avg || 0).toFixed(2)}ms
Duration (p50):  ${(metrics.http_req_duration?.values?.['p(50)'] || 0).toFixed(2)}ms
Duration (p95):  ${(metrics.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
Duration (p99):  ${(metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms
Error Rate:      ${((metrics.errors?.values?.rate || 0) * 100).toFixed(2)}%
================================================
`;
}
