<?php
use PHPUnit\Framework\TestCase;
use App\Game;
use App\ApiRouter;

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

    public function testPlayThrowsExceptionForZero(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play(0);
    }

    public function testPlayThrowsExceptionForNegativeBet(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play(-5);
    }

    public function testPlayThrowsExceptionForZeroString(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play('0');
    }

    public function testPlayThrowsExceptionForNegativeString(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play('-10');
    }

    public function testPlayThrowsExceptionForFloatZero(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ставка має бути більшою за нуль.");
        $game->play(0.0);
    }

    public function testPlayAcceptsOne(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 1", $game->play(1));
    }

    public function testPlayAcceptsLargePositiveBet(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 1000000", $game->play(1000000));
    }

    public function testPlayAcceptsMaximumInt(): void
    {
        $game = new Game();
        $max = PHP_INT_MAX;
        $this->assertEquals("Гра почалася зі ставкою $max", $game->play($max));
    }

    public function testPlayCoercesNumericStringToInteger(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 50", $game->play('50'));
    }

    public function testPlayCoercesFloatToInteger(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 10", $game->play(10.9));
    }

    public function testPlayIsConsistentForTheSameValue(): void
    {
        $game = new Game();
        $this->assertSame($game->play(123), $game->play(123));
    }

    public function testRouteReturnsNotFoundJsonForUnknownPath(): void
    {
        http_response_code(200);

        ob_start();
        ApiRouter::route('/missing-route');
        $output = trim((string) ob_get_clean());

        $this->assertSame('{"error":"Route not found"}', $output);
    }

    public function testRouteSets404StatusForUnknownPath(): void
    {
        http_response_code(200);
        ApiRouter::route('/another-missing-route');

        $this->assertSame(404, http_response_code());
    }

    public function testRouteReturnsNotFoundJsonForEmptyPath(): void
    {
        http_response_code(200);

        ob_start();
        ApiRouter::route('');
        $output = trim((string) ob_get_clean());

        $this->assertSame('{"error":"Route not found"}', $output);
    }
}
