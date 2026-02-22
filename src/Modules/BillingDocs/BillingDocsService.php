<?php
declare(strict_types=1);

namespace App\Modules\BillingDocs;

use App\App;
use App\Shared\Enum\{ActStatus, InvoiceStatus};

final class BillingDocsService
{
    private BillingDocsRepository $repo;
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->repo = $app->make(BillingDocsRepository::class);
    }

    public function getInvoicesByContract(int $contractId): array
    {
        return $this->repo->findInvoicesByContract($contractId);
    }

    public function getActsByContract(int $contractId): array
    {
        return $this->repo->findActsByContract($contractId);
    }

    /** @return array{success:bool, errors?:string[]} */
    public function createInvoice(int $contractId, array $d): array
    {
        if (!$this->repo->contractExists($contractId)) {
            return ['success' => false, 'errors' => ['Контракт не найден.']];
        }

        $prepared = $this->prepareInvoiceData($d);
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $id = $this->repo->insertInvoice(array_merge($prepared['data'], [
            'contract_id' => $contractId,
            'created_by' => $this->app->currentUserId(),
        ]));

        $this->app->audit('invoice_created', 'contract_invoice', $id, ['contract_id' => $contractId]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function updateInvoice(int $id, array $d): array
    {
        $existing = $this->repo->findInvoiceById($id);
        if (!$existing) return ['success' => false, 'errors' => ['Счёт не найден.']];

        $prepared = $this->prepareInvoiceData(array_merge($existing, $d));
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $this->repo->updateInvoice($id, $prepared['data']);
        $this->app->audit('invoice_updated', 'contract_invoice', $id, ['contract_id' => (int) $existing['contract_id']]);
        return ['success' => true];
    }

    public function deleteInvoice(int $id): bool
    {
        $existing = $this->repo->findInvoiceById($id);
        if (!$existing) return false;

        $this->repo->deleteInvoice($id);
        $this->app->audit('invoice_deleted', 'contract_invoice', $id, ['contract_id' => (int) $existing['contract_id']]);
        return true;
    }

    /** @return array{success:bool, errors?:string[]} */
    public function createAct(int $contractId, array $d): array
    {
        if (!$this->repo->contractExists($contractId)) {
            return ['success' => false, 'errors' => ['Контракт не найден.']];
        }

        $prepared = $this->prepareActData($d);
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $id = $this->repo->insertAct(array_merge($prepared['data'], [
            'contract_id' => $contractId,
            'created_by' => $this->app->currentUserId(),
        ]));

        $this->app->audit('act_created', 'contract_act', $id, ['contract_id' => $contractId]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function updateAct(int $id, array $d): array
    {
        $existing = $this->repo->findActById($id);
        if (!$existing) return ['success' => false, 'errors' => ['Акт не найден.']];

        $prepared = $this->prepareActData(array_merge($existing, $d));
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $this->repo->updateAct($id, $prepared['data']);
        $this->app->audit('act_updated', 'contract_act', $id, ['contract_id' => (int) $existing['contract_id']]);
        return ['success' => true];
    }

    public function deleteAct(int $id): bool
    {
        $existing = $this->repo->findActById($id);
        if (!$existing) return false;

        $this->repo->deleteAct($id);
        $this->app->audit('act_deleted', 'contract_act', $id, ['contract_id' => (int) $existing['contract_id']]);
        return true;
    }

    /** @return array{data:array<string,mixed>, errors:string[]} */
    private function prepareInvoiceData(array $d): array
    {
        $errors = [];

        $number = trim((string) ($d['invoice_number'] ?? ''));
        if ($number === '') $errors[] = 'Номер счёта обязателен.';
        $number = $this->limit($number, 100);

        $amount = (float) ($d['amount'] ?? 0);
        if ($amount <= 0) $errors[] = 'Сумма счёта должна быть больше 0.';

        $status = InvoiceStatus::tryFrom((string) ($d['status'] ?? InvoiceStatus::ISSUED->value));
        if (!$status) {
            $errors[] = 'Некорректный статус счёта.';
            $status = InvoiceStatus::ISSUED;
        }

        $invoiceDateRaw = trim((string) ($d['invoice_date'] ?? ''));
        $invoiceDate = $this->normalizeDate($invoiceDateRaw);
        if ($invoiceDateRaw !== '' && $invoiceDate === null) $errors[] = 'Некорректная дата счёта.';

        $dueDateRaw = trim((string) ($d['due_date'] ?? ''));
        $dueDate = $this->normalizeDate($dueDateRaw);
        if ($dueDateRaw !== '' && $dueDate === null) $errors[] = 'Некорректная дата оплаты.';

        $comment = trim((string) ($d['comment'] ?? ''));
        $comment = $comment === '' ? null : $this->limit($comment, 4000);

        return [
            'data' => [
                'invoice_number' => $number,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'status' => $status->value,
                'comment' => $comment,
            ],
            'errors' => $errors,
        ];
    }

    /** @return array{data:array<string,mixed>, errors:string[]} */
    private function prepareActData(array $d): array
    {
        $errors = [];

        $number = trim((string) ($d['act_number'] ?? ''));
        if ($number === '') $errors[] = 'Номер акта обязателен.';
        $number = $this->limit($number, 100);

        $amount = (float) ($d['amount'] ?? 0);
        if ($amount <= 0) $errors[] = 'Сумма акта должна быть больше 0.';

        $status = ActStatus::tryFrom((string) ($d['status'] ?? ActStatus::PENDING->value));
        if (!$status) {
            $errors[] = 'Некорректный статус акта.';
            $status = ActStatus::PENDING;
        }

        $actDateRaw = trim((string) ($d['act_date'] ?? ''));
        $actDate = $this->normalizeDate($actDateRaw);
        if ($actDateRaw !== '' && $actDate === null) $errors[] = 'Некорректная дата акта.';

        $comment = trim((string) ($d['comment'] ?? ''));
        $comment = $comment === '' ? null : $this->limit($comment, 4000);

        return [
            'data' => [
                'act_number' => $number,
                'act_date' => $actDate,
                'amount' => $amount,
                'status' => $status->value,
                'comment' => $comment,
            ],
            'errors' => $errors,
        ];
    }

    private function normalizeDate(string $v): ?string
    {
        if ($v === '') return null;
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
        return ($dt && $dt->format('Y-m-d') === $v) ? $v : null;
    }

    private function limit(string $v, int $len): string
    {
        return function_exists('mb_substr') ? mb_substr($v, 0, $len) : substr($v, 0, $len);
    }
}
