<?php
declare(strict_types=1);
namespace App\Modules\Documents;

use App\App;
use App\Infrastructure\Storage\LocalStorage;

final class DocumentsService
{
    private DocumentsRepository $repo;
    private LocalStorage $storage;
    private App $app;

    public function __construct(App $app) { $this->app = $app; $this->repo = $app->make(DocumentsRepository::class); $this->storage = new LocalStorage(); }

    public function upload(int $contractId, array $file, string $docType = 'other'): int
    {
        $info = $this->storage->upload($file, "contracts/{$contractId}");
        $id = $this->repo->insert([
            'contract_id' => $contractId, 'original_name' => $info['original_name'], 'safe_name' => $info['safe_name'],
            'relative_path' => $info['relative_path'], 'mime_type' => $info['mime'], 'size_bytes' => $info['size'],
            'sha256' => $info['sha256'], 'doc_type' => $docType, 'uploaded_by' => $this->app->currentUserId(),
        ]);
        $this->app->audit('document_uploaded', 'document', $id);
        return $id;
    }

    public function getById(int $id): ?array { return $this->repo->findById($id); }
    public function getByContract(int $cid): array { return $this->repo->findByContract($cid); }
    public function absolutePath(string $rel): string { return $this->storage->absolutePath($rel); }

    public function delete(int $id): bool
    {
        $d = $this->repo->findById($id);
        if (!$d) return false;
        $this->storage->delete($d['relative_path']);
        $this->repo->delete($id);
        $this->app->audit('document_deleted', 'document', $id);
        return true;
    }
}
