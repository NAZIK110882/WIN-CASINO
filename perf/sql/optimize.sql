-- ===== ЗАВДАННЯ 5: аналіз і оптимізація запитів =====
-- Підставте реальний id користувача, у якого є раунди (SELECT user_id, COUNT(*) FROM game_rounds GROUP BY 1;)
USE casino_db;
SET @uid = 1;

-- 0) Зробіть скриншот "ДО": прибираємо індекси
DROP INDEX idx_rounds_user_id    ON game_rounds;
DROP INDEX idx_rounds_created_at ON game_rounds;

-- 1) Історія гравця
EXPLAIN ANALYZE
SELECT id, game, bet, win, created_at FROM game_rounds
 WHERE user_id = @uid ORDER BY id DESC LIMIT 20;

-- 2) Лідерборд за 24 год
EXPLAIN ANALYZE
SELECT u.username, SUM(r.win - r.bet) AS profit
  FROM game_rounds r JOIN users u ON u.id = r.user_id
 WHERE r.created_at >= NOW() - INTERVAL 1 DAY
 GROUP BY r.user_id, u.username ORDER BY profit DESC LIMIT 10;

-- 3) Додаємо індекси
CREATE INDEX idx_rounds_user_id    ON game_rounds (user_id, id);
CREATE INDEX idx_rounds_created_at ON game_rounds (created_at, user_id, bet, win);

-- 4) Скриншот "ПІСЛЯ": ті самі запити. Очікування: type=ref/range замість ALL,
--    rows з ~500 000 -> десятки/тисячі, зникає "Using filesort" для запиту 1.
EXPLAIN ANALYZE
SELECT id, game, bet, win, created_at FROM game_rounds
 WHERE user_id = @uid ORDER BY id DESC LIMIT 20;

EXPLAIN ANALYZE
SELECT u.username, SUM(r.win - r.bet) AS profit
  FROM game_rounds r JOIN users u ON u.id = r.user_id
 WHERE r.created_at >= NOW() - INTERVAL 1 DAY
 GROUP BY r.user_id, u.username ORDER BY profit DESC LIMIT 10;

-- 5) Топ найповільніших запитів за час роботи сервера (performance_schema)
SELECT LEFT(DIGEST_TEXT, 100) AS query,
       COUNT_STAR AS calls,
       ROUND(AVG_TIMER_WAIT / 1e9, 2)  AS avg_ms,
       ROUND(SUM_TIMER_WAIT / 1e9, 2)  AS total_ms,
       SUM_ROWS_EXAMINED AS rows_examined
  FROM performance_schema.events_statements_summary_by_digest
 WHERE SCHEMA_NAME = 'casino_db'
 ORDER BY SUM_TIMER_WAIT DESC LIMIT 10;
