<?php
declare(strict_types=1);

namespace App\Modules\Cases;

use App\App;
use App\Infrastructure\Storage\LocalStorage;
use App\Shared\Enum\CaseAssigneeRole;
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseEventType;
use App\Shared\Enum\CaseResultStatus;

final class CasesService
{
    private CasesRepository $repo;
    private LocalStorage $storage;

    public function __construct(private readonly App $app)
    {
        $this->repo = $app->make(CasesRepository::class);
        $this->storage = new LocalStorage();
    }

    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = max(1, min(200, $perPage));

        $res = $this->repo->paginate($page, $perPage, $filters);
        $blockCounts = $this->repo->countByBlock($filters);
        $years = $this->repo->distinctYears($filters);
        $caseIds = array_map(static fn(array $row): string => (string) $row['id'], $res['items']);

        $attributes = $this->repo->attributesByCaseIds($caseIds);
        $assignees = $this->repo->assigneesByCaseIds($caseIds);

        foreach ($res['items'] as &$row) {
            $caseId = (string) $row['id'];
            $row['is_overdue'] = (int) ($row['is_overdue'] ?? 0) === 1;
            $row['attributes'] = $attributes[$caseId] ?? [];
            $row['assignees_data'] = $assignees[$caseId] ?? [];
        }
        unset($row);

        $pages = (int) ceil(($res['total'] ?: 1) / $perPage);

        return [
            'items' => $res['items'],
            'total' => $res['total'],
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'block_counts' => $blockCounts,
            'years' => $years,
            'total_without_block' => (int) array_sum($blockCounts),
        ];
    }

    public function get(string $caseId): ?array
    {
        $case = $this->repo->findById($caseId);
        if (!$case) {
            return null;
        }

        $case['is_overdue'] = ($case['due_date'] ?? null) !== null
            && (string) ($case['due_date'] ?? '') < date('Y-m-d')
            && (string) ($case['result_status'] ?? '') !== CaseResultStatus::DONE->value;

        $case['attributes'] = $this->repo->attributesByCaseIds([$caseId])[$caseId] ?? [];
        $case['assignees'] = $this->repo->assigneesByCaseIds([$caseId])[$caseId] ?? [];
        $case['events'] = $this->repo->eventsByCaseId($caseId);
        $case['files'] = $this->repo->filesByCaseId($caseId);

        return $case;
    }

    /** @return array{success:bool,id?:string,errors?:array<int,string>} */
    public function create(array $input): array
    {
        [$payload, $errors] = $this->buildCasePayload($input);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $caseId = $this->uuidV4();
        $payload['id'] = $caseId;

        $bundleKey = (string) ($payload['bundle_key'] ?? '');
        $payload['duplicate_of_case_id'] = $bundleKey !== ''
            ? $this->repo->findPrimaryCaseIdByBundle($bundleKey)
            : null;

        $this->repo->insert($payload);
        $this->app->audit('case_created', 'case', null, ['case_id' => $caseId, 'block_type' => $payload['block_type']]);

        return ['success' => true, 'id' => $caseId];
    }

    /** @return array{success:bool,errors?:array<int,string>} */
    public function update(string $caseId, array $input): array
    {
        $existing = $this->repo->findById($caseId);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Дело не найдено.']];
        }

        [$payload, $errors] = $this->buildCasePayload(array_merge($existing, $input));
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $bundleKey = (string) ($payload['bundle_key'] ?? '');
        $payload['duplicate_of_case_id'] = $bundleKey !== ''
            ? $this->repo->findPrimaryCaseIdByBundle($bundleKey, $caseId)
            : null;

        $this->repo->update($caseId, $payload);
        $this->app->audit('case_updated', 'case', null, ['case_id' => $caseId]);

        return ['success' => true];
    }

    /** @param array<string,mixed> $attributes */
    public function saveAttributes(string $caseId, array $attributes): array
    {
        if (!$this->repo->findById($caseId)) {
            return ['success' => false, 'errors' => ['Дело не найдено.']];
        }

        $updated = 0;
        $errors = [];

        foreach ($attributes as $key => $value) {
            [$attrKey, $attrPayload] = $this->normalizeAttribute($key, $value);
            if ($attrKey === null || $attrPayload === null) {
                $errors[] = 'Некорректный формат атрибута: ' . (string) $key;
                continue;
            }

            $this->repo->upsertAttribute(
                $caseId,
                $attrKey,
                $attrPayload['attr_value'],
                $attrPayload['attr_value_num'],
                $attrPayload['attr_value_date']
            );
            $updated++;
        }

        if ($updated > 0) {
            $this->app->audit('case_attributes_updated', 'case', null, ['case_id' => $caseId, 'count' => $updated]);
        }

        return ['success' => $updated > 0, 'updated' => $updated, 'errors' => $errors];
    }

    public function addEvent(string $caseId, array $input): array
    {
        if (!$this->repo->findById($caseId)) {
            return ['success' => false, 'errors' => ['Дело не найдено.']];
        }

        $eventType = strtoupper(trim((string) ($input['event_type'] ?? CaseEventType::NOTE->value)));
        if (!CaseEventType::tryFrom($eventType)) {
            return ['success' => false, 'errors' => ['Некорректный тип события.']];
        }

        $errors = [];
        $eventDate = $this->normalizeDate($input['event_date'] ?? null, 'event_date', $errors);
        $amount = $this->normalizeDecimal($input['amount'] ?? null, 'amount', $errors);
        $text = $this->normalizeText($input['text'] ?? null);

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $eventId = $this->repo->addEvent($caseId, [
            'event_date' => $eventDate,
            'event_type' => $eventType,
            'amount' => $amount,
            'text' => $text,
        ]);
        $this->app->audit('case_event_added', 'case', null, ['case_id' => $caseId, 'event_id' => $eventId]);

        return ['success' => true, 'id' => $eventId];
    }

    public function uploadFile(string $caseId, array $file): array
    {
        if (!$this->repo->findById($caseId)) {
            return ['success' => false, 'errors' => ['Дело не найдено.']];
        }

        try {
            $info = $this->storage->upload($file, 'cases/' . $caseId);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }

        $fileId = $this->repo->addFile($caseId, [
            'file_name' => $info['original_name'],
            'file_path' => $info['relative_path'],
            'mime_type' => $info['mime'],
            'size_bytes' => $info['size'],
        ]);
        $this->app->audit('case_file_uploaded', 'case', null, ['case_id' => $caseId, 'file_id' => $fileId]);

        return [
            'success' => true,
            'id' => $fileId,
            'file' => [
                'file_name' => $info['original_name'],
                'file_path' => $info['relative_path'],
                'mime_type' => $info['mime'],
                'size_bytes' => $info['size'],
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $assignees */
    public function assignAssignees(string $caseId, array $assignees): array
    {
        if (!$this->repo->findById($caseId)) {
            return ['success' => false, 'errors' => ['Дело не найдено.']];
        }

        $updated = 0;
        $errors = [];

        foreach ($assignees as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $role = strtoupper(trim((string) ($row['role'] ?? CaseAssigneeRole::EXECUTOR->value)));
            $isPrimary = (int) ($row['is_primary'] ?? 0) === 1 || (bool) ($row['is_primary'] ?? false);

            if ($userId < 1) {
                $errors[] = 'user_id должен быть положительным числом.';
                continue;
            }
            if (!CaseAssigneeRole::tryFrom($role)) {
                $errors[] = "Некорректная роль исполнителя для user_id={$userId}.";
                continue;
            }
            if (!$this->repo->userExists($userId)) {
                $errors[] = "Пользователь {$userId} не найден или неактивен.";
                continue;
            }

            if ($isPrimary) {
                $this->repo->clearPrimaryRole($caseId, $role, $userId);
            }
            $this->repo->upsertAssignee($caseId, $userId, $role, $isPrimary);
            $updated++;
        }

        if ($updated > 0) {
            $this->app->audit('case_assignees_updated', 'case', null, ['case_id' => $caseId, 'count' => $updated]);
        }

        return ['success' => $updated > 0, 'updated' => $updated, 'errors' => $errors];
    }

    public function delete(string $caseId): bool
    {
        if (!$this->repo->findById($caseId)) {
            return false;
        }

        foreach ($this->repo->filesByCaseId($caseId) as $file) {
            $path = trim((string) ($file['file_path'] ?? ''));
            if ($path !== '') {
                $this->storage->delete($path);
            }
        }

        $this->repo->delete($caseId);
        $this->app->audit('case_deleted', 'case', null, ['case_id' => $caseId]);
        return true;
    }

    /** @return array{0:array<string,mixed>,1:array<int,string>} */
    private function buildCasePayload(array $input): array
    {
        $errors = [];

        $blockType = strtoupper(trim((string) ($input['block_type'] ?? '')));
        if (!CaseBlockType::tryFrom($blockType)) {
            $errors[] = 'Некорректный block_type.';
        }

        $subjectRaw = $this->normalizeText($input['subject_raw'] ?? null);
        if ($subjectRaw === null) {
            $errors[] = 'Поле subject_raw обязательно.';
        }

        $year = $this->normalizeInt($input['year'] ?? null, 'year', $errors);
        $regNo = $this->normalizeInt($input['reg_no'] ?? null, 'reg_no', $errors);

        $taskDate = $this->normalizeDate($input['task_date'] ?? null, 'task_date', $errors);
        $dueDate = $this->normalizeDate($input['due_date'] ?? null, 'due_date', $errors);
        $contractDate = $this->normalizeDate($input['contract_date'] ?? null, 'contract_date', $errors);

        $resultRaw = $this->normalizeText($input['result_raw'] ?? null);
        $statusInput = strtoupper(trim((string) ($input['result_status'] ?? '')));
        $status = $statusInput !== '' ? $statusInput : null;
        if ($status !== null && !CaseResultStatus::tryFrom($status)) {
            $errors[] = 'Некорректный result_status.';
        }

        $resultAmount = $this->normalizeDecimal($input['result_amount'] ?? null, 'result_amount', $errors);
        $resultPercent = $this->normalizePercent($input['result_percent'] ?? null, 'result_percent', $errors);

        if ($resultRaw !== null) {
            $normalizedResult = $this->parseResultRaw($resultRaw);
            if ($status === null && isset($normalizedResult['status'])) {
                $status = $normalizedResult['status'];
            }
            if ($resultAmount === null && isset($normalizedResult['amount'])) {
                $resultAmount = $normalizedResult['amount'];
            }
            if ($resultPercent === null && isset($normalizedResult['percent'])) {
                $resultPercent = $normalizedResult['percent'];
            }
        }

        if ($status === null && $resultPercent !== null) {
            $status = $resultPercent >= 100 ? CaseResultStatus::DONE->value : CaseResultStatus::IN_PROGRESS->value;
        }

        if ($status !== null && !CaseResultStatus::tryFrom($status)) {
            $errors[] = 'Не удалось нормализовать result_status.';
        }

        $payload = [
            'block_type' => $blockType,
            'year' => $year ?? ($taskDate ? (int) substr($taskDate, 0, 4) : null),
            'reg_no' => $regNo,
            'case_code' => $this->normalizeText($input['case_code'] ?? null),
            'subject_raw' => $subjectRaw,
            'subject_clean' => $this->normalizeText($input['subject_clean'] ?? null),
            'budget_article' => $this->normalizeText($input['budget_article'] ?? null),
            'procurement_form' => $this->normalizeText($input['procurement_form'] ?? null),
            'amount_planned' => $this->normalizeDecimal($input['amount_planned'] ?? null, 'amount_planned', $errors),
            'rnmc_amount' => $this->normalizeDecimal($input['rnmc_amount'] ?? null, 'rnmc_amount', $errors),
            'task_date' => $taskDate,
            'stage_raw' => $this->normalizeText($input['stage_raw'] ?? null),
            'due_date' => $dueDate,
            'notes' => $this->normalizeText($input['notes'] ?? null),
            'archive_path' => $this->normalizeText($input['archive_path'] ?? null),
            'result_raw' => $resultRaw,
            'result_status' => $status,
            'result_amount' => $resultAmount,
            'result_percent' => $resultPercent,
            'contract_ref_raw' => $this->normalizeText($input['contract_ref_raw'] ?? null),
            'contract_number' => $this->normalizeText($input['contract_number'] ?? null),
            'contract_date' => $contractDate,
            'contract_amount' => $this->normalizeDecimal($input['contract_amount'] ?? null, 'contract_amount', $errors),
            'bundle_key' => $this->buildBundleKey($input),
        ];

        return [$payload, $errors];
    }

    private function buildBundleKey(array $input): ?string
    {
        $manual = $this->normalizeText($input['bundle_key'] ?? null);
        if ($manual !== null) {
            return $manual;
        }

        $archivePath = $this->normalizeText($input['archive_path'] ?? null);
        if ($archivePath !== null) {
            return $archivePath;
        }

        $caseCode = $this->normalizeText($input['case_code'] ?? null);
        $contractNumber = $this->normalizeText($input['contract_number'] ?? null);

        if ($caseCode === null && $contractNumber === null) {
            return null;
        }

        return ($caseCode ?? '') . '|' . ($contractNumber ?? '');
    }

    /** @return array{0:?string,1:?array{attr_value:?string,attr_value_num:?float,attr_value_date:?string}} */
    private function normalizeAttribute(mixed $key, mixed $value): array
    {
        $attrKey = is_string($key) ? trim($key) : '';
        $candidate = $value;

        if ($attrKey === '' && is_array($value)) {
            $attrKey = trim((string) ($value['attr_key'] ?? ''));
            $candidate = $value;
        }

        if (!preg_match('/^[a-z0-9_]{1,64}$/', $attrKey)) {
            return [null, null];
        }

        if (!is_array($candidate)) {
            return [$attrKey, [
                'attr_value' => $this->normalizeText($candidate),
                'attr_value_num' => null,
                'attr_value_date' => null,
            ]];
        }

        $errors = [];
        return [$attrKey, [
            'attr_value' => $this->normalizeText($candidate['attr_value'] ?? $candidate['value'] ?? null),
            'attr_value_num' => $this->normalizeDecimal($candidate['attr_value_num'] ?? $candidate['num'] ?? null, 'attr_value_num', $errors),
            'attr_value_date' => $this->normalizeDate($candidate['attr_value_date'] ?? $candidate['date'] ?? null, 'attr_value_date', $errors),
        ]];
    }

    /** @return array{status?:string,amount?:float,percent?:int} */
    private function parseResultRaw(string $resultRaw): array
    {
        $normalized = [];
        $lower = function_exists('mb_strtolower') ? mb_strtolower($resultRaw) : strtolower($resultRaw);

        if (preg_match('/без\s+исполн|не\s+требуе|поставлен\w*\s+вовремя/u', $lower)) {
            $normalized['status'] = CaseResultStatus::NO_ACTION->value;
        } elseif (preg_match('/отмен|снят/u', $lower)) {
            $normalized['status'] = CaseResultStatus::CANCELLED->value;
        } elseif (preg_match('/в\s*работе|частич|исполня/u', $lower)) {
            $normalized['status'] = CaseResultStatus::IN_PROGRESS->value;
        } elseif (preg_match('/исполн|выполн|закрыт/u', $lower)) {
            $normalized['status'] = CaseResultStatus::DONE->value;
        } elseif (preg_match('/нов\w*|не\s*начат/u', $lower)) {
            $normalized['status'] = CaseResultStatus::NEW->value;
        }

        if (preg_match('/(\d{1,3})\s*%/u', $resultRaw, $percentMatch)) {
            $normalized['percent'] = max(0, min(100, (int) $percentMatch[1]));
        }

        $withoutPercent = preg_replace('/\d{1,3}\s*%/u', '', $resultRaw) ?? $resultRaw;
        if (preg_match_all('/-?\d[\d\s\x{00A0}]*(?:[.,]\d{1,2})?/u', $withoutPercent, $matches)) {
            foreach ($matches[0] as $candidate) {
                $parseErrors = [];
                $amount = $this->normalizeDecimal($candidate, 'result_raw', $parseErrors);
                if ($amount === null) {
                    continue;
                }
                if (isset($normalized['percent']) && abs($amount - (float) $normalized['percent']) < 0.0001) {
                    continue;
                }
                $normalized['amount'] = $amount;
                break;
            }
        }

        return $normalized;
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function normalizeInt(mixed $value, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            $errors[] = "Поле {$field} должно быть числом.";
            return null;
        }
        return (int) $value;
    }

    private function normalizePercent(mixed $value, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            $errors[] = "Поле {$field} должно быть числом.";
            return null;
        }

        $percent = (int) $value;
        if ($percent < 0 || $percent > 100) {
            $errors[] = "Поле {$field} должно быть в диапазоне 0..100.";
            return null;
        }

        return $percent;
    }

    private function normalizeDecimal(mixed $value, string $field, array &$errors): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $normalized = str_replace(["\xC2\xA0", ' '], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d.\-]/u', '', $normalized) ?? '';

        if ($normalized === '' || !is_numeric($normalized)) {
            $errors[] = "Поле {$field} должно быть числом.";
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function normalizeDate(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        $formats = ['Y-m-d', 'd.m.Y', 'Y-m-d H:i:s', 'd.m.Y H:i:s'];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        $errors[] = "Поле {$field} содержит некорректную дату.";
        return null;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
