// Перевірка rate limiting: перші RATE_LIMIT_MAX запитів -> 200, далі -> 429 + Retry-After.
// Запуск (RATE_LIMIT_MAX=60 у .env): k6 run -e CASINO_PASSWORD=*** perf/k6/ratelimit.js
import http from 'k6/http';
import { check } from 'k6';
import { BASE, login, authHeaders } from './lib.js';

export const options = { vus: 1, iterations: 1 };

export function setup() { return { token: login() }; }

export default function (data) {
  const p = authHeaders(data.token);
  let ok = 0, limited = 0, retryAfter = null;
  for (let i = 0; i < 100; i++) {
    const r = http.get(`${BASE}/user`, p);
    if (r.status === 200) ok++;
    if (r.status === 429) { limited++; retryAfter = r.headers['Retry-After']; }
  }
  console.log(`200: ${ok}, 429: ${limited}, Retry-After: ${retryAfter}`);
  check(null, {
    'є 429 після перевищення': () => limited > 0,
    'є Retry-After': () => retryAfter !== null,
  });
}
