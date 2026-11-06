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

$st = $pdo->prepare(
    'INSERT INTO users (username, password_hash, role) VALUES (:u, :h, :r)
     ON DUPLICATE KEY UPDATE password_hash = :h2, role = :r2'
);
foreach ($users as [$name, $plain, $role]) {
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $st->execute(['u' => $name, 'h' => $hash, 'r' => $role, 'h2' => $hash, 'r2' => $role]);
    echo "seeded $name ($role)\n";
}
