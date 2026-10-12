<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Game;

class GameTest extends TestCase
{
    public function testPlayReturnsStringOnPositiveBet()
    {
        $game = new Game();
        $result = $game->play(10);
        $this->assertIsString($result);
        $this->assertStringContainsString('ставкою 10', $result);
    }

    public function testPlayThrowsOnZeroOrNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $game = new Game();
        $game->play(0);
    }
}
