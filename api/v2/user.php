<?php
declare(strict_types=1);

define('API_VERSION', 'v2');
require __DIR__ . '/../bootstrap.php';

use App\Cache\CacheFactory;
use App\Http\HttpException;
use App\Http\Json;
use App\Security\Auth;
use App\Service\UserService;
use App\Support\Database;

Json::requireMethod('GET');
$userId = Auth::requireUserId();

$profile = (new UserService(Database::pdo(), CacheFactory::make()))->profile($userId);
if ($profile === null) {
    throw new HttpException(404, 'user_not_found', 'User not found');
}

// v2: новий конверт data/meta + більше полів. v1-клієнти не зачіпаються.
Json::send(200, [
    'data' => [
        'id' => $profile['id'],
        'username' => $profile['username'],
        'balance' => ['amount' => $profile['balance'], 'currency' => 'USD'],
    ],
    'meta' => ['version' => 'v2'],
]);
