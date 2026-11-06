# WIN-CASINO

Навчальний вебзастосунок з іграми Neon Slots, Sugar Bonanza та Space Crash. Проєкт поєднує PHP API, MySQL, авторизацію, клієнтський інтерфейс, автоматизовані перевірки й Docker-оточення. Це демонстраційний проєкт: реальні гроші та платежі не підтримуються.

## Можливості

- Ігрові сторінки та логіка ставок із серверним збереженням балансу.
- PHP 8.2+, PDO, MySQL 8.4 локально та PostgreSQL для Render; Redis-кеш із файловим fallback.
- Версійоване API `/api/v1` і `/api/v2`, JWT-автентифікація, рольова авторизація, валідація, CORS і rate limiting.
- Механізми retry, timeout і circuit breaker.
- PHPUnit unit та integration tests, Cypress UI E2E, API smoke checks і k6 сценарії.
- Docker Compose для локального запуску; окремі production-конфігурація та GitHub Actions workflow.

## Структура

```text
api/                    shared bootstrap та versioned API endpoints
src/                    ігрова логіка, HTTP, security, cache та resilience
public/                 PHP public entry point, assets та сторінки ігор
tests/                  PHPUnit unit та integration tests
cypress/                Cypress E2E сценарії
postman/                Postman collection з API checks
docs/                   OpenAPI та інструкції до практикуму
docker/                 MySQL schema, Apache, nginx, Caddy та PHP конфігурація
perf/                   k6 сценарії й SQL для performance testing
scripts/                database migration, seed та API smoke script
.github/workflows/      CI/CD workflow
```

`index.html`, `slots.html`, `bonanza.html` та `rocket.html` — статичний frontend. У локальному Compose він працює окремо від API; Render Blueprint збирає фронтенд і API в один Docker web service. Старі неверсійовані API-маршрути вилучені; використовуйте `/api/v1/*`.

## Безкоштовний деплой на Render

У корені є `render.yaml`: він створює Docker web service і PostgreSQL, обидва на Free-плані. Render PostgreSQL видаляється через 30 днів, тому цей варіант підходить для показу практикуму, а не для постійного зберігання даних. Інструкція: [docs/DEPLOY_RENDER.md](docs/DEPLOY_RENDER.md).

## Вимоги

- PHP 8.2+ з PDO, mbstring та PDO SQLite для integration tests.
- Composer 2.
- Node.js 16+ та npm для Cypress.
- Docker Engine із Docker Compose v2 — рекомендований спосіб запустити повний застосунок локально.

## Локальний запуск через Docker

```bash
cp .env.example .env
```

Для локального навчального середовища значення у `.env.example` можна лишити. Для реального серверного використання замініть усі паролі й `JWT_SECRET` на випадкові секрети; `.env` не додавайте до Git.

```bash
docker compose up -d --build --wait
docker compose exec backend php scripts/migrate.php
docker compose exec backend php scripts/seed.php
```

Адреси після запуску:

- Frontend: <http://localhost:8080>
- API health: <http://localhost:8000/api/v1/health>
- Ігрові сторінки legacy frontend: <http://localhost:8080/slots.html>, `/bonanza.html`, `/rocket.html`

Тестові облікові дані після seed беруться зі змінних `SEED_PLAYER_PASSWORD` і `SEED_ADMIN_PASSWORD` у `.env`. Не використовуйте їх у публічному production середовищі.

Зупинити середовище:

```bash
docker compose down
```

Дані MySQL залишаються у Docker volume. Щоб видалити також локальні дані БД, виконайте `docker compose down -v`.

## Розробка без Docker

Встановіть залежності та задайте змінні середовища з `.env.example`. Для PHPUnit:

```bash
composer install
composer test

# Unit coverage summary in the terminal
vendor/bin/phpunit --coverage-text
composer test:integration
```

Для frontend E2E потрібен запущений сайт за адресою `http://localhost:8080`:

```bash
npm install
npm run cypress:run
```

E2E сценарії перевіряють браузерний інтерфейс із контрольованими API-відповідями. Реальну інтеграцію API окремо перевіряє `scripts/smoke.sh` у CI та Docker-оточенні.

## Перевірки та звіти

```bash
# PHP syntax
find api src scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l

# Unit suite
composer test

# Unit suite with a terminal coverage summary
vendor/bin/phpunit --coverage-text

# Integration suite окремо
composer test:integration

# API smoke (потрібен запущений stack і seeded DB)
CASINO_PASSWORD='значення SEED_PLAYER_PASSWORD' \
ALLOWED_ORIGIN=http://localhost:8080 \
scripts/smoke.sh http://localhost:8000/api/v1
```

Coverage Clover-файл формується в CI як `coverage/clover.xml` та зберігається як artifact. Для локального summary встановіть Xdebug або PCOV і запустіть `vendor/bin/phpunit --coverage-text`. Показники SonarCloud, навантаження k6 і профілювання потрібно отримати під час запуску інструментів; самі конфігурації не є результатами вимірювання. Див. [інструкцію до практикуму](docs/PRACTICUM_GUIDE.md).

## API документація

OpenAPI 3.1 специфікація розташована в [docs/openapi.yaml](docs/openapi.yaml). Її можна відкрити через Swagger Editor або інший OpenAPI viewer. У специфікації описані методи, параметри, авторизація, статуси та формати відповідей.

Основні маршрути:

| Метод | Маршрут | Призначення |
|---|---|---|
| `GET` | `/api/v1/health` | Стан API, БД та кешу |
| `POST` | `/api/v1/login` | Отримати JWT |
| `GET` | `/api/v1/user` | Баланс поточного користувача |
| `POST` | `/api/v1/spin` | Зробити ставку у слотах |
| `POST` | `/api/v1/bonanza` | Зіграти раунд Bonanza |
| `POST` | `/api/v1/rocket_start` | Почати раунд Space Crash |
| `GET` | `/api/v1/rocket_state` | Отримати стан активного раунду |
| `POST` | `/api/v1/rocket_win` | Забрати виграш активного раунду |
| `GET` | `/api/v1/history` | Історія раундів користувача |
| `GET` | `/api/v1/leaderboard` | Таблиця лідерів |
| `GET` | `/api/v1/admin_stats` | Статистика для адміністратора |
| `GET` | `/api/v2/user` | Профіль у форматі API v2 |

Ставки та результати в цьому навчальному застосунку не є фінансовими операціями.

## CI/CD та deployment

Workflow `.github/workflows/ci-cd.yml` запускає lint, PHPUnit, API smoke та Cypress. Публікація образів і deployment є окремими опціональними jobs та вмикаються GitHub Actions variables; інструкція налаштування секретів і змінних наведена в [docs/PRACTICUM_GUIDE.md](docs/PRACTICUM_GUIDE.md). Успішний локальний build не означає, що застосунок вже опублікований або розгорнутий.

Інструкція для демо-розгортання на Railway: [docs/DEPLOY_RAILWAY.md](docs/DEPLOY_RAILWAY.md). На Railway можна використати backend, frontend та MySQL окремими сервісами; для компактного демо Redis можна вимкнути через файловий кеш.

## Командна робота

Для кожної задачі створюйте окрему feature/fix/docs/test гілку, відкривайте Pull Request і просіть іншого учасника про review. Issues, GitHub Project/Kanban, призначення задач та історію review ведіть у GitHub — ці дані не зберігаються у локальних файлах проєкту.

## Команда

Додайте імена учасників та їхні ролі після узгодження з командою.
