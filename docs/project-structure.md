# Project structure

```text
WIN-CASINO/
├── api/
│   ├── v1/                    # Versioned API: auth, games, history, admin
│   ├── v2/                    # New profile response contract
│   └── bootstrap.php          # Shared environment, CORS, error, timeout and rate-limit setup
├── src/
│   ├── Cache/                 # Redis/file/array caches and cache-aside helper
│   ├── Http/                  # JSON responses, CORS and exception mapping
│   ├── Resilience/            # Retry and circuit breaker
│   ├── Security/              # JWT auth, validation and rate limiting
│   ├── Service/               # Game, profile and history services
│   └── Support/                # Environment loading and PDO factory
├── public/                    # PHP server entry point and static resources
├── tests/
│   ├── Unit/                  # Isolated PHPUnit checks
│   └── Integration/           # SQLite-backed service and persistence checks
├── cypress/e2e/               # Browser UI scenarios; API responses are stubbed
├── postman/                   # API collection and request checks
├── docs/
│   ├── openapi.yaml           # OpenAPI 3.1 API contract
│   └── PRACTICUM_GUIDE.md     # Commands, demonstrations and report evidence
├── docker/
│   ├── mysql-init.sql         # MySQL schema and indexes
│   ├── apache/                # Backend virtual host and route rules
│   ├── frontend/              # Nginx image and runtime API config
│   └── caddy/                 # HTTPS reverse proxy for production
├── perf/                      # k6 load scenarios and SQL profiling/optimization scripts
├── scripts/                   # Seed data and API smoke checks
├── Dockerfile                 # Multi-stage PHP/Apache backend image
├── docker-compose.yml         # Local MySQL, Redis, backend and frontend stack
├── docker-compose.prod.yml    # Production topology using published images
└── .github/workflows/ci-cd.yml
```

The root HTML pages are the static frontend. They use `config.js` and `casino-api.js` to call the versioned backend API. Unversioned prototype handlers have been removed; use `/api/v1/*` for application requests.
