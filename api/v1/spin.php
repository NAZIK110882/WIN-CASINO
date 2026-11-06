<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Http\Json;
use App\Security\Auth;
use App\Security\Validator;
use App\SlotMachine;
use App\Support\Database;
use App\Service\UserService;

Json::requireMethod('POST');
$userId = Auth::requireUserId();
$bet = Validator::amount(Json::body()['bet'] ?? null, 'bet', 1, 1000);
$result = (new SlotMachine(Database::pdo()))->spin($userId, $bet);
UserService::forget($userId);
Json::send(200, $result + ['bet' => $bet]);
