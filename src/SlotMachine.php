<?php
namespace App;

class SlotMachine
{
    private \PDO $pdo;
    private array $symbols;
    /**
     * @var callable|null function(array $symbols): array of 3 symbols
     */
    private $symbolSelector;

    public function __construct(\PDO $pdo, callable $symbolSelector = null)
    {
        $this->pdo = $pdo;
        $this->symbols = ['🍒', '🍋', '🔔', '💎', '7️⃣', '🍉', '🍇', '🍀'];
        $this->symbolSelector = $symbolSelector ?? function (array $symbols) {
            return [
                $symbols[array_rand($symbols)],
                $symbols[array_rand($symbols)],
                $symbols[array_rand($symbols)],
            ];
        };
    }

    /**
     * Spin the slot machine for a user.
     *
     * @param int $userId
     * @param float|int $bet
     * @return array ['success' => bool, 'result' => array, 'win' => float, 'new_balance' => float]
     * @throws \InvalidArgumentException for invalid bet
     * @throws \RuntimeException for DB or transaction errors or insufficient funds
     */
    public function spin(int $userId, $bet): array
    {
        $betAmount = (float) $bet;
        if ($betAmount <= 0) {
            throw new \InvalidArgumentException('Ставка має бути більшою за нуль.');
        }

        try {
            $this->pdo->beginTransaction();

            $select = $this->pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $select->execute([$userId]);
            $userRow = $select->fetch(\PDO::FETCH_ASSOC);

            if (!$userRow || $userRow['balance'] < $betAmount) {
                $this->pdo->rollBack();
                throw new \RuntimeException('Недостатньо коштів');
            }

            $currentBalance = (float) $userRow['balance'];

            $selector = $this->symbolSelector;
            $result = $selector($this->symbols);

            $winAmount = 0.0;
            [$slot1, $slot2, $slot3] = $result;

            if ($slot1 === $slot2 && $slot2 === $slot3) {
                if ($slot1 === '7️⃣') $winAmount = $betAmount * 50;
                elseif ($slot1 === '💎') $winAmount = $betAmount * 25;
                else $winAmount = $betAmount * 10;
            } elseif ($slot1 === $slot2 || $slot2 === $slot3 || $slot1 === $slot3) {
                $winAmount = $betAmount * 2;
            }

            $newBalance = $currentBalance - $betAmount + $winAmount;

            $update = $this->pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
            $update->execute([$newBalance, $userId]);

            $this->pdo->commit();

            return [
                'success' => true,
                'result' => $result,
                'win' => $winAmount,
                'new_balance' => $newBalance,
            ];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                try { $this->pdo->rollBack(); } catch (\Throwable $_) {}
            }
            throw new \RuntimeException('Помилка транзакції: ' . $e->getMessage(), 0, $e);
        }
    }
}
