// STRESS: ступінчасте зростання навантаження до відмови.
// Фіксуйте: при якому VU p95 перевищує поріг, коли з'являються 5xx/timeout, як система відновлюється.
// Запуск: k6 run -e CASINO_PASSWORD=*** perf/k6/stress.js --summary-export=perf/results/stress.json
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, login, authHeaders } from './lib.js';

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '30s', target: 50 },
    { duration: '30s', target: 100 },
    { duration: '30s', target: 200 },
    { duration: '30s', target: 400 },
    { duration: '30s', target: 0 }, // recovery
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<1000'],
  },
};

export function setup() { return { token: login() }; }

export default function (data) {
  const p = authHeaders(data.token);
  const r = http.get(`${BASE}/leaderboard`, p);
  check(r, { 'status 200': (x) => x.status === 200, 'not 5xx': (x) => x.status < 500 });
  const h = http.get(`${BASE}/history?limit=20`, p);
  check(h, { 'history 200': (x) => x.status === 200 });
  sleep(0.2);
}
