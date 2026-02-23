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
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM contracts c WHERE {$where}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.full_name AS creator_name FROM contracts c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE {$where} ORDER BY c.created_at DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,int> */
    public function countByStatus(array $filters): array
    {
        $filters['status'] = '';
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT c.status, COUNT(*) AS cnt
             FROM contracts c
             WHERE {$where}
             GROUP BY c.status"
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['status']] = (int) $row['cnt'];
        }

        return $result;
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

    public function exportRows(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.number, c.subject, c.law_type, c.contractor_name, c.contractor_inn,
                    c.total_amount, c.nmck_amount, c.status, c.signed_at, c.expires_at,
                    c.created_at, u.full_name AS creator_name
             FROM contracts c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE {$where}
             ORDER BY c.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array{0:string, 1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(c.number LIKE :s1 OR c.subject LIKE :s2 OR c.contractor_name LIKE :s3)';
            $params['s1'] = $params['s2'] = $params['s3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['law_type'])) {
            $where[] = 'c.law_type = :lt';
            $params['lt'] = $filters['law_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = :st';
            $params['st'] = $filters['status'];
        }

        return [implode(' AND ', $where), $params];
    }
}
