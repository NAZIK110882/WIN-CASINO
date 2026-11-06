/* Клієнт REST API: базовий URL з config.js (генерується з env у контейнері),
   Bearer-токен, таймаут запиту, єдина обробка помилок. */
(function () {
  const cfg = window.APP_CONFIG || { API_BASE: '/api', API_VERSION: 'v1' };
  const base = `${cfg.API_BASE}/${cfg.API_VERSION}`;
  const TOKEN_KEY = 'casino_token';
  const TIMEOUT_MS = 8000;

  async function request(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    const token = sessionStorage.getItem(TOKEN_KEY);
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
    try {
      const res = await fetch(`${base}/${path}`, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
        signal: ctrl.signal,
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401 && path !== 'login') {
        sessionStorage.removeItem(TOKEN_KEY);
        window.location.href = 'login.html';
      }
      if (!res.ok) {
        const err = new Error((data.error && data.error.message) || `HTTP ${res.status}`);
        err.status = res.status;
        err.retryAfter = res.headers.get('Retry-After');
        throw err;
      }
      return data;
    } finally {
      clearTimeout(timer);
    }
  }

  window.CasinoAPI = {
    async login(username, password) {
      const data = await request('login', { method: 'POST', body: { username, password } });
      sessionStorage.setItem(TOKEN_KEY, data.token);
      return data;
    },
    logout() { sessionStorage.removeItem(TOKEN_KEY); },
    user: () => request('user'),
    spin: (bet) => request('spin', { method: 'POST', body: { bet } }),
    bonanza: (bet) => request('bonanza', { method: 'POST', body: { bet } }),
    rocketStart: (bet) => request('rocket_start', { method: 'POST', body: { bet } }),
    history: (limit = 20, before) =>
      request(`history?limit=${limit}${before ? `&before=${before}` : ''}`),
    leaderboard: () => request('leaderboard'),
  };
})();
