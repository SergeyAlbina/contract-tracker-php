<?php
declare(strict_types=1);
namespace App\Infrastructure\Db;

final class Transaction
{
    public function __construct(private readonly \PDO $pdo) {}

    /** @template T @param callable():T $fn @return T */
    public function run(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try { $r = $fn(); $this->pdo->commit(); return $r; }
        catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
}
