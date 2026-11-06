# Практикум з програмної інженерії — WIN-CASINO

Цей документ описує локальні реалізації, команди для їх демонстрації та докази, які потрібно зібрати для звіту.
Наявність коду або workflow не є підтвердженням успішного запуску, вимірювання чи production deployment.

## Поточний стан

| Частина | Що є у локальному коді | Що ще треба зібрати або зробити вручну |
|---|---|---|
| Проєкт і Git | README, розділені директорії, історія гілок/PR | GitHub Project, виконавці задач і Code Review підтвердити у GitHub |
| API та БД | MySQL schema, версії API, OpenAPI, PDO services, constraints та індекси | Зробити скриншоти схеми й показати OpenAPI viewer |
| Тестування | PHPUnit unit/integration, 3 Cypress UI flows, Postman checks, smoke script | Запустити їх і долучити результати |
| Якість і продуктивність | Sonar config, k6, SQL optimization та profiling інструкції | Sonar скриншоти, реальні benchmark/stress/profile метрики |
| Надійність і захист | JWT, validation, CORS, rate limit, retry, circuit breaker, timeout, cache | Зібрати демонстраційні логи з локального запуску |
| Docker і CI | Dockerfiles, Compose та GitHub Actions jobs | Локально зібрати контейнери, перевірити Actions у власному репозиторії |
| Публікація і deployment | Опціональні jobs у workflow та production Compose | Потрібні власні Docker Hub secrets, сервер, домен і GitHub variables |

## 0. Підготовка (один раз)

```bash
# В корені репозиторію
composer install
cp .env.example .env
# Замініть JWT_SECRET і паролі перед використанням поза локальним навчальним середовищем.
```

Для рекомендованого локального середовища:

```bash
docker compose up -d --build --wait
docker compose exec backend php scripts/seed.php
```

Для PHP поза Docker переконайтеся, що доступні PHP 8.2+, mbstring, PDO MySQL/SQLite і Composer 2. Потім виконайте:

```bash
find api src scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
composer test
composer test:integration
```

---

# МОДУЛЬ 1. Командна розробка та розвиток backend-проєкту

## README і структура
README описує призначення проєкту, стек, залежності, запуск і структуру. Перед здачею додайте реальні імена учасників та їхні ролі до розділу «Команда» у README.

## GitHub Issues і Kanban
У репозиторії створіть GitHub Project з колонками `Todo → In Progress → Review → Done`, додайте задачі та призначте кожному учаснику щонайменше дві. Переміщуйте задачі відповідно до роботи. **Доказ:** посилання/скриншот дошки з виконавцями. Цю частину потрібно виконати у GitHub; локальний код не може підтвердити її.

## Перевірка бази даних
Схема в `docker/mysql-init.sql` містить `users`, `game_rounds` і `rocket_rounds`, первинні та зовнішні ключі, унікальність ігрового імені, обмеження балансу/ставки та індекси історії. У звіті додайте ER-діаграму або скриншот схеми та коротко опишіть зв’язки `users 1:N game_rounds` і `users 1:N rocket_rounds`.

## Learn Git Branching і гілки задач
Виконайте щонайменше перші чотири модулі Learn Git Branching. Створюйте гілки задач від командної базової гілки, фіксуйте зміни окремими комітами та відкривайте PR для review. **Доказ:** скриншоти прогресу тренажера й історії PR/комітів.

---

# МОДУЛЬ 2. Автоматизоване тестування

Проєкт має PHPUnit unit та integration suite і три Cypress E2E сценарії: показ балансу, успішне обертання слотів, недостатній баланс. Cypress зараз підміняє відповіді API, тому це перевіряє UI-поведінку; реальну взаємодію з backend перевіряє `scripts/smoke.sh`.

Останній локальний запуск у цьому робочому дереві: `composer test` — **57 tests, 123 assertions**, `composer test:integration` — **13 tests, 37 assertions**. Обидва suite проходять, але PHPUnit показує deprecations. Cypress потрібно запускати з доступним frontend і зберегти його підсумок у доказах.

PDF вимагає 15+ unit-тестів, 10+ тестів помилкових сценаріїв і 3+ E2E-тестів. Для звіту згрупуйте назви відповідних тестів за цими критеріями та додайте вивід успішних запусків.

---

# МОДУЛЬ 3. Якість, продуктивність, надійність і безпека ПЗ

## 1. Статичний аналіз (SonarCloud)
1. https://sonarcloud.io → Log in with GitHub → **Analyze new project** → WIN-CASINO.
2. У `sonar-project.properties` вкажіть свої `organization` і `projectKey`.
3. У власному репозиторії додайте secret `SONAR_TOKEN`. CI запустить скан за наявності токена; quality gate потрібно окремо переглянути у SonarCloud.
4. **Звіт:** скриншот Overview (Bugs / Vulnerabilities / Code Smells / Security Hotspots / Coverage / Duplications / Maintainability Rating) — це стан **ДО**.

## 2. Рефакторинг
Типові знахідки в такому проєкті й що з ними робити (звіряйте зі своїм списком у Sonar):

| Проблема (ймовірна) | Виправлення |
|---|---|
| Конфігурація БД | Версійовані API використовують `src/Support/Database.php`; не повертайте credentials до prototype handlers |
| Випадкові ігрові результати | Слот-символи та crash point генеруються через `random_int()` |
| Ідентифікатор користувача | Гравець визначається з JWT; не приймайте user id із тіла запиту |
| Баланс та одночасні ставки | Ігрові сервіси використовують транзакції та блокування рядка MySQL |
| Межі відповідальності | Складні операції винесені у `SlotMachine` та `GameService`; тримайте API handlers тонкими |
| «Магічні» числа (множники, шанси) | `const` / конфіг |
| `echo json_encode` розкидано по файлах | `Json::send()` / `Json::error()` |
| `vendor/`, `coverage/`, `.phpunit.result.cache` у git | `.gitignore` |

Після рефакторингу: `git commit`, push, дочекайтесь нового скану. **Звіт:** скриншот Sonar **ПІСЛЯ** + таблиця «було → стало».

## 3. Benchmark і Stress-тест
```bash
# Встановіть k6: https://k6.io/docs/get-started/installation/
# У .env на час тесту: RATE_LIMIT_MAX=1000000  (інакше лімітер вас заблокує — це очікувано!)
docker compose up -d --build
docker compose exec backend php scripts/migrate.php
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
`optimize.sql` знімає звичайний `EXPLAIN` (type/key/estimated rows/Extra) і `EXPLAIN ANALYZE` (фактичні rows та час)
до та після індексів. **Звіт:** запишіть фактичне порівняння, включно з `Using filesort`; не підставляйте очікувані значення.
Додатково — топ запитів з `performance_schema` (блок 5 у файлі).

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
`src/`, `vendor/`; старі неверсійовані API handlers вилучено; брутфорс-ліміт на логін.
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

# МОДУЛЬ 4. Контейнеризація, CI/CD, розгортання та супровід ПЗ

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
git clone https://github.com/OWNER/REPOSITORY.git /opt/win-casino && cd /opt/win-casino
cp .env.example .env && nano .env     # APP_ENV=production, свої паролі, JWT_SECRET, DOCKERHUB_USER,
                                      # APP_DOMAIN, API_DOMAIN, CACHE_DRIVER=redis, RATE_LIMIT_MAX=60
docker compose -f docker-compose.prod.yml --env-file .env up -d
docker compose -f docker-compose.prod.yml --env-file .env exec backend php scripts/migrate.php
docker compose -f docker-compose.prod.yml exec backend php scripts/seed.php
```
* **Frontend (п.5)**: окремий nginx-контейнер; `config.js` генерується зі змінної `API_BASE_URL=https://<API_DOMAIN>/api`.
* **Backend (п.6)**: `APP_ENV=production` (помилки не показуються клієнту), `opcache`, `expose_php=Off`, порти БД/Redis не опубліковані, `TRUSTED_PROXY=true` (реальний IP клієнта з Caddy для rate limit), HTTPS від Caddy (Let's Encrypt).
* **Взаємодія (п.7)**: відкрийте `https://<APP_DOMAIN>/login.html` → логін → у DevTools → Network видно запити на `https://<API_DOMAIN>/api/v1/...`; `scripts/smoke.sh https://<API_DOMAIN>/api/v1`.
* **CORS (п.8)**: `CORS_ALLOWED_ORIGINS=https://<APP_DOMAIN>` — лише цей origin отримує `Access-Control-Allow-Origin`; preflight `OPTIONS` → 204; чужий origin → без заголовка (smoke-тест це перевіряє). `*` не використовується.

Frontend сторінки вже підключають `config.js` та `casino-api.js` і викликають versioned API. Без токена API-клієнт очищає сесію та перенаправляє на `login.html`.

## 9. CI/CD
`.github/workflows/ci-cd.yml`: `test` (lint → init БД → PHPUnit+coverage → optional SonarCloud) → `e2e` (Compose → seed → API smoke → Cypress)
→ optional `publish` (Docker Hub, push у `main`) → optional `deploy` (SSH, compose pull/up, post-deploy smoke).

Секрети (Settings → Secrets and variables → Actions): `SONAR_TOKEN`, `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`,
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `SMOKE_PLAYER_PASSWORD`; змінні (Variables): `APP_DOMAIN`, `API_DOMAIN`, `PUBLISH_DOCKER_IMAGES=true`, `DEPLOY_ENABLED=true`.
Публікацію і deployment вмикайте лише після налаштування відповідних secrets, variables та production environment.
**Звіт:** скриншот зеленого pipeline (Actions), посилання на запуск.

## 10. Continuous Testing
Unit та integration suites запускаються окремо і блокують наступні jobs у разі помилки. Cypress має три UI сценарії з підміненими API відповідями; конфігурація Cypress 10+ зберігається в `cypress.config.js`. API інтеграцію перевіряє `scripts/smoke.sh`. Coverage `coverage/clover.xml` завантажується як artifact.

---

## Шаблон структури звіту
1. Мета і стек (PHP, MySQL, Redis, Docker, GitHub Actions).
2. Модуль 1: README, схема БД, Kanban, Learn Git Branching, гілки/PR.
3. Модуль 2: unit, exception, integration та E2E тести з командами й результатами.
4. Модуль 3: Sonar до/після, рефакторинг, k6 benchmark/stress, профілювання, DB `EXPLAIN ANALYZE`, reliability та API security/versioning/rate limiting/cache/env.
5. Модуль 4: Docker/Compose, запуск, CI/CD; за наявності — Container Registry і production deployment.
6. Висновки, обмеження та джерела доказів.

## Обмеження та докази, яких ще немає в репозиторії
* Fixed-window rate limiter допускає сплеск до 2× ліміту на межі вікон.
* JWT не має refresh/revocation механізму; зміна ролі не анулює вже виданий токен до його завершення.
* Crash-графік на клієнті є візуальною анімацією; сервер сам рахує множник і перевіряє виграш за часом раунду.
* У репозиторії немає доказів успішного Sonar scan, відсотків coverage, результатів k6/profiling, опублікованих образів чи доступного production URL. Їх потрібно отримати та додати у звіт після запуску.
