<?php
declare(strict_types=1);

namespace App\Modules\BillingDocs;

use App\App;

final class BillingDocsRepository
{
    private \PDO $pdo;

    public function __construct(App $app)
    {
        $this->pdo = $app->pdo();
    }

    public function contractExists(int $id): bool
    {
        $s = $this->pdo->prepare('SELECT 1 FROM contracts WHERE id = :id');
        $s->execute(['id' => $id]);
        return (bool) $s->fetchColumn();
    }

    public function findInvoiceById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM contract_invoices WHERE id = :id');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    public function findInvoicesByContract(int $contractId): array
    {
        $s = $this->pdo->prepare(
            'SELECT i.*, u.full_name AS creator_name
             FROM contract_invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.contract_id = :contract_id
             ORDER BY i.invoice_date DESC, i.id DESC'
        );
        $s->execute(['contract_id' => $contractId]);
        return $s->fetchAll();
    }

    public function insertInvoice(array $d): int
    {
        $this->pdo->prepare(
            'INSERT INTO contract_invoices (contract_id,invoice_number,invoice_date,due_date,amount,status,comment,created_by)
             VALUES (:contract_id,:invoice_number,:invoice_date,:due_date,:amount,:status,:comment,:created_by)'
        )->execute($d);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateInvoice(int $id, array $d): void
    {
        if (!$d) return;
        $sets = array_map(static fn(string $c): string => "{$c} = :{$c}", array_keys($d));
        $d['_id'] = $id;
        $this->pdo->prepare('UPDATE contract_invoices SET ' . implode(', ', $sets) . ' WHERE id = :_id')->execute($d);
    }

    public function deleteInvoice(int $id): void
    {
        $this->pdo->prepare('DELETE FROM contract_invoices WHERE id = :id')->execute(['id' => $id]);
    }

    public function findActById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM contract_acts WHERE id = :id');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    public function findActsByContract(int $contractId): array
    {
        $s = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS creator_name
             FROM contract_acts a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.contract_id = :contract_id
             ORDER BY a.act_date DESC, a.id DESC'
        );
        $s->execute(['contract_id' => $contractId]);
        return $s->fetchAll();
    }

    public function insertAct(array $d): int
    {
        $this->pdo->prepare(
            'INSERT INTO contract_acts (contract_id,act_number,act_date,amount,status,comment,created_by)
             VALUES (:contract_id,:act_number,:act_date,:amount,:status,:comment,:created_by)'
        )->execute($d);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAct(int $id, array $d): void
    {
        if (!$d) return;
        $sets = array_map(static fn(string $c): string => "{$c} = :{$c}", array_keys($d));
        $d['_id'] = $id;
        $this->pdo->prepare('UPDATE contract_acts SET ' . implode(', ', $sets) . ' WHERE id = :_id')->execute($d);
    }

    public function deleteAct(int $id): void
    {
        $this->pdo->prepare('DELETE FROM contract_acts WHERE id = :id')->execute(['id' => $id]);
    }
}
