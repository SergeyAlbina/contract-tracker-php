<?php
declare(strict_types=1);
namespace App\Modules\Contracts;

use App\App;

final class ContractsRepository
{
    private \PDO $pdo;
    public function __construct(App $app) { $this->pdo = $app->pdo(); }

    /** @return array{items:array, total:int} */
    public function paginate(int $page, int $perPage, array $filters): array
    {
        $w = ['1=1']; $p = [];

        if (!empty($filters['search'])) {
            $w[] = '(c.number LIKE :s1 OR c.subject LIKE :s2 OR c.contractor_name LIKE :s3)';
            $p['s1'] = $p['s2'] = $p['s3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['law_type']))  { $w[] = 'c.law_type = :lt'; $p['lt'] = $filters['law_type']; }
        if (!empty($filters['status']))    { $w[] = 'c.status = :st';   $p['st'] = $filters['status']; }

        $where = implode(' AND ', $w);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM contracts c WHERE {$where}");
        $stmt->execute($p);
        $total = (int) $stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.full_name AS creator_name FROM contracts c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE {$where} ORDER BY c.created_at DESC LIMIT :lim OFFSET :off"
        );
        foreach ($p as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT c.*, u.full_name AS creator_name FROM contracts c LEFT JOIN users u ON u.id = c.created_by WHERE c.id = :id');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    public function insert(array $d): int
    {
        $cols = array_keys($d);
        $sql = 'INSERT INTO contracts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_map(fn($c) => ':' . $c, $cols)) . ')';
        $this->pdo->prepare($sql)->execute($d);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $sets = array_map(fn($c) => "{$c} = :{$c}", array_keys($d));
        $d['_id'] = $id;
        $this->pdo->prepare('UPDATE contracts SET ' . implode(', ', $sets) . ' WHERE id = :_id')->execute($d);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM contracts WHERE id = :id')->execute(['id' => $id]);
    }

    public function paidSum(int $contractId): float
    {
        $s = $this->pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE contract_id = :id AND status = 'paid'");
        $s->execute(['id' => $contractId]);
        return (float) $s->fetchColumn();
    }
}
