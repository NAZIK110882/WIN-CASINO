<?php
namespace App;

class Database
{
    public static function getConnection(): \PDO
    {
        $config = require __DIR__ . '/../config/database.php';

        $pdo = new \PDO(
            $config['dsn'],
            $config['username'],
            $config['password'],
            $config['options']
        );

        return $pdo;
    }
}
