// BENCHMARK: стабільне навантаження, базові метрики (baseline).
// Перед запуском підніміть ліміт: RATE_LIMIT_MAX=1000000 у .env і docker compose up -d backend
// Запуск: k6 run -e CASINO_PASSWORD=*** perf/k6/benchmark.js --summary-export=perf/results/benchmark.json
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, login, authHeaders } from './lib.js';

export const options = {
  vus: 10,
  duration: '1m',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<300'],
  },
};

export function setup() { return { token: login() }; }

export default function (data) {
  const p = authHeaders(data.token);
  const r1 = http.get(`${BASE}/user`, p);
  const r2 = http.get(`${BASE}/history?limit=20`, p);
  const r3 = http.get(`${BASE}/leaderboard`, p);
  check(r1, { 'user 200': (r) => r.status === 200 });
  check(r2, { 'history 200': (r) => r.status === 200 });
  check(r3, { 'leaderboard 200': (r) => r.status === 200 });
  sleep(0.5);
}
