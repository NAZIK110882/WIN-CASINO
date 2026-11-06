<?php
declare(strict_types=1);

use App\Cache\ArrayCache;
use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testBlocksAfterLimitWithinWindow(): void
    {
        $rl = new RateLimiter(new ArrayCache(), 3, 60, fn () => 1200);
        $this->assertTrue($rl->hit('ip1')['allowed']);
        $this->assertTrue($rl->hit('ip1')['allowed']);
        $last = $rl->hit('ip1');
        $this->assertTrue($last['allowed']);
        $this->assertSame(0, $last['remaining']);

        $blocked = $rl->hit('ip1');
        $this->assertFalse($blocked['allowed']);
        $this->assertSame(1260, $blocked['reset']);
    }

    public function testClientsAreIndependent(): void
    {
        $rl = new RateLimiter(new ArrayCache(), 1, 60, fn () => 1200);
        $this->assertTrue($rl->hit('a')['allowed']);
        $this->assertFalse($rl->hit('a')['allowed']);
        $this->assertTrue($rl->hit('b')['allowed']);
    }
}
