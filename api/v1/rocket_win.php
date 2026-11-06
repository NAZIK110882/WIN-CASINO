<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Http\Json;
use App\Security\Auth;
use App\Security\Validator;
use App\Service\GameService;
use App\Service\UserService;
use App\Support\Database;

Json::requireMethod('POST');
$userId = Auth::requireUserId();
$body = Json::body();
$roundId = Validator::positiveInt($body['round_id'] ?? null, 'round_id');

$result = (new GameService(Database::pdo()))->cashOutRocket($userId, $roundId);
UserService::forget($userId);
Json::send(200, $result);
