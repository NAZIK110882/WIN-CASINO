import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://localhost:8000/api/v1';

export function login() {
  const res = http.post(
    `${BASE}/login`,
    JSON.stringify({ username: __ENV.CASINO_USER || 'player1', password: __ENV.CASINO_PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } }
  );
  if (!check(res, { 'login 200': (r) => r.status === 200 })) fail(`login failed: ${res.status} ${res.body}`);
  return res.json('token');
}

export function authHeaders(token) {
  return { headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' } };
}
