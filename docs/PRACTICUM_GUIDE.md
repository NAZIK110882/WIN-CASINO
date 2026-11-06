# Практикум: Модуль 1 і Модуль 2 — WIN-CASINO

Цей набір накладається на корінь репозиторію (структура папок збережена). Нижче: що вже зроблено в коді,
які команди запускати і що **фіксувати у звіті** (скриншот / таблиця / лог).

## 0. Підготовка (один раз)

```bash
# 1. Скопіюйте вміст набору в корінь репозиторію (api/, src/, docker/, perf/, scripts/, tests/, .github/ ...)
# 2. Додайте рядки з .gitignore.additions у .gitignore
# 3. Приберіть з git те, чого там не має бути:
git rm -r --cached vendor coverage .phpunit.result.cache
# 4. Оновіть lock-файл після зміни composer.json
composer update --lock
# 5. Перевірка синтаксису PHP (я не міг запустити PHP у своєму середовищі — зробіть це у себе!)
find api src scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
# 6. Конфіг
cp .env.example .env     # змініть паролі та JWT_SECRET (openssl rand -hex 32)
```

---

# МОДУЛЬ 1

## 1. Статичний аналіз (SonarCloud)
1. https://sonarcloud.io → Log in with GitHub → **Analyze new project** → WIN-CASINO.
2. У `sonar-project.properties` вкажіть свої `organization` і `projectKey`.
3. GitHub → Settings → Secrets → додайте `SONAR_TOKEN`. CI (`.github/workflows/ci-cd.yml`) запустить скан автоматично.
4. **Звіт:** скриншот Overview (Bugs / Vulnerabilities / Code Smells / Security Hotspots / Coverage / Duplications / Maintainability Rating) — це стан **ДО**.

## 2. Рефакторинг
Типові знахідки в такому проєкті й що з ними робити (звіряйте зі своїм списком у Sonar):

| Проблема (ймовірна) | Виправлення |
|---|---|
| Креди БД захардкоджені в кожному `api/*.php` | `Database::pdo()` (вже є в `src/Support/Database.php`) |
| `rand()` / `mt_rand()` для результатів ігор (Sonar: *Security Hotspot — weak PRNG*) | `random_int()` |
| `user id = 1` / id з тіла запиту | `$GLOBALS['AUTH_USER_ID']` (ставить обгортка `api/v1/*.php`) |
| Баланс оновлюється без транзакції → гонка (double-spend) | `BEGIN; SELECT balance FROM users WHERE id=? FOR UPDATE; UPDATE ...; COMMIT;` |
| Дублювання коду в `spin/bonanza/rocket_start` | винести в `src/Game/*` (класи з одним методом `play()`), ендпоінти лишити тонкими |
| «Магічні» числа (множники, шанси) | `const` / конфіг |
| `echo json_encode` розкидано по файлах | `Json::send()` / `Json::error()` |
| `vendor/`, `coverage/`, `.phpunit.result.cache` у git | `.gitignore` |

Після рефакторингу: `git commit`, push, дочекайтесь нового скану. **Звіт:** скриншот Sonar **ПІСЛЯ** + таблиця «було → стало».

## 3. Benchmark і Stress-тест
```bash
# Встановіть k6: https://k6.io/docs/get-started/installation/
# У .env на час тесту: RATE_LIMIT_MAX=1000000  (інакше лімітер вас заблокує — це очікувано!)
docker compose up -d --build
docker compose exec backend php scripts/seed.php
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < perf/sql/seed_rounds.sql   # 500k рядків

k6 run -e CASINO_PASSWORD=<SEED_PLAYER_PASSWORD> perf/k6/benchmark.js --summary-export=perf/results/benchmark.json
k6 run -e CASINO_PASSWORD=<SEED_PLAYER_PASSWORD> perf/k6/stress.js    --summary-export=perf/results/stress.json
```
**Звіт (таблиця):** для benchmark — RPS, avg, p95, p99, % помилок. Для stress — на якому кроці (20/50/100/200/400 VU)
p95 перетнув 1 с, коли з'явились 5xx/timeout, чи відновилась система на спаді. Ознаки деградації: зростання p95/p99,
`http_req_failed`, обмеження `docker stats` (CPU backend/db), вичерпання Apache workers (`MaxRequestWorkers`).
Корисно прогнати **двічі**: до оптимізації БД/кешу і після — різниця і є головний результат.

## 4. Профілювання backend
```bash
docker compose -f docker-compose.yml -f docker-compose.profile.yml up -d --build backend
TOKEN=$(curl -s -X POST localhost:8000/api/v1/login -H 'Content-Type: application/json' \
   -d '{"username":"player1","password":"<пароль>"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
curl -H "Authorization: Bearer $TOKEN" "localhost:8000/api/v1/leaderboard?XDEBUG_TRIGGER=1"
ls profiles/      # cachegrind.out.* → відкрийте у QCachegrind/KCachegrind або webgrind
```
**Звіт:** скриншот call graph / таблиці «Self time» з топ-3 найдорожчими функціями (очікувано: `PDOStatement::execute`
для leaderboard/history, `password_verify` для login — це нормально й навмисно повільно). Висновок: що саме й чому дороге.
Після завершення поверніться на звичайний `docker compose up -d --build backend`.

## 5. Продуктивність БД
Slow query log увімкнено в compose (`long_query_time=0.1`). Виконайте `perf/sql/optimize.sql` по блоках:
**EXPLAIN ANALYZE до індексів → створення індексів → EXPLAIN ANALYZE після.**
**Звіт:** таблиця `type / key / rows / actual time` до і після. Очікувано: `type=ALL, rows≈500000` → `type=ref/range`,
`rows` на порядки менше, зникає `Using filesort`. Додатково — топ запитів з `performance_schema` (блок 5 у файлі).

## 6. Reliability & Fault Tolerance
| Механізм | Де реалізовано | Як перевірити |
|---|---|---|
| **Timeout** | `DB_CONNECT_TIMEOUT`, `DB_READ_TIMEOUT`, `DB_LOCK_WAIT_TIMEOUT`, `REQUEST_TIMEOUT`, Apache `Timeout`, `AbortController` на фронті | зупиніть БД і виміряйте час відповіді — не «вічність» |
| **Retry** (exp. backoff + jitter, лише transient) | `src/Resilience/Retry.php` → `Database::pdo()` | unit-тест `RetryTest` |
| **Circuit Breaker** | `src/Resilience/CircuitBreaker.php` (стан у Redis/файлі) | див. нижче |

Демонстрація Circuit Breaker:
```bash
docker compose stop db
for i in $(seq 1 8); do curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" -H "Authorization: Bearer $TOKEN" localhost:8000/api/v1/user; done
# перші ~5 запитів: 503 database_unavailable (з retry, повільні) → далі 503 dependency_unavailable МИТТЄВО + Retry-After
docker compose start db   # через CB_OPEN_SECONDS (15 c) breaker half-open → closed, запити знову 200
```
Також: зупиніть `redis` — застосунок продовжить працювати через `FileCache` (graceful degradation).
**Звіт:** лог/таблиця часу відповіді «до open» і «після open», вивід `phpunit` (CircuitBreakerTest, RetryTest).

## 7. Безпека REST API
Реалізовано: **автентифікація** (JWT HS256, `api/v1/login.php`, bcrypt через `password_hash/verify`, однакова відповідь
для невідомого логіна та неправильного пароля), **авторизація** (`Auth::requireRole('admin')`, перевірка в `admin_stats.php`),
**захист від IDOR** (user id — лише з токена), **валідація** (`Validator`, ліміт тіла 10 КБ, перевірка Content-Type,
prepared statements з `EMULATE_PREPARES=false`), **security headers**, **CORS allowlist**, закритий прямий доступ до
`src/`, `vendor/`, старих `api/*.php`; брутфорс-ліміт на логін.
Перевірка: `scripts/smoke.sh` (401 без токена, 403 для player на admin, 422 на `bet=-5`, CORS для чужого origin) + `AuthTest`, `ValidatorTest`.
Для ручного тесту: `{"bet":"1; DROP TABLE users"}` → 422; підроблений токен → 401.

## 8. Версіювання API
`/api/v1/*` — незмінний контракт (`{"success":true,"balance":...}`), `/api/v2/user` — новий формат
(`{"data":{...},"meta":{...}}`). Обидві працюють одночасно. Політика застарівання: у `.env` задайте `API_V1_SUNSET=...` —
v1 почне повертати заголовки `Deprecation`, `Sunset`, `Link: rel="successor-version"`.
**Звіт:** `curl -i` обох версій поруч. Фронтенд вибирає версію через `API_VERSION` (config.js).

## 9. Rate limiting
`RATE_LIMIT_MAX` запитів за `RATE_LIMIT_WINDOW` с на IP (fixed window, лічильник у Redis, атомарний `INCR`).
Відповідь: `429` + `Retry-After` + `X-RateLimit-*`. Перевірка: `k6 run -e CASINO_PASSWORD=... perf/k6/ratelimit.js`
(при `RATE_LIMIT_MAX=60` має вивести `200: 60, 429: 40`). Окремо: 6 невдалих логінів за хвилину → 429.

## 10. Кешування
Redis (`CACHE_DRIVER=redis`, fallback на файли). Cache-aside через `Remember::get`: профіль користувача (TTL 30 с,
**інвалідується після кожної ставки** — баланс не застаріває), лідерборд (TTL 30 с).
**Демонстрація зменшення звернень до БД:** виконайте 100 запитів і подивіться лічильники:
```bash
for i in $(seq 1 100); do curl -s -o /dev/null -H "Authorization: Bearer $TOKEN" localhost:8000/api/v1/leaderboard; done
curl -s localhost:8000/api/v1/health
# "cache_hit": 99, "cache_miss": 1, "db_leaderboard_queries": 1   ← 100 запитів → 1 звернення до БД
```
+ порівняння p95 у k6 «CACHE_TTL_LEADERBOARD=0 / >0». Тест: `FileCacheTest::testRememberCallsLoaderOnlyOnce`.

## 11. Конфігурація через Environment Variables
Усі параметри в `.env` (шаблон `.env.example`), у `src/Support/Env.php` реальні змінні середовища мають пріоритет
над файлом. `.env` у `.gitignore`. Секрети (`JWT_SECRET`, паролі БД) відсутні в коді. Якщо реальні паролі колись були в
історії git — змініть їх (видалення з коду не прибирає їх з історії).

---

# МОДУЛЬ 2

## 1–2. Dockerfile і docker-compose
```bash
docker build -t win-casino-backend .                      # багатостадійна збірка (composer → php:8.3-apache)
docker compose up -d --build                              # db + redis + backend(:8000) + frontend(:8080)
docker compose ps                                         # усі сервіси healthy
docker compose exec backend php scripts/seed.php
curl localhost:8000/api/v1/health                         # {"status":"ok","database":"up","cache_driver":"redis",...}
```
Взаємодія: `backend → db:3306`, `backend → redis:6379` по внутрішній мережі compose; `depends_on` + healthcheck
гарантують порядок старту. **Звіт:** `docker compose ps`, вивід health, `docker images`.

## 3. Публікація в Docker Hub
```bash
docker login
docker build -t <user>/win-casino-backend:1.0.0 -t <user>/win-casino-backend:latest .
docker build -f docker/frontend/Dockerfile -t <user>/win-casino-frontend:1.0.0 -t <user>/win-casino-frontend:latest .
docker push <user>/win-casino-backend:1.0.0 && docker push <user>/win-casino-backend:latest
docker push <user>/win-casino-frontend:1.0.0 && docker push <user>/win-casino-frontend:latest
```
(CI робить це автоматично на push у `main` — потрібні секрети `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN` [Access Token].)

## 4–8. Production-розгортання (VPS + Docker)
Потрібно: VPS (Ubuntu), домен з двома A-записами (`APP_DOMAIN` і `API_DOMAIN`) на IP сервера. Без власного домену
можна взяти безкоштовний на duckdns.org / nip.io.
```bash
# на сервері
curl -fsSL https://get.docker.com | sh
git clone https://github.com/NAZIK110882/WIN-CASINO /opt/win-casino && cd /opt/win-casino
cp .env.example .env && nano .env     # APP_ENV=production, свої паролі, JWT_SECRET, DOCKERHUB_USER,
                                      # APP_DOMAIN, API_DOMAIN, CACHE_DRIVER=redis, RATE_LIMIT_MAX=60
docker compose -f docker-compose.prod.yml --env-file .env up -d
docker compose -f docker-compose.prod.yml exec backend php scripts/seed.php
```
* **Frontend (п.5)**: окремий nginx-контейнер; `config.js` генерується зі змінної `API_BASE_URL=https://<API_DOMAIN>/api`.
* **Backend (п.6)**: `APP_ENV=production` (помилки не показуються клієнту), `opcache`, `expose_php=Off`, порти БД/Redis не опубліковані, `TRUSTED_PROXY=true` (реальний IP клієнта з Caddy для rate limit), HTTPS від Caddy (Let's Encrypt).
* **Взаємодія (п.7)**: відкрийте `https://<APP_DOMAIN>/login.html` → логін → у DevTools → Network видно запити на `https://<API_DOMAIN>/api/v1/...`; `scripts/smoke.sh https://<API_DOMAIN>/api/v1`.
* **CORS (п.8)**: `CORS_ALLOWED_ORIGINS=https://<APP_DOMAIN>` — лише цей origin отримує `Access-Control-Allow-Origin`; preflight `OPTIONS` → 204; чужий origin → без заголовка (smoke-тест це перевіряє). `*` не використовується.

**Підключення старого фронтенду:** у `index.html`, `slots.html`, `bonanza.html`, `rocket.html` додайте перед своїми скриптами
`<script src="config.js"></script><script src="casino-api.js"></script>` і замініть `fetch('api/spin.php', ...)` на
`CasinoAPI.spin(bet)`, `fetch('api/user.php')` → `CasinoAPI.user()`, тощо. Без токена API поверне 401 і клієнт відправить на `login.html`.

## 9. CI/CD
`.github/workflows/ci-cd.yml`: `test` (lint → init БД → PHPUnit+coverage → SonarCloud) → `e2e` (compose up → seed → smoke → Cypress)
→ `publish` (Docker Hub, лише `main`) → `deploy` (SSH на сервер, `docker compose pull && up -d`, post-deploy smoke).

Секрети (Settings → Secrets and variables → Actions): `SONAR_TOKEN`, `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`,
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `SMOKE_PLAYER_PASSWORD`; змінні (Variables): `APP_DOMAIN`, `API_DOMAIN`.
Job `deploy` використовує environment `production` — можна ввімкнути ручне схвалення.
**Звіт:** скриншот зеленого pipeline (Actions), посилання на запуск.

## 10. Continuous Testing
Тести запускаються на кожен push і PR: unit (PHPUnit), інтеграційні, API smoke (`smoke.sh`), E2E (Cypress), статичний аналіз
(Sonar Quality Gate), та post-deploy smoke на production. Покриття — `coverage/clover.xml` у Sonar і як artifact.
Для Cypress: `cypress.json` → `"baseUrl": "http://localhost:8080"` і логін через `cy.request('POST', '/api/v1/login', ...)` + запис токена в `sessionStorage`.

---

## Шаблон структури звіту
1. Мета і стек (PHP 8.3, MySQL 8.4, Redis, Docker, GitHub Actions)  2. Модуль 1: пункти 1–11 (було/стало, скриншоти, таблиці метрик)
3. Модуль 2: пункти 1–10 (команди, скриншоти, посилання на Docker Hub / живий сайт / pipeline)  4. Висновки та обмеження.

## Чесні обмеження цього набору
* Старі `api/spin.php`, `bonanza.php`, `rocket_start.php` мені недоступні — ігрова логіка підключається через обгортки, а їх
  внутрішній рефакторинг (транзакції, `random_int`, винесення класів) потрібно зробити за чеклістом з п. 2.
* Fixed-window rate limiter допускає сплеск до 2× ліміту на межі вікон — для навчального проєкту прийнятно, у звіті варто згадати.
* JWT без refresh/відкликання; роль у токені може бути застарілою до його завершення (TTL 1 год).
* Код не запускався мною (немає PHP у моєму середовищі) — обов'язково прогоніть `php -l` та `phpunit`.
