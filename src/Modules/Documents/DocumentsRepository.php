<?php
declare(strict_types=1);
namespace App\Modules\Documents;

use App\App;

final class DocumentsRepository
{
    private \PDO $pdo;
    public function __construct(App $app) { $this->pdo = $app->pdo(); }

    public function findById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id');
        $s->execute(['id' => $id]); return $s->fetch() ?: null;
    }

    public function findByContract(int $cid): array
    {
        $s = $this->pdo->prepare('SELECT d.*, u.full_name AS uploader_name FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.contract_id = :c ORDER BY d.created_at DESC');
        $s->execute(['c' => $cid]); return $s->fetchAll();
    }

    public function insert(array $d): int
    {
        $this->pdo->prepare('INSERT INTO documents (contract_id,original_name,safe_name,relative_path,mime_type,size_bytes,sha256,doc_type,uploaded_by) VALUES (:contract_id,:original_name,:safe_name,:relative_path,:mime_type,:size_bytes,:sha256,:doc_type,:uploaded_by)')->execute($d);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void { $this->pdo->prepare('DELETE FROM documents WHERE id = :id')->execute(['id' => $id]); }
}
