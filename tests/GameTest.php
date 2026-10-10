<?php
use PHPUnit\Framework\TestCase;
use App\Game;

class GameTest extends TestCase
{
    public function testPlayReturnsCorrectString(): void
    {
        $game = new Game();
        $result = $game->play(100);
        $this->assertEquals("Гра почалася зі ставкою 100", $result);
    }

    public function testPlayThrowsExceptionForInvalidBet(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play(0);
    }
}