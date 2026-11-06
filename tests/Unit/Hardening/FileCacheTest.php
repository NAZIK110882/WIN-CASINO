<?php
declare(strict_types=1);

use App\Cache\ArrayCache;
use App\Cache\FileCache;
use App\Cache\Remember;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/casino-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testSetGetDelete(): void
    {
        $c = new FileCache($this->dir);
        $this->assertNull($c->get('k'));
        $c->set('k', ['a' => 1], 60);
        $this->assertSame(['a' => 1], $c->get('k'));
        $c->delete('k');
        $this->assertNull($c->get('k'));
    }

    public function testIncrementIsCumulative(): void
    {
        $c = new FileCache($this->dir);
        $this->assertSame(1, $c->increment('n', 60));
        $this->assertSame(2, $c->increment('n', 60));
        $this->assertSame(3, $c->increment('n', 60));
    }

    public function testRememberCallsLoaderOnlyOnce(): void
    {
        $cache = new ArrayCache();
        $dbCalls = 0;
        $loader = function () use (&$dbCalls) {
            $dbCalls++;
            return ['balance' => 100];
        };
        for ($i = 0; $i < 10; $i++) {
            Remember::get($cache, 'user:1', 30, $loader);
        }
        $this->assertSame(1, $dbCalls, '10 запитів -> лише 1 звернення до БД');
        $this->assertSame(9, $cache->get('metrics:cache_hit'));
        $this->assertSame(1, $cache->get('metrics:cache_miss'));
    }
}
