<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(array $config, bool $withoutDatabase = false): PDO
    {
        $database = $withoutDatabase ? '' : ';dbname=' . $config['name'];
        $dsn = sprintf('mysql:host=%s;port=%d%s;charset=utf8mb4', $config['host'], $config['port'], $database);

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

