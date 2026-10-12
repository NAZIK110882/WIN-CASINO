# E2E Tests (Cypress)

This folder contains end-to-end tests using Cypress that exercise main user flows in the WIN-CASINO frontend.

Prerequisites
- Node.js (>=16)
- The application accessible at the base URL specified in `cypress.json` (default: http://127.0.0.1/WIN-CASINO)

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
- The tests stub API responses using `cy.intercept` so they are resilient if your local backend is not available. For full end-to-end coverage, remove or adjust the intercepts so the tests call the real backend.
- If your app is hosted at a different URL, update `cypress.json` or set `CYPRESS_baseUrl` environment variable when running.
