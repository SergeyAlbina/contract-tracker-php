<?php
declare(strict_types=1);
namespace App\Infrastructure\Db;

use App\Shared\Utils\Env;

final class PdoFactory
{
    public static function create(): \PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Env::get('DB_HOST', 'localhost'), Env::get('DB_PORT', '3306'),
            Env::get('DB_NAME', 'contract_tracker'), Env::get('DB_CHARSET', 'utf8mb4'));

        $pdo = new \PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        return $pdo;
    }
}
