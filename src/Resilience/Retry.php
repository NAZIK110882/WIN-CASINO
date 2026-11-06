<?php
declare(strict_types=1);

namespace App\Resilience;

final class Retry
{
    /**
     * Виконує $op з повторними спробами та exponential backoff + jitter.
     *
     * @param callable(): mixed                 $op
     * @param (callable(\Throwable): bool)|null $shouldRetry true для тимчасових помилок
     * @param (callable(int): void)|null        $sleep       підміна sleep (для тестів), аргумент — мс
     */
    public static function run(
        callable $op,
        int $attempts = 3,
        int $baseDelayMs = 100,
        ?callable $shouldRetry = null,
        ?callable $sleep = null
    ): mixed {
        $sleep ??= static fn (int $ms) => usleep($ms * 1000);
        $attempt = 0;

        while (true) {
            try {
                return $op();
            } catch (\Throwable $e) {
                $attempt++;
                if ($attempt >= $attempts || ($shouldRetry !== null && !$shouldRetry($e))) {
                    throw $e;
                }
                $delay = $baseDelayMs * (2 ** ($attempt - 1));
                $sleep($delay + random_int(0, intdiv($delay, 4)));
            }
        }
    }
}
