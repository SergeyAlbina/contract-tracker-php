<?php
declare(strict_types=1);

namespace App\Modules\Procurements;

use App\App;
use App\Shared\Enum\{LawType, ProcurementStatus};

final class ProcurementsService
{
    private ProcurementsRepository $repo;
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->repo = $app->make(ProcurementsRepository::class);
    }

    public function list(int $page, array $filters): array
    {
        $result = $this->repo->paginate($page, 20, $filters);
        $statusCounts = $this->repo->countByStatus($filters);
        $result['status_counts'] = $statusCounts;
        $result['total_without_status'] = (int) array_sum($statusCounts);

        return $result;
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function getWithProposals(int $id): ?array
    {
        $procurement = $this->repo->findById($id);
        if (!$procurement) {
            return null;
        }

        $proposals = $this->repo->findProposalsByProcurement($id);
        $amounts = array_map(static fn(array $p): float => (float) $p['amount'], $proposals);
        $winner = null;
        foreach ($proposals as $proposal) {
            if ((int) $proposal['is_winner'] === 1) {
                $winner = $proposal;
                break;
            }
        }

        $procurement['proposals'] = $proposals;
        $procurement['proposals_count'] = count($proposals);
        $procurement['min_quote_amount'] = $amounts ? min($amounts) : null;
        $procurement['winner'] = $winner;

        return $procurement;
    }

    /** @return array{success:bool, id?:int, errors?:string[]} */
    public function create(array $data): array
    {
        $payload = $this->normalizeProcurementInput($data, $this->app->currentUserId());
        $errors = $this->validateProcurement($payload);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = $this->repo->insert($payload);
        $this->app->audit('procurement_created', 'procurement', $id, ['number' => $payload['number']]);
        return ['success' => true, 'id' => $id];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function update(int $id, array $data): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Закупка не найдена.']];
        }

        $payload = $this->normalizeProcurementInput(array_merge($existing, $data), (int) ($existing['created_by'] ?? 0));
        $errors = $this->validateProcurement($payload);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $allowed = ['number', 'subject', 'law_type', 'status', 'nmck_amount', 'deadline_at', 'notes'];
        $updateData = array_intersect_key($payload, array_flip($allowed));
        $this->repo->update($id, $updateData);
        $this->app->audit('procurement_updated', 'procurement', $id, ['number' => $payload['number']]);
        return ['success' => true];
    }

    public function delete(int $id): bool
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return false;
        }

        $this->repo->delete($id);
        $this->app->audit('procurement_deleted', 'procurement', $id, ['number' => $existing['number']]);
        return true;
    }

    /** @return array{success:bool, errors?:string[]} */
    public function addProposal(int $procurementId, array $data): array
    {
        $procurement = $this->repo->findById($procurementId);
        if (!$procurement) {
            return ['success' => false, 'errors' => ['Закупка не найдена.']];
        }

        $payload = $this->normalizeProposalInput($procurementId, $data);
        $errors = $this->validateProposal($payload);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $proposalId = $this->repo->insertProposal($payload);
        if ((int) $payload['is_winner'] === 1) {
            $this->repo->markWinner($procurementId, $proposalId);
            $this->repo->update($procurementId, ['status' => ProcurementStatus::AWARDED->value]);
        }

        $this->app->audit('procurement_proposal_added', 'procurement', $procurementId, [
            'proposal_id' => $proposalId,
            'supplier_name' => $payload['supplier_name'],
        ]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function deleteProposal(int $proposalId): array
    {
        $proposal = $this->repo->findProposalById($proposalId);
        if (!$proposal) {
            return ['success' => false, 'errors' => ['КП не найдено.']];
        }

        $this->repo->deleteProposal($proposalId);
        $this->app->audit('procurement_proposal_deleted', 'procurement', (int) $proposal['procurement_id'], [
            'proposal_id' => $proposalId,
        ]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function setWinner(int $procurementId, int $proposalId): array
    {
        $proposal = $this->repo->findProposalById($proposalId);
        if (!$proposal || (int) $proposal['procurement_id'] !== $procurementId) {
            return ['success' => false, 'errors' => ['КП не найдено для этой закупки.']];
        }

        $this->repo->markWinner($procurementId, $proposalId);
        $this->repo->update($procurementId, ['status' => ProcurementStatus::AWARDED->value]);
        $this->app->audit('procurement_winner_selected', 'procurement', $procurementId, [
            'proposal_id' => $proposalId,
        ]);
        return ['success' => true];
    }

    private function normalizeProcurementInput(array $input, ?int $createdBy): array
    {
        return [
            'number' => trim((string) ($input['number'] ?? '')),
            'subject' => trim((string) ($input['subject'] ?? '')),
            'law_type' => (string) ($input['law_type'] ?? ''),
            'status' => (string) ($input['status'] ?? ProcurementStatus::DRAFT->value),
            'nmck_amount' => (float) ($input['nmck_amount'] ?? 0),
            'deadline_at' => $this->nullableDate($input['deadline_at'] ?? null),
            'notes' => $this->nullableText($input['notes'] ?? null),
            'created_by' => $createdBy ?: null,
        ];
    }

    private function normalizeProposalInput(int $procurementId, array $input): array
    {
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'RUB')));
        if ($currency === '') {
            $currency = 'RUB';
        }

        return [
            'procurement_id' => $procurementId,
            'supplier_name' => trim((string) ($input['supplier_name'] ?? '')),
            'supplier_inn' => $this->nullableText($input['supplier_inn'] ?? null),
            'amount' => (float) ($input['amount'] ?? 0),
            'currency' => $currency,
            'submitted_at' => $this->nullableDate($input['submitted_at'] ?? null),
            'comment' => $this->nullableText($input['comment'] ?? null),
            'is_winner' => (($input['is_winner'] ?? '0') === '1') ? 1 : 0,
            'created_by' => $this->app->currentUserId(),
        ];
    }

    /** @return string[] */
    private function validateProcurement(array $payload): array
    {
        $errors = [];

        if ($payload['number'] === '') {
            $errors[] = 'Номер закупки обязателен.';
        }
        if ($payload['subject'] === '') {
            $errors[] = 'Предмет закупки обязателен.';
        }
        if (LawType::tryFrom($payload['law_type']) === null) {
            $errors[] = 'Укажите корректный тип закона.';
        }
        if (ProcurementStatus::tryFrom($payload['status']) === null) {
            $errors[] = 'Укажите корректный статус закупки.';
        }
        if ($payload['nmck_amount'] <= 0) {
            $errors[] = 'НМЦК должна быть больше 0.';
        }

        return $errors;
    }

    /** @return string[] */
    private function validateProposal(array $payload): array
    {
        $errors = [];

        if ($payload['supplier_name'] === '') {
            $errors[] = 'Укажите поставщика.';
        }
        if ($payload['amount'] <= 0) {
            $errors[] = 'Сумма КП должна быть больше 0.';
        }
        if (!preg_match('/^[A-Z]{3}$/', $payload['currency'])) {
            $errors[] = 'Валюта должна быть в формате ISO (например, RUB).';
        }
        if ($payload['supplier_inn'] !== null && $payload['supplier_inn'] !== '' && !preg_match('/^\d{10,12}$/', $payload['supplier_inn'])) {
            $errors[] = 'ИНН поставщика должен содержать 10 или 12 цифр.';
        }

        return $errors;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return $value;
    }
}
