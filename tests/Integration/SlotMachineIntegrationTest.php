<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\SlotMachine;
use App\Game;

class SlotMachineIntegrationTest extends TestCase
{
    private function createSqlitePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'win_casino_test_' . uniqid() . '.sqlite';
        return $path;
    }

    private function createPdo(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function initSchema(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT, balance REAL)');
    }

    private function seedUser(\PDO $pdo, int $id, float $balance, string $username = 'tester'): void
    {
        $stmt = $pdo->prepare('INSERT INTO users (id, username, balance) VALUES (?, ?, ?)');
        $stmt->execute([$id, $username, $balance]);
    }

    public function testSpinUpdatesBalanceNoWin()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 1, 100.0);

        $selector = function (array $symbols) {
            return ['🍒','🍋','🔔'];
        };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin(1, 5);

        $this->assertTrue($res['success']);
        $this->assertEquals(0.0, $res['win']);
        $this->assertEquals(95.0, $res['new_balance']);

        // verify persisted in DB
        $pdo2 = $this->createPdo($path);
        $row = $pdo2->query('SELECT balance FROM users WHERE id = 1')->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(95.0, (float)$row['balance']);

        // cleanup
        @unlink($path);
    }

    public function testSpinTriple7Wins50x()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 2, 20.0);

        $selector = function (array $symbols) { return ['7️⃣','7️⃣','7️⃣']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin(2, 2);

        $this->assertTrue($res['success']);
        $this->assertEquals(100.0, $res['win']); // 2 * 50
        $this->assertEquals(118.0, $res['new_balance']);

        @unlink($path);
    }

    public function testSpinTripleDiamondWins25x()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 3, 30.0);

        $selector = function (array $symbols) { return ['💎','💎','💎']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin(3, 3);

        $this->assertTrue($res['success']);
        $this->assertEquals(75.0, $res['win']); // 3 * 25
        $this->assertEquals(102.0, $res['new_balance']);

        @unlink($path);
    }

    public function testSpinPairWins2x()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 4, 40.0);

        $selector = function (array $symbols) { return ['🍒','🍒','🔔']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin(4, 4);

        $this->assertTrue($res['success']);
        $this->assertEquals(8.0, $res['win']); // 4 * 2
        $this->assertEquals(44.0, $res['new_balance']);

        @unlink($path);
    }

    public function testSpinInsufficientFundsThrowsAndBalanceUnchanged()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 5, 10.0);

        $selector = function (array $symbols) { return ['🍒','🍋','🔔']; };

        $machine = new SlotMachine($pdo, $selector);

        $this->expectException(\RuntimeException::class);
        try {
            $machine->spin(5, 50);
        } finally {
            // balance must remain unchanged
            $row = $pdo->query('SELECT balance FROM users WHERE id = 5')->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals(10.0, (float)$row['balance']);
            @unlink($path);
        }
    }

    public function testTransactionRollbackOnUpdateFailure()
    {
        $path = $this->createSqlitePath();
        $pdoReal = $this->createPdo($path);
        $this->initSchema($pdoReal);
        $this->seedUser($pdoReal, 6, 10.0);

        // Create TestPDO which will throw on UPDATE execute
        $pdo = new class('sqlite:' . $path) extends \PDO {
            public function prepare($statement, $options = null)
            {
                $stmt = parent::prepare($statement, $options ?? []);
                if (stripos($statement, 'update') !== false) {
                    // wrap execute to throw
                    return new class($stmt) extends \PDOStatement {
                        private $inner;
                        protected function __construct($inner) { $this->inner = $inner; }
                        public function execute($input_parameters = null)
                        {
                            throw new \Exception('Simulated update failure');
                        }
                    };
                }
                return $stmt;
            }
        };

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $selector = function (array $symbols) { return ['🍒','🍋','🔔']; };

        $machine = new SlotMachine($pdo, $selector);

        $this->expectException(\RuntimeException::class);
        try {
            $machine->spin(6, 1);
        } finally {
            // balance must remain unchanged
            $row = $pdoReal->query('SELECT balance FROM users WHERE id = 6')->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals(10.0, (float)$row['balance']);
            @unlink($path);
        }
    }

    public function testMultipleSpinsAccumulateBalance()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 7, 50.0);

        $selector1 = function (array $symbols) { return ['🍒','🍋','🔔']; };
        $selector2 = function (array $symbols) { return ['🍒','🍒','🍒']; };

        $machine1 = new SlotMachine($pdo, $selector1);
        $res1 = $machine1->spin(7, 5);
        $this->assertEquals(45.0, $res1['new_balance']);

        $machine2 = new SlotMachine($pdo, $selector2);
        $res2 = $machine2->spin(7, 5);
        // three identical non-special symbol => 10x
        $this->assertEquals(50.0, $res2['win']); // 5*10
        $this->assertEquals(90.0, $res2['new_balance']);

        @unlink($path);
    }

    public function testSelectorProvidedIsUsed()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 8, 100.0);

        $selector = function (array $symbols) { return ['X','Y','Z']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin(8, 10);

        $this->assertEquals(['X','Y','Z'], $res['result']);

        @unlink($path);
    }

    public function testInvalidBetThrowsInvalidArgument()
    {
        $path = $this->createSqlitePath();
        $pdo = $this->createPdo($path);
        $this->initSchema($pdo);
        $this->seedUser($pdo, 9, 20.0);

        $machine = new SlotMachine($pdo, function() { return ['🍒','🍋','🔔']; });

        $this->expectException(\InvalidArgumentException::class);
        $machine->spin(9, 0);

        @unlink($path);
    }

    public function testGamePlayIntegration()
    {
        $game = new Game();
        $this->assertStringContainsString('Гра почалася', $game->play(1));
    }
}
