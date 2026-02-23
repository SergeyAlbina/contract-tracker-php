<?php
declare(strict_types=1);

namespace App\Modules\Procurements;

use App\App;

final class ProcurementsRepository
{
    private \PDO $pdo;

    public function __construct(App $app)
    {
        $this->pdo = $app->pdo();
    }

    /** @return array{items:array, total:int} */
    public function paginate(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM procurements p WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM procurement_proposals pp WHERE pp.procurement_id = p.id) AS proposals_count,
                    (SELECT MIN(pp.amount) FROM procurement_proposals pp WHERE pp.procurement_id = p.id) AS min_quote_amount
             FROM procurements p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,int> */
    public function countByStatus(array $filters): array
    {
        $filters['status'] = '';
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT p.status, COUNT(*) AS cnt
             FROM procurements p
             WHERE {$where}
             GROUP BY p.status"
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
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.full_name AS creator_name
             FROM procurements p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO procurements (number, subject, law_type, status, nmck_amount, deadline_at, notes, created_by, created_at, updated_at)
             VALUES (:number, :subject, :law_type, :status, :nmck_amount, :deadline_at, :notes, :created_by, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if (!$data) {
            return;
        }

        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = :{$key}";
        }
        $data['_id'] = $id;
        $sql = 'UPDATE procurements SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :_id';
        $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM procurements WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findProposalsByProcurement(int $procurementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pp.*, u.full_name AS creator_name
             FROM procurement_proposals pp
             LEFT JOIN users u ON u.id = pp.created_by
             WHERE pp.procurement_id = :pid
             ORDER BY pp.is_winner DESC, pp.amount ASC, pp.created_at ASC"
        );
        $stmt->execute(['pid' => $procurementId]);
        return $stmt->fetchAll();
    }

    public function findProposalById(int $proposalId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM procurement_proposals WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $proposalId]);
        return $stmt->fetch() ?: null;
    }

    public function insertProposal(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO procurement_proposals
              (procurement_id, supplier_name, supplier_inn, amount, currency, submitted_at, comment, is_winner, created_by, created_at, updated_at)
             VALUES
              (:procurement_id, :supplier_name, :supplier_inn, :amount, :currency, :submitted_at, :comment, :is_winner, :created_by, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProposal(int $proposalId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM procurement_proposals WHERE id = :id');
        $stmt->execute(['id' => $proposalId]);
    }

    public function markWinner(int $procurementId, int $proposalId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo
                ->prepare('UPDATE procurement_proposals SET is_winner = 0, updated_at = NOW() WHERE procurement_id = :pid')
                ->execute(['pid' => $procurementId]);

            $this->pdo
                ->prepare('UPDATE procurement_proposals SET is_winner = 1, updated_at = NOW() WHERE id = :id AND procurement_id = :pid')
                ->execute(['id' => $proposalId, 'pid' => $procurementId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{0:string, 1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.number LIKE :s1 OR p.subject LIKE :s2)';
            $params['s1'] = $params['s2'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['law_type'])) {
            $where[] = 'p.law_type = :lt';
            $params['lt'] = $filters['law_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :st';
            $params['st'] = $filters['status'];
        }

        return [implode(' AND ', $where), $params];
    }
}
