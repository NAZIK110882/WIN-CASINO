<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Cache\CacheFactory;
use App\Http\Json;
use App\Security\Auth;
use App\Security\Validator;
use App\Service\GameHistoryService;
use App\Support\Database;

Json::requireMethod('GET');
$userId = Auth::requireUserId(); // тільки власна історія (захист від IDOR)

$limit = isset($_GET['limit']) ? Validator::positiveInt($_GET['limit'], 'limit', 1, 100) : 20;
$before = isset($_GET['before']) ? Validator::positiveInt($_GET['before'], 'before') : null;

$rows = (new GameHistoryService(Database::pdo(), CacheFactory::make()))->recent($userId, $limit, $before);
Json::send(200, [
    'success' => true,
    'items' => $rows,
    'next_before' => $rows ? end($rows)['id'] : null,
]);
