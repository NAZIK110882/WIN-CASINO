<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Http\Json;
use App\Security\Auth;
use App\Security\Validator;
use App\Service\UserService;

/**
 * Обгортка: автентифікація + валідація + rate limit (в bootstrap) -> стара ігрова логіка.
 * Стара логіка бере user id з $GLOBALS['AUTH_USER_ID'] і ставку з $GLOBALS['VALIDATED_BET'].
 */
Json::requireMethod('POST');
$userId = Auth::requireUserId();
$bet = Validator::amount(Json::body()['bet'] ?? null, 'bet', 1, 1000);

$GLOBALS['AUTH_USER_ID'] = $userId;
$GLOBALS['VALIDATED_BET'] = $bet;
register_shutdown_function(static fn () => UserService::forget($userId)); // баланс змінився -> скинути кеш

require __DIR__ . '/../bonanza.php';
