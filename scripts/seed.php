<?php
declare(strict_types=1);

/** Створює тестових користувачів. Паролі беруться з env, не з коду.
 *  Запуск: docker compose exec backend php scripts/seed.php */

require __DIR__ . '/../vendor/autoload.php';

use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$pdo = Database::pdo();

$users = [
    ['player1', Env::required('SEED_PLAYER_PASSWORD'), 'player'],
    ['admin1', Env::required('SEED_ADMIN_PASSWORD'), 'admin'],
];

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$sql = $driver === 'pgsql'
    ? 'INSERT INTO users (username, password_hash, role) VALUES (:u, :h, :r)
       ON CONFLICT (username) DO UPDATE SET password_hash = EXCLUDED.password_hash, role = EXCLUDED.role'
    : 'INSERT INTO users (username, password_hash, role) VALUES (:u, :h, :r)
       ON DUPLICATE KEY UPDATE password_hash = :h2, role = :r2';
$st = $pdo->prepare($sql);
foreach ($users as [$name, $plain, $role]) {
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $params = ['u' => $name, 'h' => $hash, 'r' => $role];
    if ($driver === 'mysql') {
        $params += ['h2' => $hash, 'r2' => $role];
    }
    $st->execute($params);
    echo "seeded $name ($role)\n";
}
