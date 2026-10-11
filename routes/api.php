<?php
namespace App;

final class ApiRouter
{
    public static function route(string $path): void
    {
        $routes = [
            '/user' => __DIR__ . '/../api/user.php',
            '/spin' => __DIR__ . '/../api/spin.php',
            '/bonanza' => __DIR__ . '/../api/bonanza.php',
            '/rocket/start' => __DIR__ . '/../api/rocket_start.php',
            '/rocket/win' => __DIR__ . '/../api/rocket_win.php',
        ];

        $target = $routes[$path] ?? null;

        if ($target !== null) {
            require $target;
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}
