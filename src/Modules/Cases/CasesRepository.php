<?php
declare(strict_types=1);

namespace App\Modules\Cases;

use App\App;

final class CasesRepository
{
    private \PDO $pdo;

    public function __construct(App $app)
    {
        $this->pdo = $app->pdo();
    }

    /** @return array{items:array<int,array<string,mixed>>, total:int} */
    public function paginate(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM cases c WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT c.*,
                       CASE
                           WHEN c.due_date IS NOT NULL
                                AND c.due_date < CURDATE()
                                AND COALESCE(c.result_status, '') <> 'DONE'
                           THEN 1
                           ELSE 0
                       END AS is_overdue,
                       (
                           SELECT GROUP_CONCAT(
                               u.full_name
                               ORDER BY ca.is_primary DESC, ca.id ASC
                               SEPARATOR ', '
                           )
                           FROM case_assignees ca
                           JOIN users u ON u.id = ca.user_id
                           WHERE ca.case_id = c.id
                       ) AS assignees
                FROM cases c
                WHERE {$where}
                ORDER BY c.created_at DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,int> */
    public function countByBlock(array $filters): array
    {
        $filters['block_type'] = '';
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT c.block_type, COUNT(*) AS cnt
             FROM cases c
             WHERE {$where}
             GROUP BY c.block_type"
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['block_type']] = (int) $row['cnt'];
        }

        return $result;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cases WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $data): void
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO cases (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))
            . ')';
        $this->pdo->prepare($sql)->execute($data);
    }

    public function update(string $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $set = array_map(static fn(string $column): string => $column . ' = :' . $column, array_keys($data));
        $data['_id'] = $id;
        $sql = 'UPDATE cases SET ' . implode(', ', $set) . ' WHERE id = :_id';
        $this->pdo->prepare($sql)->execute($data);
    }

    public function findPrimaryCaseIdByBundle(string $bundleKey, ?string $excludeCaseId = null): ?string
    {
        if ($bundleKey === '') {
            return null;
        }

        if ($excludeCaseId) {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(duplicate_of_case_id, id) AS primary_id
                 FROM cases
                 WHERE bundle_key = :bundle_key AND id <> :exclude_id
                 ORDER BY created_at ASC
                 LIMIT 1'
            );
            $stmt->execute(['bundle_key' => $bundleKey, 'exclude_id' => $excludeCaseId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(duplicate_of_case_id, id) AS primary_id
                 FROM cases
                 WHERE bundle_key = :bundle_key
                 ORDER BY created_at ASC
                 LIMIT 1'
            );
            $stmt->execute(['bundle_key' => $bundleKey]);
        }

        $primaryId = $stmt->fetchColumn();
        return $primaryId ? (string) $primaryId : null;
    }

    /** @param array<int,string> $caseIds */
    public function attributesByCaseIds(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        [$inSql, $params] = $this->inList($caseIds, 'cid');
        $stmt = $this->pdo->prepare(
            "SELECT case_id, attr_key, attr_value, attr_value_num, attr_value_date
             FROM case_attributes
             WHERE case_id IN ({$inSql})
             ORDER BY id ASC"
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $caseId = (string) $row['case_id'];
            $result[$caseId] ??= [];
            $result[$caseId][(string) $row['attr_key']] = [
                'value' => $row['attr_value'],
                'num' => $row['attr_value_num'],
                'date' => $row['attr_value_date'],
            ];
        }
        return $result;
    }

    /** @param array<int,string> $caseIds */
    public function assigneesByCaseIds(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        [$inSql, $params] = $this->inList($caseIds, 'aid');
        $stmt = $this->pdo->prepare(
            "SELECT ca.case_id, ca.user_id, ca.role, ca.is_primary, u.full_name
             FROM case_assignees ca
             JOIN users u ON u.id = ca.user_id
             WHERE ca.case_id IN ({$inSql})
             ORDER BY ca.is_primary DESC, ca.id ASC"
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $caseId = (string) $row['case_id'];
            $result[$caseId] ??= [];
            $result[$caseId][] = [
                'user_id' => (int) $row['user_id'],
                'full_name' => (string) $row['full_name'],
                'role' => (string) $row['role'],
                'is_primary' => (int) $row['is_primary'] === 1,
            ];
        }
        return $result;
    }

    public function upsertAttribute(string $caseId, string $attrKey, ?string $attrValue, ?float $attrValueNum, ?string $attrValueDate): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_attributes (case_id, attr_key, attr_value, attr_value_num, attr_value_date)
             VALUES (:case_id, :attr_key, :attr_value, :attr_value_num, :attr_value_date)
             ON DUPLICATE KEY UPDATE
               attr_value = VALUES(attr_value),
               attr_value_num = VALUES(attr_value_num),
               attr_value_date = VALUES(attr_value_date)'
        );
        $stmt->execute([
            'case_id' => $caseId,
            'attr_key' => $attrKey,
            'attr_value' => $attrValue,
            'attr_value_num' => $attrValueNum,
            'attr_value_date' => $attrValueDate,
        ]);
    }

    public function addEvent(string $caseId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_events (case_id, event_date, event_type, amount, text)
             VALUES (:case_id, :event_date, :event_type, :amount, :text)'
        );
        $stmt->execute([
            'case_id' => $caseId,
            'event_date' => $data['event_date'] ?? null,
            'event_type' => $data['event_type'] ?? 'NOTE',
            'amount' => $data['amount'] ?? null,
            'text' => $data['text'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addFile(string $caseId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_files (case_id, file_name, file_path, mime_type, size_bytes)
             VALUES (:case_id, :file_name, :file_path, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'case_id' => $caseId,
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'mime_type' => $data['mime_type'] ?? null,
            'size_bytes' => $data['size_bytes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function clearPrimaryRole(string $caseId, string $role, int $exceptUserId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE case_assignees
             SET is_primary = 0
             WHERE case_id = :case_id AND role = :role AND user_id <> :user_id'
        );
        $stmt->execute(['case_id' => $caseId, 'role' => $role, 'user_id' => $exceptUserId]);
    }

    public function upsertAssignee(string $caseId, int $userId, string $role, bool $isPrimary): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_assignees (case_id, user_id, role, is_primary)
             VALUES (:case_id, :user_id, :role, :is_primary)
             ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)'
        );
        $stmt->execute([
            'case_id' => $caseId,
            'user_id' => $userId,
            'role' => $role,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function eventsByCaseId(string $caseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, event_date, event_type, amount, text, created_at
             FROM case_events
             WHERE case_id = :case_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['case_id' => $caseId]);
        return $stmt->fetchAll();
    }

    public function filesByCaseId(string $caseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, file_name, file_path, mime_type, size_bytes, uploaded_at
             FROM case_files
             WHERE case_id = :case_id
             ORDER BY uploaded_at DESC'
        );
        $stmt->execute(['case_id' => $caseId]);
        return $stmt->fetchAll();
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['block_type'])) {
            $where[] = 'c.block_type = :block_type';
            $params['block_type'] = (string) $filters['block_type'];
        } else {
            $where[] = "c.block_type <> 'CONCLUDED'";
        }

        if (!empty($filters['year'])) {
            $where[] = 'c.year = :year';
            $params['year'] = (int) $filters['year'];
        }

        $status = $filters['result_status'] ?? $filters['status'] ?? '';
        if ($status !== '') {
            $where[] = 'c.result_status = :result_status';
            $params['result_status'] = (string) $status;
        }

        if (!empty($filters['assignee'])) {
            $where[] = 'EXISTS (SELECT 1 FROM case_assignees ca WHERE ca.case_id = c.id AND ca.user_id = :assignee)';
            $params['assignee'] = (int) $filters['assignee'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(c.case_code LIKE :q OR c.subject_raw LIKE :q OR c.contract_number LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if ((int) ($filters['show_duplicates'] ?? 0) !== 1) {
            $where[] = 'c.duplicate_of_case_id IS NULL';
        }

        if ((int) ($filters['overdue'] ?? 0) === 1) {
            $where[] = "c.due_date IS NOT NULL AND c.due_date < CURDATE() AND COALESCE(c.result_status, '') <> 'DONE'";
        }

        if ((int) ($filters['in_progress'] ?? 0) === 1) {
            $where[] = "c.result_status = 'IN_PROGRESS'";
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param array<int,string> $values @return array{0:string,1:array<string,string>} */
    private function inList(array $values, string $prefix): array
    {
        $holders = [];
        $params = [];
        foreach (array_values($values) as $idx => $value) {
            $key = $prefix . $idx;
            $holders[] = ':' . $key;
            $params[$key] = $value;
        }

        return [implode(', ', $holders), $params];
    }
}
