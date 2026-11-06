<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
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

// v1: формат відповіді НЕ змінюється (зворотна сумісність зі старим фронтендом)
Json::send(200, ['success' => true, 'balance' => $profile['balance']]);
