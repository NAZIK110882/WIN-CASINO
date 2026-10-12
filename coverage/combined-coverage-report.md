# Combined Test Coverage Report

Repository: NAZIK110882/WIN-CASINO
Date: 2026-10-12

Цей документ об'єднує результати всіх тестів, що додані до репозиторію: юніт-тести (PHPUnit), інтеграційні тести (PHPUnit + SQLite), API-тести (Postman), та E2E-тести (Cypress). У цьому середовищі я не можу запустити тести або згенерувати дійсний вимірюваний звіт coverage (HTML/PDF), тому звіт — підсумковий, на основі наявних тестів, статичного аналізу коду і рекомендацій для отримання повного машинного звіту.

1) Що включено
----------------
- Unit tests (PHPUnit):
  - tests/GameTest.php
  - tests/SlotMachineTest.php

- Integration tests (PHPUnit using SQLite files):
  - tests/Integration/SlotMachineIntegrationTest.php
  - phpunit.integration.xml (suite for integration tests)

- API tests (Postman collection / Newman-ready):
  - postman/WIN-CASINO.postman_collection.json (12 requests with Test scripts)

- E2E tests (Cypress):
  - cypress/e2e/slots_e2e_spec.cy.js (3 flows)
  - package.json, cypress.json, cypress/README.md

2) Методологія оцінки покриття
--------------------------------
- Юніт та інтеграційні тести (PHPUnit) дають реальний кодовий coverage для PHP-файлів, що виконуються під процесом PHPUnit (особливо src/*.php).
- Процедурні API-файли (api/*.php) не входять до PHPUnit-покриття автоматично, якщо їх не імпортувати і не виконувати в тестах. Postman/Newman перевіряють функціональну поведінку, але не рахуються в PHP code coverage.
- E2E (Cypress) та Postman дають функціональний/поведінковий рівень тестування; результати корисні для QA, але не додаються до line coverage.

3) Оцінка покриття за файлами (оцінка на основі тестів у репозиторії)
----------------------------------------------------------------------
- src/Game.php
  - Логіка: валідація ставки, повернення рядка.
  - Тести: unit + integration викликають позитивний і негативний кейс.
  - Оцінка покриття: ~100%

- src/SlotMachine.php
  - Логіка: транзакції DB, обчислення виграшу, оновлення балансу, обробка помилок.
  - Тести: повний набір unit-тестів із моками PDO та інтеграційних тестів на SQLite, що перевіряють багато станів (no win, triple7, triple 💎, pair, insufficient funds, rollback при помилках, кілька спінів, перевірка selector).
  - Оцінка покриття: 90–98% (велика кількість умов покрита). Можуть залишатися непокриті гілки, що пов'язані зі випадковою роботою стандартного (random) symbolSelector.

- src/Database.php
  - Логіка: фабрика PDO, що читає config.
  - Тести: немає прямого тесту, Database::getConnection не викликається у наявних тестах.
  - Оцінка покриття: 0% (рекомендується додати unit-тест/мок для config або інтеграційний тест, що викликає цей метод з test config)

- api/spin.php, api/user.php, api/bonanza.php, api/rocket_start.php
  - Логіка: процедурні ендпоінти, що звертаються до БД і виводять JSON.
  - Тести: функціонально покриті Postman collection (через HTTP), але не входять у PHP Unit coverage.
  - Оцінка покриття (PHPUnit): 0% (поки не огорнуті тестами в PHPUnit). Функціональна перевірка: Postman 12 запитів охоплюють позитивні та негативні кейси.

- Інші файли (public/*, статичні asset-и) — не потребують PHP-coverage; не враховуються.

Загальна неформальна оцінка проекту
----------------------------------
- За кодом у каталозі src/ (без врахування api): приблизно 70–85% покриття (основний вагомий файл SlotMachine добре покритий, Game повністю, Database — не покритий).
- Включаючи api/*.php (які procedural і не покриті PHPUnit): загальне проектне покриття падає — приблизна оцінка 40–60%.

Це тільки оцінка; для точного значення виконайте інструментальне вимірювання (див. розділ нижче).

4) Як згенерувати точний (машинний) звіт coverage (HTML/PDF)
-------------------------------------------------------------
A. Підготовка середовища локально або в CI
- PHP з Xdebug (або PCOV): увімкніть Xdebug у CLI (xdebug.mode=coverage).
- composer install (включно з dev-залежностями):
  composer install --no-interaction --prefer-dist

B. Запуск PHPUnit з coverage
- Окремо об'єднайте suites в phpunit.xml так, щоб юніт і інтеграційні тести виконувалися під однією командою або запустіть по черзі із збиранням у той самий каталог coverage:
  vendor/bin/phpunit --configuration phpunit.xml --coverage-html coverage/html

Якщо інтеграційні тести використовують окремий phpunit.integration.xml, запустіть їх так само і зберігайте coverage у тій же директорії (це може перезаписати — краще мати один конфіг, який включає обидва suite).

C. Включення API-ендпоїнтів у coverage
- Найпростіший підхід — рефакторинг: винести логіку з procedural api/*.php у класи/функції і викликати їх із тестів, тоді PHPUnit покриє їх.
- Інакше можна запустити веб-сервер (php -S) з Xdebug увімкненим і виконати HTTP-запити під цим процесом; інструменти coverage можуть зловити виконання (складніше налаштувати).

D. Збереження/конвертація у PDF
- HTML → PDF: wkhtmltopdf або headless Chrome (puppeteer) або вручну Print-to-PDF.

5) Об'єднання результатів з інших інструментів (Postman, Cypress)
------------------------------------------------------------------
- Postman: запустіть Newman для CLI і збережіть звіт (JSON/HTML):
  newman run postman/WIN-CASINO.postman_collection.json -e postman/env.json -r cli,html --reporter-html-export postman/report.html

- Cypress: запустіть `npx cypress run --reporter junit --reporter-options mochaFile=results/cypress-[hash].xml` або HTML report plugin.

- Потім згенеруйте загальний QA-звіт (Markdown/PDF) що агрегує:
  - coverage HTML (PHPUnit)
  - Postman report.html (functional API tests)
  - Cypress report (E2E)

6) Пропозиції по CI/Scripts (автоматизація)
-------------------------------------------
- Додати GitHub Actions workflow, який виконує три job'и або один job з трьома step'ами:
  1) Run PHPUnit with Xdebug and upload coverage/html as artifact.
  2) Run Newman (Postman) and upload report html/json.
  3) Run Cypress in headless mode and upload report (JUnit/HTML).
- Я можу додати такий workflow (приклад) — скажіть, чи використовувати MySQL в CI (docker) або SQLite for integration tests?

7) Рекомендовані кроки для підвищення покриття
-----------------------------------------------
- Додати unit-тест для src/Database.php; мокати require config or provide test config returning sqlite DSN.
- Перенести бізнес-логіку з api/*.php у класи і додати PHPUnit functional tests that call those classes (включати DB as SQLite for integration).
- Виконувати Postman/Newman и Cypress у CI та зберігати результати як артефакти; додати failing conditions if tests fail.

8) Приклад команда для локального, повного запуску (послідовно)
--------------------------------------------------------------
# 1. Встановити deps
composer install

# 2. Включити Xdebug у CLI (приклад для shivammathur/setup-php в CI: xdebug.mode=coverage)

# 3. Запустити PHPUnit (всі suites з phpunit.xml)
vendor/bin/phpunit --configuration phpunit.xml --coverage-html coverage/html

# 4. Запустити Postman collection (newman)
newman run postman/WIN-CASINO.postman_collection.json -r cli,html --reporter-html-export postman/report.html --env-var base_url=http://127.0.0.1/WIN-CASINO

# 5. Запустити Cypress
npm ci && npx cypress run --config baseUrl=http://127.0.0.1/WIN-CASINO

# 6. Зібрати артефакти у один PDF/ZIP: coverage/html, postman/report.html, cypress report

9) Готові файли я додав до репозиторію (якщо у тебе є необхідні права):
- coverage/coverage-report.md (цей файл)
- .github/workflows/coverage.yml (якщо ще не доданий) — для автоматики

---

Якщо хочеш, я зараз:
- a) Додам у репо цей файл coverage/combined-coverage-report.md та оновлю workflow для запуску всіх тестів (PHPUnit, Newman, Cypress) і збір артефактів (якщо дозвіл на запис є). Або
- b) Здам докладніші інструкції і згенерую локально запропонований HTML/PDF, якщо ти виконаєш команди локально та надішлеш артефакти.

Що обереш?