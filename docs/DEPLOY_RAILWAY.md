# Демо-деплой WIN-CASINO на Railway

У проєкті є `docker-compose.prod.yml` для VPS. На Railway зручніше створити окремі сервіси з репозиторію: backend, frontend і MySQL. Redis для навчального демо можна не додавати — встановіть `CACHE_DRIVER=file`. Це вкладається у поточний ліміт Free-плану до трьох сервісів після trial; перед запуском перевірте актуальні ліміти та білінг Railway.

## 1. Підготуйте репозиторій і Railway

1. Запуште `feat/practicum-module-3` у GitHub.
2. У Railway створіть проєкт і під'єднайте репозиторій `NAZIK110882/WIN-CASINO`, вибравши цю гілку.
3. Додайте до проєкту базу **MySQL**.
4. Додайте backend service з цього репозиторію. Для нього залиште Dockerfile у корені (`Dockerfile`).
5. Додайте окремий frontend service з того самого репозиторію. У його Variables задайте `RAILWAY_DOCKERFILE_PATH=docker/frontend/Dockerfile`.

Railway дозволяє під'єднати GitHub-репозиторій до сервісу та вказати власний шлях Dockerfile ([документація сервісів](https://docs.railway.com/services), [Dockerfile](https://docs.railway.com/builds/dockerfiles)). У змінних MySQL посилання `MySQL` має збігатися з реальною назвою database service; Railway показує доступні посилання у підказках Variables.

## 2. Змінні backend

У Variables backend service додайте:

```dotenv
APP_ENV=production
PORT=80
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}
DB_CONNECT_TIMEOUT=5
DB_READ_TIMEOUT=10
DB_RETRY_ATTEMPTS=3
DB_RETRY_BASE_MS=100
DB_LOCK_WAIT_TIMEOUT=5
JWT_SECRET=<випадковий секрет щонайменше 32 символи>
JWT_TTL=3600
SEED_PLAYER_PASSWORD=<окремий сильний пароль>
SEED_ADMIN_PASSWORD=<інший сильний пароль>
CACHE_DRIVER=file
CACHE_TTL_USER=30
CACHE_TTL_LEADERBOARD=30
RATE_LIMIT_MAX=60
RATE_LIMIT_WINDOW=60
LOGIN_RATE_MAX=5
REQUEST_TIMEOUT=10
CB_FAILURE_THRESHOLD=5
CB_OPEN_SECONDS=15
API_V1_SUNSET=
```

`PORT=80` має збігатися з портом Apache у backend Docker image. Згенеруйте випадковий JWT secret локально командою `openssl rand -hex 32`; реальні секрети не додавайте до Git.

## 3. Домени, frontend і CORS

1. У backend service → Settings → Networking згенеруйте Railway domain. Цей сервіс слухає порт `80`.
2. У frontend service задайте:

```dotenv
API_BASE_URL=https://<backend-domain>/api
API_VERSION=v1
```

3. Згенеруйте domain для frontend service і поверніться у Variables backend. Задайте `CORS_ALLOWED_ORIGINS=https://<frontend-domain>`.
4. Застосуйте зміни та дочекайтеся успішного deployment обох сервісів.

Railway видає HTTPS для згенерованих доменів ([домени й networking](https://docs.railway.com/networking/domains/working-with-domains)). Секрети та змінні зберігайте у вкладці Variables, не в репозиторії.

## 4. Створіть схему та демо-користувачів

Після успішного deploy під'єднайте Railway CLI до проєкту та виконайте команди всередині backend service:

```bash
railway ssh --service backend -- php scripts/migrate.php
railway ssh --service backend -- php scripts/seed.php
```

Міграція створює необхідні таблиці для порожньої MySQL бази. Seed створює `player1` та `admin1`; не публікуйте їхні паролі. Для демонстрації використовуйте лише вигадані внутрішні кредити — у застосунку немає платіжної системи.

Команда `railway ssh` запускає команду всередині обраного deployed service ([CLI docs](https://docs.railway.com/cli/ssh)). Змінні MySQL надаються шаблоном сервісу ([MySQL docs](https://docs.railway.com/databases/mysql)).

Перевірте `https://<backend-domain>/api/v1/health` і відкрийте `https://<frontend-domain>`.

## Обмеження

Railway Free має ліміти сервісів, пам'яті й диска; trial має обмежений кредит і строк. Після завершення trial тарифи та ліміти можуть потребувати платного плану. Перевірте актуальні [ціни](https://docs.railway.com/pricing) та Usage/Billing перед залишенням застосунку запущеним. Стрес-тест до 400 VU, виконаний локально, був значно вищим за навантаження, для якого варто використовувати безкоштовний демо-хостинг.
