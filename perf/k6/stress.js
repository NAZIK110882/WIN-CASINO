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
    'http_req_failed{load_stage:20}': ['rate<0.05'],
    'http_req_failed{load_stage:50}': ['rate<0.05'],
    'http_req_failed{load_stage:100}': ['rate<0.05'],
    'http_req_failed{load_stage:200}': ['rate<0.05'],
    'http_req_failed{load_stage:400}': ['rate<0.05'],
    'http_req_failed{load_stage:recovery}': ['rate<0.05'],
    'http_req_duration{load_stage:20}': ['p(95)<1000'],
    'http_req_duration{load_stage:50}': ['p(95)<1000'],
    'http_req_duration{load_stage:100}': ['p(95)<1000'],
    'http_req_duration{load_stage:200}': ['p(95)<1000'],
    'http_req_duration{load_stage:400}': ['p(95)<1000'],
    'http_req_duration{load_stage:recovery}': ['p(95)<1000'],
  },
};

export function setup() { return { token: login(), startedAt: Date.now() }; }

function loadStage(startedAt) {
  const elapsed = (Date.now() - startedAt) / 1000;
  if (elapsed < 30) return '20';
  if (elapsed < 60) return '50';
  if (elapsed < 90) return '100';
  if (elapsed < 120) return '200';
  if (elapsed < 150) return '400';
  return 'recovery';
}

export default function (data) {
  const stage = loadStage(data.startedAt);
  const headers = authHeaders(data.token).headers;
  const leaderboardParams = { headers, tags: { load_stage: stage, endpoint: 'leaderboard' } };
  const historyParams = { headers, tags: { load_stage: stage, endpoint: 'history' } };

  const r = http.get(`${BASE}/leaderboard`, leaderboardParams);
  check(r, { 'status 200': (x) => x.status === 200, 'not 5xx': (x) => x.status < 500 });
  const h = http.get(`${BASE}/history?limit=20`, historyParams);
  check(h, { 'history 200': (x) => x.status === 200 });
  sleep(0.2);
}
