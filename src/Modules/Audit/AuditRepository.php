<?php
declare(strict_types=1);

namespace App\Modules\Audit;

use App\App;

final class AuditRepository
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

        $count = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}"
        );
        $this->bindParams($count, $params);
        $count->execute();
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.user_agent, a.created_at,
                    u.login AS actor_login, u.full_name AS actor_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :lim OFFSET :off"
        );
        $this->bindParams($stmt, $params);
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return string[] */
    public function actions(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT action
             FROM audit_log
             WHERE action <> ''
             ORDER BY action ASC"
        );

        return array_values(
            array_filter(
                array_map(static fn($v): string => (string) $v, $stmt->fetchAll(\PDO::FETCH_COLUMN)),
                static fn(string $v): bool => $v !== ''
            )
        );
    }

    /** @return string[] */
    public function entityTypes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT entity_type
             FROM audit_log
             WHERE entity_type IS NOT NULL AND entity_type <> ''
             ORDER BY entity_type ASC"
        );

        return array_values(
            array_filter(
                array_map(static fn($v): string => (string) $v, $stmt->fetchAll(\PDO::FETCH_COLUMN)),
                static fn(string $v): bool => $v !== ''
            )
        );
    }

    /** @return array<int,array{id:int,login:string,full_name:string}> */
    public function usersForFilter(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, login, full_name
             FROM users
             ORDER BY role = 'admin' DESC, full_name ASC, login ASC"
        );
        return $stmt->fetchAll();
    }

    /** @return array{0:string, 1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.action LIKE :q OR COALESCE(a.entity_type, \'\') LIKE :q OR COALESCE(u.login, \'\') LIKE :q OR COALESCE(u.full_name, \'\') LIKE :q OR a.ip_address LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }

        if (($filters['entity_type'] ?? '') !== '') {
            $where[] = 'a.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }

        if (is_int($filters['user_id'] ?? null)) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $where[] = 'a.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (($filters['date_to'] ?? '') !== '') {
            $toNext = (new \DateTimeImmutable($filters['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            $where[] = 'a.created_at < :date_to_next';
            $params['date_to_next'] = $toNext;
        }

        return [implode(' AND ', $where), $params];
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
    }
}
