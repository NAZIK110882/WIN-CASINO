<?php
declare(strict_types=1);

namespace App\Service;

use App\Cache\CacheFactory;
use App\Cache\CacheInterface;
use App\Cache\Remember;
use App\Support\Env;

final class UserService
{
    public function __construct(private \PDO $pdo, private CacheInterface $cache)
    {
    }

    /** @return array{id:int,username:string,balance:float,role:string}|null */
    public function profile(int $id): ?array
    {
        return Remember::get(
            $this->cache,
            "user:$id",
            Env::int('CACHE_TTL_USER', 30),
            function () use ($id): ?array {
                $this->cache->increment('metrics:db_user_queries', 86400);
                $st = $this->pdo->prepare('SELECT id, username, balance, role FROM users WHERE id = :id');
                $st->execute(['id' => $id]);
                $row = $st->fetch();
                if (!$row) {
                    return null;
                }
                return [
                    'id' => (int) $row['id'],
                    'username' => $row['username'],
                    'balance' => (float) $row['balance'],
                    'role' => $row['role'],
                ];
            }
        );
    }

    /** Інвалідація кешу після будь-якої зміни балансу (ставка/виграш). */
    public static function forget(int $id): void
    {
        CacheFactory::make()->delete("user:$id");
    }
}
