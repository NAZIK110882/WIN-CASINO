<?php
declare(strict_types=1);

use App\Cache\ArrayCache;
use App\Resilience\CircuitBreaker;
use App\Resilience\CircuitOpenException;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private int $now = 1000;

    private function breaker(): CircuitBreaker
    {
        return new CircuitBreaker('dep', new ArrayCache(), 3, 10, fn () => $this->now);
    }

    private function trip(CircuitBreaker $cb): void
    {
        try {
            $cb->call(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }
    }

    public function testOpensAfterThresholdAndFailsFast(): void
    {
        $cb = $this->breaker();
        for ($i = 0; $i < 3; $i++) {
            $this->trip($cb);
        }
        $this->assertSame('open', $cb->state());

        $called = false;
        try {
            $cb->call(function () use (&$called) {
                $called = true;
                return 1;
            });
            $this->fail('CircuitOpenException expected');
        } catch (CircuitOpenException $e) {
            $this->assertFalse($called, 'Dependency must not be called while open');
            $this->assertGreaterThan(0, $e->retryAfter);
        }
    }

    public function testHalfOpenSuccessClosesCircuit(): void
    {
        $cb = $this->breaker();
        for ($i = 0; $i < 3; $i++) {
            $this->trip($cb);
        }
        $this->now += 11;
        $this->assertSame('ok', $cb->call(fn () => 'ok'));
        $this->assertSame('closed', $cb->state());
    }

    public function testHalfOpenFailureReopensCircuit(): void
    {
        $cb = $this->breaker();
        for ($i = 0; $i < 3; $i++) {
            $this->trip($cb);
        }
        $this->now += 11;
        $this->trip($cb);
        $this->assertSame('open', $cb->state());
    }

    public function testSuccessResetsFailureCounter(): void
    {
        $cb = $this->breaker();
        $this->trip($cb);
        $this->trip($cb);
        $cb->call(fn () => 'ok');
        $this->trip($cb);
        $this->assertSame('closed', $cb->state());
    }
}
