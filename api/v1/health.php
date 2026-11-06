<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Cache\CacheFactory;
use App\Http\Json;
use App\Support\Database;

Json::requireMethod('GET');

$cache = CacheFactory::make();
$dbOk = true;
try {
    Database::pdo()->query('SELECT 1');
} catch (Throwable) {
    $dbOk = false;
}

Json::send($dbOk ? 200 : 503, [
    'status' => $dbOk ? 'ok' : 'degraded',
    'database' => $dbOk ? 'up' : 'down',
    'cache_driver' => CacheFactory::driverName(),
    'metrics' => [ // для демонстрації ефекту кешування (завдання 10)
        'cache_hit' => (int) $cache->get('metrics:cache_hit'),
        'cache_miss' => (int) $cache->get('metrics:cache_miss'),
        'db_user_queries' => (int) $cache->get('metrics:db_user_queries'),
        'db_leaderboard_queries' => (int) $cache->get('metrics:db_leaderboard_queries'),
        'db_history_queries' => (int) $cache->get('metrics:db_history_queries'),
    ],
]);
