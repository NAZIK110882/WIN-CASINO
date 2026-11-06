<?php
declare(strict_types=1);

namespace App\Resilience;

final class CircuitOpenException extends \RuntimeException
{
    public function __construct(string $name, public readonly int $retryAfter)
    {
        parent::__construct("Circuit '$name' is open");
    }
}
