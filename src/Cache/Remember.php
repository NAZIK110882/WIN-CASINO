<?php
declare(strict_types=1);

namespace App\Cache;

/** Cache-aside + лічильники hit/miss для демонстрації зменшення звернень до БД. */
final class Remember
{
    public static function get(CacheInterface $cache, string $key, int $ttl, callable $loader): mixed
    {
        $value = $cache->get($key);
        if ($value !== null) {
            $cache->increment('metrics:cache_hit', 86400);
            return $value;
        }
        $cache->increment('metrics:cache_miss', 86400);
        $value = $loader();
        if ($value !== null) {
            $cache->set($key, $value, $ttl);
        }
        return $value;
    }
}
