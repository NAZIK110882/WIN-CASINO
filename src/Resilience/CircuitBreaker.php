<?php
declare(strict_types=1);

namespace App\Resilience;

use App\Cache\CacheInterface;

/**
 * Circuit Breaker: closed -> open (після N помилок) -> half_open (після паузи) -> closed.
 * Стан зберігається у кеші, тому спільний для всіх PHP-процесів.
 */
final class CircuitBreaker
{
    private ?\Closure $clock;

    public function __construct(
        private string $name,
        private CacheInterface $store,
        private int $failureThreshold = 5,
        private int $openSeconds = 15,
        ?callable $clock = null
    ) {
        $this->clock = $clock === null ? null : \Closure::fromCallable($clock);
    }

    public function call(callable $op): mixed
    {
        $s = $this->load();

        if ($s['state'] === 'open') {
            $elapsed = $this->now() - $s['openedAt'];
            if ($elapsed < $this->openSeconds) {
                throw new CircuitOpenException($this->name, $this->openSeconds - $elapsed);
            }
            $s['state'] = 'half_open';
            $this->save($s);
        }

        try {
            $result = $op();
        } catch (\Throwable $e) {
            $s['failures']++;
            if ($s['state'] === 'half_open' || $s['failures'] >= $this->failureThreshold) {
                $s['state'] = 'open';
                $s['openedAt'] = $this->now();
            }
            $this->save($s);
            throw $e;
        }

        if ($s['state'] !== 'closed' || $s['failures'] > 0) {
            $this->save(['state' => 'closed', 'failures' => 0, 'openedAt' => 0]);
        }
        return $result;
    }

    public function state(): string
    {
        return $this->load()['state'];
    }

    /** @return array{state:string,failures:int,openedAt:int} */
    private function load(): array
    {
        $s = $this->store->get($this->key());
        return is_array($s) ? $s : ['state' => 'closed', 'failures' => 0, 'openedAt' => 0];
    }

    private function save(array $s): void
    {
        $this->store->set($this->key(), $s, 3600);
    }

    private function key(): string
    {
        return 'cb:' . $this->name;
    }

    private function now(): int
    {
        return $this->clock !== null ? (int) ($this->clock)() : time();
    }
}
