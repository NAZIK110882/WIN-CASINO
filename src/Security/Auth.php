<?php
declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;
use App\Support\Env;

/**
 * Stateless-автентифікація через підписаний токен (JWT HS256) в заголовку
 * Authorization: Bearer <token>. Працює і з окремим origin фронтенду (без cookie).
 * Ідентифікатор користувача береться ЛИШЕ з токена, ніколи з тіла/параметрів запиту.
 */
final class Auth
{
    public static function issueToken(int $userId, string $role = 'player'): string
    {
        $now = time();
        return self::encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + Env::int('JWT_TTL', 3600),
        ]);
    }

    /** @return array{sub:int,role:string,exp:int}|null */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$h.$p", self::secret(), true));
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $header = json_decode(self::unb64($h), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }
        $payload = json_decode(self::unb64($p), true);
        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time() || !isset($payload['sub'])) {
            return null;
        }
        return $payload;
    }

    /** @return array{sub:int,role:string,exp:int} */
    public static function requireClaims(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            header('WWW-Authenticate: Bearer');
            throw new HttpException(401, 'unauthorized', 'Missing or malformed Authorization header');
        }
        $claims = self::verifyToken($m[1]);
        if ($claims === null) {
            header('WWW-Authenticate: Bearer error="invalid_token"');
            throw new HttpException(401, 'invalid_token', 'Invalid or expired token');
        }
        return $claims;
    }

    public static function requireUserId(): int
    {
        return (int) self::requireClaims()['sub'];
    }

    /** Авторизація за роллю: 403, якщо роль не збігається. */
    public static function requireRole(string $role): int
    {
        $claims = self::requireClaims();
        if (($claims['role'] ?? '') !== $role) {
            throw new HttpException(403, 'forbidden', 'Insufficient permissions');
        }
        return (int) $claims['sub'];
    }

    private static function encode(array $payload): string
    {
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $p = self::b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $s = self::b64(hash_hmac('sha256', "$h.$p", self::secret(), true));
        return "$h.$p.$s";
    }

    private static function secret(): string
    {
        $secret = Env::required('JWT_SECRET');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 characters');
        }
        return $secret;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
