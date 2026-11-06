<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Http\Json;
use App\Security\Auth;
use App\Service\GameService;
use App\Support\Database;

Json::requireMethod('GET');
$userId = Auth::requireUserId();
Json::send(200, (new GameService(Database::pdo()))->rocketState($userId));
