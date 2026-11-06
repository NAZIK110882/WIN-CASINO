<?php
declare(strict_types=1);

namespace App\Cache;

/**
 * Файловий кеш із блокуванням (flock). Працює між запитами PHP без Redis,
 * тому є fallback-ом, якщо Redis недоступний.
 */
final class FileCache implements CacheInterface
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("Cannot create cache dir: {$this->dir}");
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['exp'] ?? 0) <= time()) {
            @unlink($path);
            return null;
        }
        return $data['v'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $payload = json_encode(['exp' => time() + $ttl, 'v' => $value], JSON_THROW_ON_ERROR);
        $tmp = $this->path($key) . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $payload, LOCK_EX);
        rename($tmp, $this->path($key));
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    public function increment(string $key, int $ttl): int
    {
        $fp = fopen($this->path($key), 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Cache file is not writable');
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $data = $raw ? json_decode($raw, true) : null;
            if (!is_array($data) || ($data['exp'] ?? 0) <= time()) {
                $data = ['exp' => time() + $ttl, 'v' => 0];
            }
            $data['v'] = (int) $data['v'] + 1;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            return $data['v'];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.cache';
    }
}
