<?php
declare(strict_types=1);

namespace App\Service;

use App\Cache\CacheInterface;
use App\Cache\Remember;
use App\Support\Env;

final class GameHistoryService
{
    public function __construct(private \PDO $pdo, private CacheInterface $cache)
    {
    }

    /**
     * Keyset-пагінація: WHERE user_id=? AND id < ? ORDER BY id DESC LIMIT ?
     * Використовує індекс idx_rounds_user_id (user_id, id) — без filesort і OFFSET.
     */
    public function recent(int $userId, int $limit, ?int $beforeId): array
    {
        $this->cache->increment('metrics:db_history_queries', 86400);
        $sql = 'SELECT id, game, bet, win, created_at FROM game_rounds WHERE user_id = :u'
            . ($beforeId !== null ? ' AND id < :before' : '')
            . ' ORDER BY id DESC LIMIT :lim';
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':u', $userId, \PDO::PARAM_INT);
        if ($beforeId !== null) {
            $st->bindValue(':before', $beforeId, \PDO::PARAM_INT);
        }
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Важкий агрегат — кандидат №1 на кешування (TTL 30 с). */
    public function leaderboard(): array
    {
        return Remember::get(
            $this->cache,
            'leaderboard:24h',
            Env::int('CACHE_TTL_LEADERBOARD', 30),
            function (): array {
                $this->cache->increment('metrics:db_leaderboard_queries', 86400);
                $st = $this->pdo->query(
                    'SELECT u.username, SUM(r.win - r.bet) AS profit
                       FROM game_rounds r
                       JOIN users u ON u.id = r.user_id
                      WHERE r.created_at >= NOW() - INTERVAL 1 DAY
                      GROUP BY r.user_id, u.username
                      ORDER BY profit DESC
                      LIMIT 10'
                );
                return array_map(
                    static fn (array $r): array => ['username' => $r['username'], 'profit' => (float) $r['profit']],
                    $st->fetchAll()
                );
            }
        );
    }
}
