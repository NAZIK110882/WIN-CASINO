<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$pdo = Database::pdo();

// Add tables/constraints/indexes without requiring users to drop their DB volume.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS game_rounds (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        game VARCHAR(20) NOT NULL,
        bet DECIMAL(10,2) NOT NULL,
        win DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_rounds_user FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT chk_round_bet CHECK (bet > 0),
        CONSTRAINT chk_round_win CHECK (win >= 0),
        INDEX idx_rounds_user_id (user_id, id),
        INDEX idx_rounds_created_at (created_at, user_id, bet, win)
    ) ENGINE=InnoDB"
);

// Older versions of game_rounds may predate these checks and indexes.
$ensureCheck = static function (string $name, string $expression) use ($pdo): void {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $statement->execute(['game_rounds', $name]);
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE game_rounds ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
$ensureCheck('chk_round_bet', 'bet > 0');
$ensureCheck('chk_round_win', 'win >= 0');

$ensureIndex = static function (string $name, string $columns) use ($pdo): void {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->execute(['game_rounds', $name]);
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec("CREATE INDEX {$name} ON game_rounds ({$columns})");
    }
};
$ensureIndex('idx_rounds_user_id', 'user_id, id');
$ensureIndex('idx_rounds_created_at', 'created_at, user_id, bet, win');

// Idempotent migration for databases created before crash rounds existed.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS rocket_rounds (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bet DECIMAL(10,2) NOT NULL,
        crash_point DECIMAL(5,2) NOT NULL,
        started_at DECIMAL(14,3) NOT NULL,
        cashout_multiplier DECIMAL(5,2) NULL,
        status ENUM('active','cashed_out','crashed') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        settled_at TIMESTAMP NULL DEFAULT NULL,
        CONSTRAINT fk_rocket_user FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT chk_rocket_bet CHECK (bet > 0),
        CONSTRAINT chk_rocket_crash CHECK (crash_point >= 1.30),
        INDEX idx_rocket_active_user (user_id, status, id)
    ) ENGINE=InnoDB"
);

echo "Database migrations are up to date.\n";
