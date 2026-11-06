<?php
declare(strict_types=1);

namespace App\Support;

use App\Cache\CacheFactory;
use App\Resilience\CircuitBreaker;
use App\Resilience\Retry;

/**
 * Єдина точка створення PDO: конфіг з env + Timeout + Retry + Circuit Breaker.
 */
final class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 3306),
            Env::get('DB_NAME', 'casino_db')
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT => Env::int('DB_CONNECT_TIMEOUT', 3), // таймаут підключення
        ];
        // таймаут читання відповіді (доступний у mysqlnd)
        if (defined('PDO::MYSQL_ATTR_READ_TIMEOUT')) {
            $options[constant('PDO::MYSQL_ATTR_READ_TIMEOUT')] = Env::int('DB_READ_TIMEOUT', 5);
        }

        $breaker = new CircuitBreaker(
            'mysql',
            CacheFactory::make(),
            Env::int('CB_FAILURE_THRESHOLD', 5),
            Env::int('CB_OPEN_SECONDS', 15)
        );

        $connect = static fn (): \PDO => new \PDO(
            $dsn,
            Env::get('DB_USER', 'root'),
            Env::get('DB_PASS', ''),
            $options
        );

        self::$pdo = $breaker->call(static fn () => Retry::run(
            $connect,
            Env::int('DB_RETRY_ATTEMPTS', 3),
            Env::int('DB_RETRY_BASE_MS', 100),
            static fn (\Throwable $e): bool => $e instanceof \PDOException
                && in_array((int) ($e->errorInfo[1] ?? $e->getCode()), [2002, 2006, 2013, 1040], true)
        ));

        self::$pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . Env::int('DB_LOCK_WAIT_TIMEOUT', 5));
        return self::$pdo;
    }
}
