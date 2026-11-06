<?php
declare(strict_types=1);

namespace App\Security;

final class Validator
{
    /** Грошова сума: додатне число до 2 знаків після коми в межах [min, max]. */
    public static function amount(mixed $value, string $field, float $min, float $max): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new ValidationException([$field => 'must be a number']);
        }
        if (is_string($value) && !preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new ValidationException([$field => 'must be a positive number with up to 2 decimals']);
        }
        $n = (float) $value;
        if (!is_finite($n) || $n < $min || $n > $max) {
            throw new ValidationException([$field => "must be between $min and $max"]);
        }
        if (abs($n * 100 - round($n * 100)) > 1e-9) {
            throw new ValidationException([$field => 'must have at most 2 decimals']);
        }
        return round($n, 2);
    }

    public static function string(mixed $value, string $field, int $min, int $max, ?string $pattern = null): string
    {
        if (!is_string($value)) {
            throw new ValidationException([$field => 'must be a string']);
        }
        $len = mb_strlen($value);
        if ($len < $min || $len > $max) {
            throw new ValidationException([$field => "length must be between $min and $max"]);
        }
        if ($pattern !== null && !preg_match($pattern, $value)) {
            throw new ValidationException([$field => 'has invalid format']);
        }
        return $value;
    }

    public static function positiveInt(mixed $value, string $field, int $min = 1, int $max = PHP_INT_MAX): int
    {
        if (is_string($value) && preg_match('/^\d+$/', $value)) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new ValidationException([$field => "must be an integer between $min and $max"]);
        }
        return $value;
    }
}
