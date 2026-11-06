<?php
declare(strict_types=1);

namespace App\Http;

use App\Resilience\CircuitOpenException;
use App\Security\ValidationException;

final class ExceptionHandler
{
    public static function register(bool $debug): void
    {
        set_exception_handler(static function (\Throwable $e) use ($debug): void {
            if ($e instanceof HttpException) {
                Json::error($e->status, $e->errorCode, $e->getMessage(), $e->details);
            }
            if ($e instanceof ValidationException) {
                Json::error(422, 'validation_failed', 'Invalid input', $e->errors);
            }
            if ($e instanceof CircuitOpenException) {
                header('Retry-After: ' . $e->retryAfter);
                Json::error(503, 'dependency_unavailable', 'Service temporarily unavailable');
            }

            error_log(sprintf('[api] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            if ($e instanceof \PDOException) {
                Json::error(503, 'database_unavailable', 'Database temporarily unavailable');
            }
            Json::error(500, 'internal_error', $debug ? $e->getMessage() : 'Internal server error');
        });
    }
}
