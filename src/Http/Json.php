<?php
declare(strict_types=1);

namespace App\Http;

final class Json
{
    private const MAX_BODY_BYTES = 10240;
    private static ?array $body = null;

    public static function send(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $code, string $message, array $details = []): never
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        self::send($status, ['success' => false, 'error' => $error]);
    }

    public static function requireMethod(string ...$allowed): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            throw new HttpException(405, 'method_not_allowed', 'Method not allowed');
        }
    }

    /** Безпечне читання JSON-тіла: ліміт розміру, перевірка Content-Type, строгий decode. */
    public static function body(): array
    {
        if (self::$body !== null) {
            return self::$body;
        }
        $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_starts_with($type, 'application/json')) {
            throw new HttpException(415, 'unsupported_media_type', 'Content-Type must be application/json');
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'payload_too_large', 'Request body too large');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'invalid_json', 'Request body must be a valid JSON object');
        }
        return self::$body = $data;
    }
}
