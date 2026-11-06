<?php
declare(strict_types=1);

namespace App\Cache;

/** In-memory кеш на час одного процесу. Використовується в unit-тестах. */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{exp:int, v:mixed}> */
    private array $data = [];

    public function get(string $key): mixed
    {
        $item = $this->data[$key] ?? null;
        if ($item === null) {
            return null;
        }
        if ($item['exp'] <= time()) {
            unset($this->data[$key]);
            return null;
        }
        return $item['v'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = ['exp' => time() + $ttl, 'v' => $value];
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }

    public function increment(string $key, int $ttl): int
    {
        $current = $this->get($key);
        if (!is_int($current)) {
            $this->set($key, 1, $ttl);
            return 1;
        }
        $this->data[$key]['v'] = $current + 1;
        return $current + 1;
    }
}
