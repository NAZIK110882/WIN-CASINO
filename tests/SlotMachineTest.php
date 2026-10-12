<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\SlotMachine;

class SlotMachineTest extends TestCase
{
    private function createPdoAndStmts()
    {
        $pdo = $this->getMockBuilder(\PDO::class)
            ->disableOriginalConstructor()
            ->getMock();

        $selectStmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $updateStmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();

        return [$pdo, $selectStmt, $updateStmt];
    }

    public function testSpin_noWin_updatesBalance_andCommits()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 1;
        $bet = 5.0;
        $currentBalance = 100.0;

        // beginTransaction, commit should be called
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        // prepare returns selectStmt for SELECT, updateStmt for UPDATE
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (stripos($sql, 'select') === 0) return $selectStmt;
            return $updateStmt;
        });

        $selectStmt->expects($this->once())->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $updateStmt->expects($this->once())->method('execute')->with($this->callback(function ($args) use ($currentBalance, $bet) {
            // new balance = current - bet + 0
            return count($args) === 2 && $args[0] === ($currentBalance - $bet) && $args[1] === 1;
        }));

        $pdo->method('inTransaction')->willReturn(false);

        // Force symbol selector to return three distinct symbols (no win)
        $selector = function (array $symbols) {
            return ['🍒', '🍋', '🔔'];
        };

        $machine = new SlotMachine($pdo, $selector);

        $res = $machine->spin($userId, $bet);

        $this->assertTrue($res['success']);
        $this->assertEquals(0.0, $res['win']);
        $this->assertEquals($currentBalance - $bet, $res['new_balance']);
    }

    public function testSpin_triple7_wins50x()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 2;
        $bet = 2.0;
        $currentBalance = 20.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (stripos($sql, 'select') === 0) return $selectStmt;
            return $updateStmt;
        });

        $selectStmt->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $expectedWin = $bet * 50;
        $updateStmt->expects($this->once())->method('execute')->with([$this->equalTo($currentBalance - $bet + $expectedWin), $userId]);

        $pdo->method('inTransaction')->willReturn(false);

        $selector = function () { return ['7️⃣','7️⃣','7️⃣']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin($userId, $bet);

        $this->assertTrue($res['success']);
        $this->assertEquals($expectedWin, $res['win']);
    }

    public function testSpin_tripleDiamond_wins25x()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 3;
        $bet = 3.0;
        $currentBalance = 30.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (stripos($sql, 'select') === 0) return $selectStmt;
            return $updateStmt;
        });

        $selectStmt->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $expectedWin = $bet * 25;
        $updateStmt->expects($this->once())->method('execute')->with([$this->equalTo($currentBalance - $bet + $expectedWin), $userId]);

        $pdo->method('inTransaction')->willReturn(false);

        $selector = function () { return ['💎','💎','💎']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin($userId, $bet);

        $this->assertTrue($res['success']);
        $this->assertEquals($expectedWin, $res['win']);
    }

    public function testSpin_pair_wins2x()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 4;
        $bet = 4.0;
        $currentBalance = 40.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (stripos($sql, 'select') === 0) return $selectStmt;
            return $updateStmt;
        });

        $selectStmt->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $expectedWin = $bet * 2;
        $updateStmt->expects($this->once())->method('execute')->with([$this->equalTo($currentBalance - $bet + $expectedWin), $userId]);

        $pdo->method('inTransaction')->willReturn(false);

        $selector = function () { return ['🍒','🍒','🔔']; };

        $machine = new SlotMachine($pdo, $selector);
        $res = $machine->spin($userId, $bet);

        $this->assertTrue($res['success']);
        $this->assertEquals($expectedWin, $res['win']);
    }

    public function testSpin_insufficientFunds_throwsRuntimeAndRollsBack()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 5;
        $bet = 50.0;
        $currentBalance = 10.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $pdo->method('prepare')->willReturn($selectStmt);
        $selectStmt->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $pdo->method('inTransaction')->willReturn(true);

        $selector = function () { return ['🍒','🍋','🔔']; };

        $machine = new SlotMachine($pdo, $selector);

        $this->expectException(\RuntimeException::class);
        $machine->spin($userId, $bet);
    }

    public function testSpin_prepareThrows_exceptionIsWrapped_andRollbackAttempted()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 6;
        $bet = 1.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $pdo->method('prepare')->will($this->throwException(new \Exception('DB error')));
        $pdo->method('inTransaction')->willReturn(true);

        $selector = function () { return ['🍒','🍋','🔔']; };

        $machine = new SlotMachine($pdo, $selector);

        $this->expectException(\RuntimeException::class);
        $machine->spin($userId, $bet);
    }

    public function testSpin_updateThrows_exceptionIsWrapped_andRollbackAttempted()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $userId = 7;
        $bet = 2.0;
        $currentBalance = 10.0;

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (stripos($sql, 'select') === 0) return $selectStmt;
            return $updateStmt;
        });

        $selectStmt->method('execute')->with([$userId]);
        $selectStmt->method('fetch')->willReturn(['balance' => $currentBalance]);

        $updateStmt->method('execute')->will($this->throwException(new \Exception('Update failed')));

        $pdo->method('inTransaction')->willReturn(true);

        $selector = function () { return ['🍒','🍋','🔔']; };

        $machine = new SlotMachine($pdo, $selector);

        $this->expectException(\RuntimeException::class);
        $machine->spin($userId, $bet);
    }

    public function testSpin_invalidBet_throwsInvalidArgument()
    {
        [$pdo, $selectStmt, $updateStmt] = $this->createPdoAndStmts();

        $machine = new SlotMachine($pdo, function () { return ['🍒','🍋','🔔']; });

        $this->expectException(\InvalidArgumentException::class);
        $machine->spin(1, 0);
    }
}
