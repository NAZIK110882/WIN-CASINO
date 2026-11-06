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

        $databaseUrl = Env::get('DATABASE_URL');
        if ($databaseUrl !== null && $databaseUrl !== '') {
            $parts = parse_url($databaseUrl);
            if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
                throw new \RuntimeException('DATABASE_URL is not a valid PostgreSQL connection URL');
            }
            parse_str($parts['query'] ?? '', $query);
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d',
                $parts['host'],
                (int) ($parts['port'] ?? 5432),
                ltrim($parts['path'], '/'),
                $query['sslmode'] ?? 'require',
                Env::int('DB_CONNECT_TIMEOUT', 3)
            );
            $username = rawurldecode($parts['user'] ?? '');
            $password = rawurldecode($parts['pass'] ?? '');
            $driver = 'pgsql';
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::int('DB_PORT', 3306),
                Env::get('DB_NAME', 'casino_db')
            );
            $username = Env::get('DB_USER', 'root');
            $password = Env::get('DB_PASS', '');
            $driver = 'mysql';
        }

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($driver === 'mysql') {
            $options[\PDO::ATTR_TIMEOUT] = Env::int('DB_CONNECT_TIMEOUT', 3);
        }
        // таймаут читання відповіді (доступний у mysqlnd)
        if ($driver === 'mysql' && defined('PDO::MYSQL_ATTR_READ_TIMEOUT')) {
            $options[constant('PDO::MYSQL_ATTR_READ_TIMEOUT')] = Env::int('DB_READ_TIMEOUT', 5);
        }

        $breaker = new CircuitBreaker(
            $driver,
            CacheFactory::make(),
            Env::int('CB_FAILURE_THRESHOLD', 5),
            Env::int('CB_OPEN_SECONDS', 15)
        );

        $connect = static fn (): \PDO => new \PDO(
            $dsn,
            $username,
            $password,
            $options
        );

        self::$pdo = $breaker->call(static fn () => Retry::run(
            $connect,
            Env::int('DB_RETRY_ATTEMPTS', 3),
            Env::int('DB_RETRY_BASE_MS', 100),
            static fn (\Throwable $e): bool => $e instanceof \PDOException && (
                in_array((int) ($e->errorInfo[1] ?? $e->getCode()), [2002, 2006, 2013, 1040], true)
                || in_array((string) ($e->errorInfo[0] ?? ''), ['08000', '08001', '08003', '08006', '57P01'], true)
            )
        ));

        if ($driver === 'mysql') {
            self::$pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . Env::int('DB_LOCK_WAIT_TIMEOUT', 5));
        }
        return self::$pdo;
    }
}
