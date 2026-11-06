<?php
declare(strict_types=1);

use App\Security\ValidationException;
use App\Security\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidAmounts(): void
    {
        $this->assertSame(50.0, Validator::amount(50, 'bet', 1, 1000));
        $this->assertSame(10.5, Validator::amount('10.50', 'bet', 1, 1000));
        $this->assertSame(0.5, Validator::amount(0.5, 'bet', 0.5, 10));
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmounts(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        Validator::amount($value, 'bet', 1, 1000);
    }

    public static function invalidAmounts(): array
    {
        return [[-5], [0], [1001], ['abc'], [null], [true], [[1]], ['1e3'], [10.123], ['10.123'], [INF], [NAN]];
    }

    public function testStringRules(): void
    {
        $this->assertSame('player_1', Validator::string('player_1', 'username', 3, 50, '/^[A-Za-z0-9_.-]+$/'));
        $this->expectException(ValidationException::class);
        Validator::string("bad name'; DROP TABLE users;--", 'username', 3, 50, '/^[A-Za-z0-9_.-]+$/');
    }

    public function testPositiveInt(): void
    {
        $this->assertSame(10, Validator::positiveInt('10', 'limit', 1, 100));
        $this->expectException(ValidationException::class);
        Validator::positiveInt('101', 'limit', 1, 100);
    }
}
