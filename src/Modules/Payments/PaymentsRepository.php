<?php
declare(strict_types=1);
namespace App\Modules\Payments;

use App\App;

final class PaymentsRepository
{
    private \PDO $pdo;
    public function __construct(App $app) { $this->pdo = $app->pdo(); }

    public function findById(int $id): ?array { $s = $this->pdo->prepare('SELECT * FROM payments WHERE id=:id'); $s->execute(['id'=>$id]); return $s->fetch() ?: null; }

    public function findByContract(int $cid): array
    {
        $s = $this->pdo->prepare('SELECT p.*, u.full_name AS creator_name FROM payments p LEFT JOIN users u ON u.id=p.created_by WHERE p.contract_id=:c ORDER BY p.payment_date ASC, p.created_at ASC');
        $s->execute(['c'=>$cid]); return $s->fetchAll();
    }

    public function insert(array $d): int
    {
        $this->pdo->prepare('INSERT INTO payments (contract_id,amount,status,payment_date,purpose,invoice_number,created_by) VALUES (:contract_id,:amount,:status,:payment_date,:purpose,:invoice_number,:created_by)')->execute($d);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $sets = array_map(fn($c)=>"{$c}=:{$c}", array_keys($d)); $d['_id'] = $id;
        $this->pdo->prepare('UPDATE payments SET ' . implode(',', $sets) . ' WHERE id=:_id')->execute($d);
    }

    public function delete(int $id): void { $this->pdo->prepare('DELETE FROM payments WHERE id=:id')->execute(['id'=>$id]); }
}
