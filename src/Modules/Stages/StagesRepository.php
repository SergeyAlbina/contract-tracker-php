<?php
declare(strict_types=1);

namespace App\Modules\Stages;

use App\App;

final class StagesRepository
{
    private \PDO $pdo;

    public function __construct(App $app)
    {
        $this->pdo = $app->pdo();
    }

    public function findById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM contract_stages WHERE id = :id');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    public function contractExists(int $id): bool
    {
        $s = $this->pdo->prepare('SELECT 1 FROM contracts WHERE id = :id');
        $s->execute(['id' => $id]);
        return (bool) $s->fetchColumn();
    }

    public function findByContract(int $contractId): array
    {
        $s = $this->pdo->prepare(
            'SELECT s.*, u.full_name AS creator_name
             FROM contract_stages s
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.contract_id = :contract_id
             ORDER BY s.sort_order ASC, s.planned_date ASC, s.id ASC'
        );
        $s->execute(['contract_id' => $contractId]);
        return $s->fetchAll();
    }

    public function insert(array $d): int
    {
        $this->pdo->prepare(
            'INSERT INTO contract_stages (contract_id,title,status,planned_date,actual_date,sort_order,description,created_by)
             VALUES (:contract_id,:title,:status,:planned_date,:actual_date,:sort_order,:description,:created_by)'
        )->execute($d);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        if (!$d) return;

        $sets = array_map(static fn(string $c): string => "{$c} = :{$c}", array_keys($d));
        $d['_id'] = $id;
        $this->pdo->prepare('UPDATE contract_stages SET ' . implode(', ', $sets) . ' WHERE id = :_id')->execute($d);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM contract_stages WHERE id = :id')->execute(['id' => $id]);
    }
}
