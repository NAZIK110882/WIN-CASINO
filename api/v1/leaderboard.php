<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Cache\CacheFactory;
use App\Http\Json;
use App\Security\Auth;
use App\Service\GameHistoryService;
use App\Support\Database;

Json::requireMethod('GET');
Auth::requireUserId();

Json::send(200, [
    'success' => true,
    'items' => (new GameHistoryService(Database::pdo(), CacheFactory::make()))->leaderboard(),
]);
