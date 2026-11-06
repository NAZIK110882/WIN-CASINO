-- 500 000 раундів для реалістичного навантаження на БД. Потрібні користувачі (php scripts/seed.php).
USE casino_db;
SET SESSION cte_max_recursion_depth = 500000;

INSERT INTO game_rounds (user_id, game, bet, win, created_at)
WITH RECURSIVE seq AS (
    SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 500000
)
SELECT (SELECT MIN(id) FROM users) + (n % (SELECT COUNT(*) FROM users)),
       ELT(1 + n % 3, 'slots', 'bonanza', 'rocket'),
       1 + (n % 100),
       IF(n % 4 = 0, (n % 250), 0),
       NOW() - INTERVAL (n % 172800) SECOND
FROM seq;

ANALYZE TABLE game_rounds;
