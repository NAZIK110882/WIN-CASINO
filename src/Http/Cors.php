<?php
declare(strict_types=1);

namespace App\Http;

use App\Support\Env;

/** CORS за allowlist-ом із CORS_ALLOWED_ORIGINS (через кому). Ніколи не віддає "*". */
final class Cors
{
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = array_filter(array_map('trim', explode(',', Env::get('CORS_ALLOWED_ORIGINS', ''))));

        if ($origin !== null) {
            header('Vary: Origin');
            if (in_array($origin, $allowed, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After, X-API-Version');
                header('Access-Control-Max-Age: 600');
            } elseif ($method === 'OPTIONS') {
                http_response_code(403);
                exit;
            }
        }

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
