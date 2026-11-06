<?php
declare(strict_types=1);

namespace App\Security;

use App\Cache\CacheInterface;
use App\Support\Env;

/** Fixed-window rate limiter: не більше $limit запитів за $windowSeconds на один ключ клієнта. */
final class RateLimiter
{
    private ?\Closure $clock;

    public function __construct(
        private CacheInterface $store,
        private int $limit,
        private int $windowSeconds,
        ?callable $clock = null
    ) {
        $this->clock = $clock === null ? null : \Closure::fromCallable($clock);
    }

    /** @return array{allowed:bool,limit:int,remaining:int,reset:int} */
    public function hit(string $clientKey): array
    {
        $now = $this->clock !== null ? (int) ($this->clock)() : time();
        $window = intdiv($now, $this->windowSeconds);
        $count = $this->store->increment("rl:$clientKey:$window", $this->windowSeconds);

        return [
            'allowed' => $count <= $this->limit,
            'limit' => $this->limit,
            'remaining' => max(0, $this->limit - $count),
            'reset' => ($window + 1) * $this->windowSeconds,
        ];
    }

    /** IP клієнта. X-Forwarded-For враховується лише якщо TRUSTED_PROXY=true (за reverse proxy). */
    public static function clientKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (Env::bool('TRUSTED_PROXY')) {
            $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
            if ($forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP)) {
                $ip = $forwarded;
            }
        }
        return $ip;
    }
}
