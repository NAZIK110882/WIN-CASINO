<?php
declare(strict_types=1);

define('API_VERSION', 'v1');
require __DIR__ . '/../bootstrap.php';

use App\Http\Json;
use App\Security\Auth;
use App\Support\Database;

Json::requireMethod('GET');
Auth::requireRole('admin'); // авторизація: звичайний гравець отримає 403

$row = Database::pdo()->query('SELECT COUNT(*) AS users, COALESCE(SUM(balance),0) AS total_balance FROM users')->fetch();
Json::send(200, ['success' => true, 'users' => (int) $row['users'], 'total_balance' => (float) $row['total_balance']]);
