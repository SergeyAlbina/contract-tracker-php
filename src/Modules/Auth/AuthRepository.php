<?php
declare(strict_types=1);
namespace App\Modules\Auth;

use App\App;

final class AuthRepository
{
    private \PDO $pdo;
    public function __construct(App $app) { $this->pdo = $app->pdo(); }

    public function findByLogin(string $login): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM users WHERE login = :l LIMIT 1');
        $s->execute(['l' => $login]);
        return $s->fetch() ?: null;
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $id]);
    }
}
