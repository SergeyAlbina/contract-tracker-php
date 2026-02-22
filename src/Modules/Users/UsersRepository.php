<?php
declare(strict_types=1);

namespace App\Modules\Users;

use App\App;

final class UsersRepository
{
    private \PDO $pdo;

    public function __construct(App $app)
    {
        $this->pdo = $app->pdo();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, login, email, full_name, role, is_active, created_at, updated_at
             FROM users
             ORDER BY role = \'admin\' DESC, role = \'manager\' DESC, created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login, email, full_name, role, is_active, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findAuthById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login, password_hash, role, is_active
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function isLoginTaken(string $login, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE login = :login';
        $params = ['login' => $login];
        if ($exceptId !== null) {
            $sql .= ' AND id != :except_id';
            $params['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function isEmailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id != :except_id';
            $params['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        return (int) $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (login, email, password_hash, full_name, role, is_active, created_at, updated_at)
             VALUES (:login, :email, :password_hash, :full_name, :role, :is_active, NOW(), NOW())'
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
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :_id';
        $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
