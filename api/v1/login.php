<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Cache\CacheFactory;
use App\Http\HttpException;
use App\Http\Json;
use App\Security\Auth;
use App\Security\RateLimiter;
use App\Security\Validator;
use App\Support\Database;
use App\Support\Env;

Json::requireMethod('POST');

// Жорсткіший ліміт на логін — захист від підбору паролів
$attempt = (new RateLimiter(CacheFactory::make(), Env::int('LOGIN_RATE_MAX', 5), 60))
    ->hit('login:' . RateLimiter::clientKey());
if (!$attempt['allowed']) {
    header('Retry-After: ' . max(1, $attempt['reset'] - time()));
    throw new HttpException(429, 'too_many_attempts', 'Too many login attempts');
}

$body = Json::body();
$username = Validator::string($body['username'] ?? null, 'username', 3, 50, '/^[A-Za-z0-9_.-]+$/');
$password = Validator::string($body['password'] ?? null, 'password', 8, 128);

$st = Database::pdo()->prepare('SELECT id, password_hash, role FROM users WHERE username = :u');
$st->execute(['u' => $username]);
$user = $st->fetch();

if (!$user) {
    password_hash($password, PASSWORD_DEFAULT); // вирівнюємо час відповіді (user enumeration)
    throw new HttpException(401, 'invalid_credentials', 'Invalid username or password');
}
if (!password_verify($password, $user['password_hash'])) {
    throw new HttpException(401, 'invalid_credentials', 'Invalid username or password');
}

Json::send(200, [
    'success' => true,
    'token' => Auth::issueToken((int) $user['id'], $user['role']),
    'token_type' => 'Bearer',
    'expires_in' => Env::int('JWT_TTL', 3600),
]);
