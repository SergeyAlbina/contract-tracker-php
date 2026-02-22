<?php
declare(strict_types=1);

namespace App\Modules\Stages;

use App\App;
use App\Shared\Enum\StageStatus;

final class StagesService
{
    private StagesRepository $repo;
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->repo = $app->make(StagesRepository::class);
    }

    public function getByContract(int $contractId): array
    {
        return $this->repo->findByContract($contractId);
    }

    /** @return array{success:bool, errors?:string[]} */
    public function create(int $contractId, array $d): array
    {
        if (!$this->repo->contractExists($contractId)) {
            return ['success' => false, 'errors' => ['Контракт не найден.']];
        }

        $prepared = $this->prepareData($d);
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $id = $this->repo->insert(array_merge($prepared['data'], [
            'contract_id' => $contractId,
            'created_by' => $this->app->currentUserId(),
        ]));

        $this->app->audit('stage_created', 'contract_stage', $id, ['contract_id' => $contractId]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function update(int $id, array $d): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) return ['success' => false, 'errors' => ['Этап не найден.']];

        $prepared = $this->prepareData(array_merge($existing, $d));
        if ($prepared['errors']) return ['success' => false, 'errors' => $prepared['errors']];

        $this->repo->update($id, $prepared['data']);
        $this->app->audit('stage_updated', 'contract_stage', $id, ['contract_id' => (int) $existing['contract_id']]);
        return ['success' => true];
    }

    public function delete(int $id): bool
    {
        $existing = $this->repo->findById($id);
        if (!$existing) return false;

        $this->repo->delete($id);
        $this->app->audit('stage_deleted', 'contract_stage', $id, ['contract_id' => (int) $existing['contract_id']]);
        return true;
    }

    /** @return array{data:array<string,mixed>, errors:string[]} */
    private function prepareData(array $d): array
    {
        $errors = [];

        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '') $errors[] = 'Название этапа обязательно.';
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);

        $statusRaw = (string) ($d['status'] ?? StageStatus::PLANNED->value);
        $status = StageStatus::tryFrom($statusRaw);
        if (!$status) {
            $errors[] = 'Некорректный статус этапа.';
            $status = StageStatus::PLANNED;
        }

        $plannedRaw = trim((string) ($d['planned_date'] ?? ''));
        $plannedDate = $this->normalizeDate($plannedRaw);
        if ($plannedRaw !== '' && $plannedDate === null) $errors[] = 'Некорректная плановая дата.';

        $actualRaw = trim((string) ($d['actual_date'] ?? ''));
        $actualDate = $this->normalizeDate($actualRaw);
        if ($actualRaw !== '' && $actualDate === null) $errors[] = 'Некорректная фактическая дата.';

        $sortOrder = max(0, (int) ($d['sort_order'] ?? 0));

        $description = trim((string) ($d['description'] ?? ''));
        if ($description !== '') {
            $description = function_exists('mb_substr') ? mb_substr($description, 0, 4000) : substr($description, 0, 4000);
        } else {
            $description = null;
        }

        return [
            'data' => [
                'title' => $title,
                'status' => $status->value,
                'planned_date' => $plannedDate,
                'actual_date' => $actualDate,
                'sort_order' => $sortOrder,
                'description' => $description,
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
}
