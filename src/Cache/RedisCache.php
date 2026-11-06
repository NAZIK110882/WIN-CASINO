<?php
declare(strict_types=1);

namespace App\Cache;

final class RedisCache implements CacheInterface
{
    public function __construct(private \Redis $redis, private string $prefix = 'casino:')
    {
    }

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === false) {
            return null;
        }
        return json_decode((string) $raw, true);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->redis->setex($this->prefix . $key, max(1, $ttl), json_encode($value, JSON_THROW_ON_ERROR));
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->prefix . $key);
    }

    public function increment(string $key, int $ttl): int
    {
        $k = $this->prefix . $key;
        $n = (int) $this->redis->incr($k);
        if ($n === 1) {
            $this->redis->expire($k, max(1, $ttl));
        }
        return $n;
    }
}
