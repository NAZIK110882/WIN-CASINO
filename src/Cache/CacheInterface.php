<?php
declare(strict_types=1);

namespace App\Cache;

interface CacheInterface
{
    /** @return mixed null якщо ключа немає або термін дії минув */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): void;

    public function delete(string $key): void;

    /** Атомарний інкремент лічильника; TTL задається при створенні ключа. */
    public function increment(string $key, int $ttl): int;
}
