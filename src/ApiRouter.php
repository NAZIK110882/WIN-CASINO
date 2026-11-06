<?php
declare(strict_types=1);

namespace App;

/** Compatibility router for old callers; application traffic uses /api/v1/*. */
final class ApiRouter
{
    public static function route(string $path): void
    {
        $retiredRoutes = ['/user', '/spin', '/bonanza', '/rocket/start', '/rocket/win'];

        if (in_array($path, $retiredRoutes, true)) {
            http_response_code(410);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'endpoint_removed', 'message' => 'Use the versioned /api/v1 endpoint'],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}
