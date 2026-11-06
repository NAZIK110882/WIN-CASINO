<?php
declare(strict_types=1);

namespace App\Cache;

use App\Support\Env;

final class CacheFactory
{
    private static ?CacheInterface $instance = null;

    public static function make(): CacheInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (Env::get('CACHE_DRIVER', 'file') === 'redis' && class_exists(\Redis::class)) {
            try {
                $redis = new \Redis();
                $redis->connect(
                    Env::get('REDIS_HOST', '127.0.0.1'),
                    Env::int('REDIS_PORT', 6379),
                    (float) Env::int('REDIS_TIMEOUT', 1)
                );
                $password = Env::get('REDIS_PASSWORD');
                if ($password !== null && $password !== '') {
                    $redis->auth($password);
                }
                return self::$instance = new RedisCache($redis);
            } catch (\Throwable $e) {
                // Fault tolerance: Redis впав -> працюємо через файловий кеш
                error_log('[cache] Redis unavailable, fallback to FileCache: ' . $e->getMessage());
            }
        }

        return self::$instance = new FileCache(
            Env::get('CACHE_DIR', sys_get_temp_dir() . '/casino-cache')
        );
    }

    public static function driverName(): string
    {
        return match (true) {
            self::make() instanceof RedisCache => 'redis',
            self::make() instanceof FileCache => 'file',
            default => 'array',
        };
    }
}
