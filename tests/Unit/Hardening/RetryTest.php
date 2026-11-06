<?php
declare(strict_types=1);

use App\Resilience\Retry;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    public function testSucceedsAfterTransientFailures(): void
    {
        $calls = 0;
        $result = Retry::run(function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                throw new RuntimeException('temporary');
            }
            return 'ok';
        }, 5, 10, null, fn () => null);

        $this->assertSame('ok', $result);
        $this->assertSame(3, $calls);
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $calls = 0;
        $thrown = false;
        try {
            Retry::run(function () use (&$calls) {
                $calls++;
                throw new RuntimeException('down');
            }, 3, 10, null, fn () => null);
        } catch (RuntimeException) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
        $this->assertSame(3, $calls);
    }

    public function testDoesNotRetryNonTransientErrors(): void
    {
        $calls = 0;
        $thrown = false;
        try {
            Retry::run(function () use (&$calls) {
                $calls++;
                throw new InvalidArgumentException('bad input');
            }, 5, 10, fn (Throwable $e) => !$e instanceof InvalidArgumentException, fn () => null);
        } catch (InvalidArgumentException) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
        $this->assertSame(1, $calls);
    }

    public function testBackoffGrowsExponentially(): void
    {
        $delays = [];
        try {
            Retry::run(fn () => throw new RuntimeException('x'), 4, 100, null, function (int $ms) use (&$delays) {
                $delays[] = $ms;
            });
        } catch (RuntimeException) {
        }
        $this->assertCount(3, $delays);
        $this->assertGreaterThanOrEqual(100, $delays[0]);
        $this->assertGreaterThanOrEqual(200, $delays[1]);
        $this->assertGreaterThanOrEqual(400, $delays[2]);
    }
}
