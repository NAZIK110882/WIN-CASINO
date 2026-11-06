<?php
declare(strict_types=1);

/**
 * Спільна ініціалізація для всіх API-ендпоінтів:
 * .env -> обробка помилок -> security headers -> CORS -> versioning -> rate limit -> timeout.
 * Підключається першим рядком кожного api/vN/*.php (після define('API_VERSION', ...)).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Cache\CacheFactory;
use App\Http\Cors;
use App\Http\ExceptionHandler;
use App\Http\Json;
use App\Security\RateLimiter;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$appDebug = Env::get('APP_ENV', 'production') !== 'production';
ini_set('display_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);
ExceptionHandler::register($appDebug);

// Timeout виконання одного запиту
set_time_limit(Env::int('REQUEST_TIMEOUT', 10));

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// Версіювання API
$apiVersion = defined('API_VERSION') ? API_VERSION : 'v1';
header('X-API-Version: ' . $apiVersion);
$sunset = Env::get('API_V1_SUNSET'); // напр. "Wed, 31 Dec 2026 23:59:59 GMT"
if ($apiVersion === 'v1' && $sunset) {
    header('Deprecation: true');
    header('Sunset: ' . $sunset);
    header('Link: </api/v2/>; rel="successor-version"');
}

// CORS (+ швидка відповідь на preflight)
Cors::handle();

// Rate limiting: X запитів / Y секунд на клієнта
$rate = (new RateLimiter(
    CacheFactory::make(),
    Env::int('RATE_LIMIT_MAX', 60),
    Env::int('RATE_LIMIT_WINDOW', 60)
))->hit(RateLimiter::clientKey());

header('X-RateLimit-Limit: ' . $rate['limit']);
header('X-RateLimit-Remaining: ' . $rate['remaining']);
header('X-RateLimit-Reset: ' . $rate['reset']);

if (!$rate['allowed']) {
    header('Retry-After: ' . max(1, $rate['reset'] - time()));
    Json::error(429, 'rate_limited', 'Too many requests, slow down');
}
