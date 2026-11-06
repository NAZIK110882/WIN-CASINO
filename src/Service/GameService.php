<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\HttpException;
use PDO;
use Throwable;

/** Transactional game operations that mutate a user's balance. */
final class GameService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{success: bool, grid: list<string>, win: float, new_balance: float, winning_symbols: list<string>, bonus_game: bool} */
    public function playBonanza(int $userId, float $bet): array
    {
        $this->validateBet($bet);
        $this->pdo->beginTransaction();
        try {
            $balance = $this->lockedBalance($userId);
            if ($balance < $bet) {
                throw new HttpException(422, 'insufficient_funds', 'Insufficient balance');
            }

            $pool = [
                '🍎', '🍎', '🍎', '🍎', '🍇', '🍇', '🍇', '🍇', '🍉', '🍉', '🍉',
                '🍌', '🍌', '🍌', '🫐', '🫐', '🍬', '🍬', '🍭',
            ];
            $grid = [];
            $counts = [];
            for ($i = 0; $i < 25; $i++) {
                $symbol = $pool[random_int(0, count($pool) - 1)];
                $grid[] = $symbol;
                $counts[$symbol] = ($counts[$symbol] ?? 0) + 1;
            }

            $win = 0.0;
            $winningSymbols = [];
            foreach ($counts as $symbol => $count) {
                if ($symbol !== '🍭' && $count >= 8) {
                    $multiplier = ($count - 7) * 0.5;
                    if ($symbol === '🍬') {
                        $multiplier *= 3;
                    }
                    $win += $bet * $multiplier;
                    $winningSymbols[] = $symbol;
                }
            }

            $bonus = ($counts['🍭'] ?? 0) >= 3;
            if ($bonus) {
                $win += $bet * 50;
                $winningSymbols[] = '🍭';
            }

            $newBalance = round($balance - $bet + $win, 2);
            $this->saveBalance($userId, $newBalance);
            $this->recordRound($userId, 'bonanza', $bet, $win);
            $this->pdo->commit();

            return [
                'success' => true,
                'grid' => $grid,
                'win' => round($win, 2),
                'new_balance' => $newBalance,
                'winning_symbols' => $winningSymbols,
                'bonus_game' => $bonus,
            ];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** Start a crash round. The crash point stays on the server. */
    public function startRocket(int $userId, float $bet): array
    {
        $this->validateBet($bet);
        $this->pdo->beginTransaction();
        try {
            $balance = $this->lockedBalance($userId);
            if ($balance < $bet) {
                throw new HttpException(422, 'insufficient_funds', 'Insufficient balance');
            }

            // An uncollected prior round is considered crashed before a new round starts.
            $expire = $this->pdo->prepare(
                "SELECT id, bet FROM rocket_rounds WHERE user_id = ? AND status = 'active'" .
                ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '')
            );
            $expire->execute([$userId]);
            foreach ($expire->fetchAll(PDO::FETCH_ASSOC) as $previousRound) {
                $this->recordRound($userId, 'rocket', (float) $previousRound['bet'], 0.0);
            }
            $this->pdo->prepare(
                "UPDATE rocket_rounds SET status = 'crashed', settled_at = CURRENT_TIMESTAMP
                 WHERE user_id = ? AND status = 'active'"
            )->execute([$userId]);

            $newBalance = round($balance - $bet, 2);
            $this->saveBalance($userId, $newBalance);
            $crashPoint = random_int(130, 350) / 100;
            $pgsql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            $insert = $this->pdo->prepare(
                "INSERT INTO rocket_rounds (user_id, bet, crash_point, started_at, status)
                 VALUES (?, ?, ?, ?, 'active')" . ($pgsql ? ' RETURNING id' : '')
            );
            $insert->execute([$userId, $bet, $crashPoint, microtime(true)]);
            $roundId = (int) ($pgsql ? $insert->fetchColumn() : $this->pdo->lastInsertId());
            $this->pdo->commit();

            return ['success' => true, 'round_id' => $roundId, 'new_balance' => $newBalance];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** Current crash-round state without revealing its future crash point. */
    public function rocketState(int $userId): array
    {
        $this->pdo->beginTransaction();
        try {
            // Keep the same lock order as startRocket (user first, then round) to avoid deadlocks.
            $balance = $this->lockedBalance($userId);
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare(
                "SELECT id, bet, crash_point, started_at FROM rocket_rounds
                 WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1" . $lock
            );
            $statement->execute([$userId]);
            $round = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$round) {
                $this->pdo->commit();
                return ['active' => false];
            }

            $multiplier = round(1 + max(0.0, microtime(true) - (float) $round['started_at']) * 0.75, 2);
            if ($multiplier >= (float) $round['crash_point']) {
                $this->pdo->prepare(
                    "UPDATE rocket_rounds SET status = 'crashed', settled_at = CURRENT_TIMESTAMP WHERE id = ?"
                )->execute([(int) $round['id']]);
                $this->recordRound($userId, 'rocket', (float) $round['bet'], 0.0);
                $this->pdo->commit();
                return ['active' => false, 'crashed' => true, 'multiplier' => (float) $round['crash_point']];
            }

            $this->pdo->commit();
            return [
                'active' => true,
                'round_id' => (int) $round['id'],
                'multiplier' => $multiplier,
            ];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    /** @return array{success: bool, win: float, new_balance: float} */
    public function cashOutRocket(int $userId, int $roundId): array
    {
        if ($roundId < 1) {
            throw new HttpException(422, 'invalid_round', 'Invalid round');
        }

        $this->pdo->beginTransaction();
        try {
            $balance = $this->lockedBalance($userId);
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare(
                "SELECT id, bet, crash_point, started_at FROM rocket_rounds
                 WHERE id = ? AND user_id = ? AND status = 'active'" . $lock
            );
            $statement->execute([$roundId, $userId]);
            $round = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$round) {
                throw new HttpException(404, 'round_not_found', 'Active round not found');
            }

            $elapsed = max(0.0, microtime(true) - (float) $round['started_at']);
            $multiplier = round(1 + $elapsed * 0.75, 2);
            if ($multiplier >= (float) $round['crash_point']) {
                $crash = $this->pdo->prepare(
                    "UPDATE rocket_rounds SET status = 'crashed', settled_at = CURRENT_TIMESTAMP WHERE id = ?"
                );
                $crash->execute([$roundId]);
                $this->recordRound($userId, 'rocket', (float) $round['bet'], 0.0);
                $this->pdo->commit();
                throw new HttpException(409, 'round_crashed', 'The rocket has already crashed');
            }

            $bet = (float) $round['bet'];
            $win = round($bet * $multiplier, 2);
            $newBalance = round($balance + $win, 2);
            $this->saveBalance($userId, $newBalance);
            $settle = $this->pdo->prepare(
                "UPDATE rocket_rounds SET status = 'cashed_out', cashout_multiplier = ?, settled_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'active'"
            );
            $settle->execute([$multiplier, $roundId]);
            if ($settle->rowCount() !== 1) {
                throw new HttpException(409, 'round_already_settled', 'Round is already settled');
            }
            $this->recordRound($userId, 'rocket', $bet, $win);
            $this->pdo->commit();

            return ['success' => true, 'win' => $win, 'new_balance' => $newBalance];
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    private function validateBet(float $bet): void
    {
        if (!is_finite($bet) || $bet < 1 || $bet > 1000) {
            throw new HttpException(422, 'invalid_bet', 'Bet must be between 1 and 1000');
        }
    }

    private function lockedBalance(int $userId): float
    {
        $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare('SELECT balance FROM users WHERE id = ?' . $lock);
        $statement->execute([$userId]);
        $balance = $statement->fetchColumn();
        if ($balance === false) {
            throw new HttpException(404, 'user_not_found', 'User not found');
        }
        return (float) $balance;
    }

    private function saveBalance(int $userId, float $balance): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
        $statement->execute([$balance, $userId]);
    }

    private function recordRound(int $userId, string $game, float $bet, float $win): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO game_rounds (user_id, game, bet, win) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$userId, $game, $bet, $win]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
