<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Http\HttpException;
use App\Service\GameService;
use PDO;
use PHPUnit\Framework\TestCase;

final class GameServiceIntegrationTest extends TestCase
{
    private PDO $pdo;
    private GameService $games;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, balance REAL NOT NULL)');
        $this->pdo->exec(
            'CREATE TABLE game_rounds (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, game TEXT, bet REAL, win REAL)'
        );
        $this->pdo->exec(
            "CREATE TABLE rocket_rounds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bet REAL NOT NULL,
                crash_point REAL NOT NULL,
                started_at REAL NOT NULL,
                cashout_multiplier REAL,
                status TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                settled_at TEXT
            )"
        );
        $this->pdo->exec('INSERT INTO users (id, balance) VALUES (1, 100)');
        $this->games = new GameService($this->pdo);
    }

    public function testBonanzaPersistsBalanceAndRoundHistory(): void
    {
        $result = $this->games->playBonanza(1, 10.0);

        self::assertCount(25, $result['grid']);
        self::assertEqualsWithDelta(90.0 + $result['win'], $result['new_balance'], 0.001);
        self::assertSame($result['new_balance'], (float) $this->pdo->query('SELECT balance FROM users WHERE id = 1')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn());
    }

    public function testRocketCashOutUsesServerElapsedTimeAndPersistsPayout(): void
    {
        $round = $this->games->startRocket(1, 10.0);
        $update = $this->pdo->prepare('UPDATE rocket_rounds SET started_at = ?, crash_point = 3.50 WHERE id = ?');
        $update->execute([microtime(true) - 1, $round['round_id']]);

        $result = $this->games->cashOutRocket(1, $round['round_id']);

        self::assertGreaterThanOrEqual(17.5, $result['win']);
        self::assertLessThan(18.0, $result['win']);
        self::assertSame('cashed_out', $this->pdo->query('SELECT status FROM rocket_rounds')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn());
        self::assertSame($result['new_balance'], (float) $this->pdo->query('SELECT balance FROM users WHERE id = 1')->fetchColumn());
    }

    public function testRocketCrashSettlesRoundWithoutRefundingBet(): void
    {
        $round = $this->games->startRocket(1, 10.0);
        $update = $this->pdo->prepare('UPDATE rocket_rounds SET started_at = ?, crash_point = 1.30 WHERE id = ?');
        $update->execute([microtime(true) - 10, $round['round_id']]);

        try {
            $this->games->cashOutRocket(1, $round['round_id']);
            self::fail('Expected a crash response');
        } catch (HttpException $error) {
            self::assertSame(409, $error->status);
        }

        self::assertSame('crashed', $this->pdo->query('SELECT status FROM rocket_rounds')->fetchColumn());
        self::assertSame(90.0, (float) $this->pdo->query('SELECT balance FROM users WHERE id = 1')->fetchColumn());
        self::assertSame(0.0, (float) $this->pdo->query('SELECT win FROM game_rounds')->fetchColumn());
    }
}
