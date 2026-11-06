# E2E Tests (Cypress)

This folder contains end-to-end tests using Cypress that exercise main user flows in the WIN-CASINO frontend.

Prerequisites
- Node.js (>=16)
- The frontend container accessible at the base URL specified in `cypress.config.js` (default: http://localhost:8080)

Install
```
npm install
```

Run tests (headed)
```
npm run cypress:open
```

Run tests (headless)
```
npm run cypress:run
```

Notes
- The scenarios visit `/slots.html`, which is included in the frontend image. They stub legacy API responses with `cy.intercept` to keep UI checks deterministic. API integration with the real backend is checked separately by `scripts/smoke.sh` in CI.
- If your app is hosted at a different URL, update `cypress.config.js` or set `CYPRESS_baseUrl` environment variable when running.
