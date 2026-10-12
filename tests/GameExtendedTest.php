<?php
use PHPUnit\Framework\TestCase;
use App\Game;
use App\ApiRouter;

class GameExtendedTest extends TestCase
{
    public function testPlayWithMinimumPositiveBet(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 1", $game->play(1));
    }

    public function testPlayWithLargeBet(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 1000000", $game->play(1000000));
    }

    public function testPlayWithPhpIntMax(): void
    {
        $game = new Game();
        $max = PHP_INT_MAX;
        $this->assertEquals("Гра почалася зі ставкою $max", $game->play($max));
    }

    public function testPlayWithNumericString(): void
    {
        $game = new Game();
        // string will be coerced to int
        $this->assertEquals("Гра почалася зі ставкою 50", $game->play('50'));
    }

    public function testPlayWithFloatBetGetsCastToInt(): void
    {
        $game = new Game();
        // float 10.9 is cast to int 10 by the type declaration coercion
        $this->assertEquals("Гра почалася зі ставкою 10", $game->play(10.9));
    }

    public function testPlayThrowsExceptionForNegativeBetExplicit(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ставка має бути більшою за нуль.');
        $game->play(-5);
    }

    public function testPlayThrowsExceptionForZeroString(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ставка має бути більшою за нуль.');
        $game->play('0');
    }

    public function testMultipleSequentialPlaysAreStateless(): void
    {
        $game = new Game();
        $this->assertEquals("Гра почалася зі ставкою 10", $game->play(10));
        $this->assertEquals("Гра почалася зі ставкою 20", $game->play(20));
        $this->assertEquals("Гра почалася зі ставкою 10", $game->play(10));
    }

    public function testPlayWithLeadingZeroString(): void
    {
        $game = new Game();
        // '0050' coerces to int 50
        $this->assertEquals("Гра почалася зі ставкою 50", $game->play('0050'));
    }

    public function testPlayWithVeryLargeNumericString(): void
    {
        $game = new Game();
        // Depending on platform this may be cast to int or float then to int; ensure positive
        $big = '100000000000000000';
        $result = $game->play($big);
        $this->assertStringStartsWith('Гра почалася зі ставкою', $result);
    }

    public function testPlayConsistencyForSameBet(): void
    {
        $game = new Game();
        $this->assertEquals($game->play(123), $game->play(123));
    }

    public function testRouteNotFoundOutputsJson(): void
    {
        // capture output
        ob_start();
        ApiRouter::route('/this-path-does-not-exist');
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertEquals('Route not found', $decoded['error']);
    }

    public function testRouteNotFoundSetsHttpResponseCode(): void
    {
        ApiRouter::route('/another-missing-path');
        $this->assertEquals(404, http_response_code());
    }

    public function testPlayThrowsExceptionForZeroExplicit(): void
    {
        $game = new Game();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ставка має бути більшою за нуль.');
        $game->play(0);
    }
}
