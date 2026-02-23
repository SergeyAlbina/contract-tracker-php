#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();

$mergeFields = [
    'year',
    'reg_no',
    'case_code',
    'subject_raw',
    'subject_clean',
    'budget_article',
    'procurement_form',
    'amount_planned',
    'rnmc_amount',
    'task_date',
    'stage_raw',
    'due_date',
    'notes',
    'archive_path',
    'result_raw',
    'result_status',
    'result_amount',
    'result_percent',
    'contract_ref_raw',
    'contract_number',
    'contract_date',
    'contract_amount',
];

$numericFields = [
    'year',
    'reg_no',
    'amount_planned',
    'rnmc_amount',
    'result_amount',
    'result_percent',
    'contract_amount',
];

$dateFields = ['task_date', 'due_date', 'contract_date'];
$statusPriority = ['CANCELLED' => 1, 'NO_ACTION' => 2, 'NEW' => 3, 'IN_PROGRESS' => 4, 'DONE' => 5];

$bundleStmt = $pdo->query(
    "SELECT bundle_key
     FROM cases
     WHERE bundle_key IS NOT NULL AND bundle_key <> ''
     GROUP BY bundle_key
     HAVING COUNT(*) > 1
     ORDER BY bundle_key ASC"
);
$bundleKeys = array_map(
    static fn(array $row): string => (string) $row['bundle_key'],
    $bundleStmt->fetchAll(PDO::FETCH_ASSOC)
);

$metrics = [
    'mode' => $dryRun ? 'dry-run' : 'apply',
    'bundles_found' => count($bundleKeys),
    'rows_examined' => 0,
    'rows_deleted' => 0,
    'rows_remaining_after' => 0,
    'bundles_merged' => 0,
    'child_rows_moved' => [
        'attributes' => 0,
        'assignees' => 0,
        'events' => 0,
        'files' => 0,
    ],
    'details' => [],
];

foreach ($bundleKeys as $bundleKey) {
    $rowsStmt = $pdo->prepare('SELECT * FROM cases WHERE bundle_key = :bundle ORDER BY created_at ASC, id ASC');
    $rowsStmt->execute(['bundle' => $bundleKey]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 2) {
        continue;
    }

    $metrics['rows_examined'] += count($rows);
    $metrics['bundles_merged']++;

    $primary = choosePrimaryRow($rows, $mergeFields, $statusPriority);
    $primaryId = (string) $primary['id'];
    $others = array_values(array_filter(
        $rows,
        static fn(array $row): bool => (string) $row['id'] !== $primaryId
    ));

    $updatePayload = buildMergedPayload($rows, $primary, $mergeFields, $numericFields, $dateFields, $statusPriority);

    $bundleReport = [
        'bundle_key' => $bundleKey,
        'primary_id' => $primaryId,
        'rows_in_bundle' => count($rows),
        'rows_to_delete' => count($others),
        'update_fields' => array_keys($updatePayload),
    ];

    if (!$dryRun) {
        $pdo->beginTransaction();
        try {
            if ($updatePayload !== []) {
                $set = [];
                foreach (array_keys($updatePayload) as $field) {
                    $set[] = "{$field} = :{$field}";
                }
                $updatePayload['_id'] = $primaryId;
                $sql = 'UPDATE cases SET ' . implode(', ', $set) . ' WHERE id = :_id';
                $pdo->prepare($sql)->execute($updatePayload);
            }

            foreach ($others as $row) {
                $dupId = (string) $row['id'];
                $moved = moveChildRows($pdo, $dupId, $primaryId);
                foreach ($moved as $key => $value) {
                    $metrics['child_rows_moved'][$key] += $value;
                }

                $pdo->prepare('UPDATE cases SET duplicate_of_case_id = :to WHERE duplicate_of_case_id = :from')
                    ->execute(['to' => $primaryId, 'from' => $dupId]);
                $pdo->prepare('DELETE FROM cases WHERE id = :id')->execute(['id' => $dupId]);
                $metrics['rows_deleted']++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $bundleReport['error'] = $e->getMessage();
            $metrics['details'][] = $bundleReport;
            continue;
        }
    }

    $metrics['details'][] = $bundleReport;
}

$metrics['rows_remaining_after'] = (int) $pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function isFilled(mixed $value): bool
{
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return true;
}

function completenessScore(array $row, array $fields, array $statusPriority): int
{
    $score = 0;
    foreach ($fields as $field) {
        if (!array_key_exists($field, $row) || !isFilled($row[$field])) {
            continue;
        }
        $score += 1;
        if (in_array($field, ['subject_raw', 'subject_clean', 'notes', 'archive_path', 'contract_ref_raw', 'contract_number'], true)) {
            $score += min(5, (int) floor(strlen((string) $row[$field]) / 35));
        }
    }

    $status = (string) ($row['result_status'] ?? '');
    $score += $statusPriority[$status] ?? 0;
    return $score;
}

function choosePrimaryRow(array $rows, array $fields, array $statusPriority): array
{
    $best = $rows[0];
    $bestScore = completenessScore($best, $fields, $statusPriority);

    foreach ($rows as $row) {
        $score = completenessScore($row, $fields, $statusPriority);
        if ($score > $bestScore) {
            $best = $row;
            $bestScore = $score;
            continue;
        }

        if ($score === $bestScore && (string) $row['created_at'] < (string) $best['created_at']) {
            $best = $row;
            $bestScore = $score;
        }
    }

    return $best;
}

function shouldReplaceValue(
    mixed $current,
    mixed $candidate,
    string $field,
    array $numericFields,
    array $dateFields,
    array $statusPriority
): bool {
    $currentFilled = isFilled($current);
    $candidateFilled = isFilled($candidate);

    if (!$candidateFilled) {
        return false;
    }
    if (!$currentFilled) {
        return true;
    }

    if ($field === 'result_status') {
        $curP = $statusPriority[(string) $current] ?? 0;
        $canP = $statusPriority[(string) $candidate] ?? 0;
        return $canP > $curP;
    }

    if (in_array($field, $numericFields, true)) {
        return (float) $candidate > (float) $current && (float) $current <= 0.0;
    }

    if (in_array($field, $dateFields, true)) {
        return false;
    }

    $curLen = strlen(trim((string) $current));
    $canLen = strlen(trim((string) $candidate));
    return $canLen > ($curLen + 8);
}

function buildMergedPayload(
    array $rows,
    array $primary,
    array $fields,
    array $numericFields,
    array $dateFields,
    array $statusPriority
): array {
    $payload = [];
    foreach ($fields as $field) {
        $best = $primary[$field] ?? null;
        foreach ($rows as $row) {
            $candidate = $row[$field] ?? null;
            if (shouldReplaceValue($best, $candidate, $field, $numericFields, $dateFields, $statusPriority)) {
                $best = $candidate;
            }
        }

        $primaryValue = $primary[$field] ?? null;
        if ($best !== $primaryValue) {
            $payload[$field] = $best;
        }
    }
    return $payload;
}

function moveChildRows(PDO $pdo, string $fromCaseId, string $toCaseId): array
{
    $result = ['attributes' => 0, 'assignees' => 0, 'events' => 0, 'files' => 0];

    $countAttributesStmt = $pdo->prepare('SELECT COUNT(*) FROM case_attributes WHERE case_id = :id');
    $countAttributesStmt->execute(['id' => $fromCaseId]);
    $countAttributes = (int) $countAttributesStmt->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO case_attributes (case_id, attr_key, attr_value, attr_value_num, attr_value_date)
         SELECT :to_id, attr_key, attr_value, attr_value_num, attr_value_date
         FROM case_attributes
         WHERE case_id = :from_id
         ON DUPLICATE KEY UPDATE
           attr_value = COALESCE(case_attributes.attr_value, VALUES(attr_value)),
           attr_value_num = COALESCE(case_attributes.attr_value_num, VALUES(attr_value_num)),
           attr_value_date = COALESCE(case_attributes.attr_value_date, VALUES(attr_value_date))'
    );
    $stmt->execute(['to_id' => $toCaseId, 'from_id' => $fromCaseId]);
    $pdo->prepare('DELETE FROM case_attributes WHERE case_id = :id')->execute(['id' => $fromCaseId]);
    $result['attributes'] = (int) $countAttributes;

    $countAssigneesStmt = $pdo->prepare('SELECT COUNT(*) FROM case_assignees WHERE case_id = :id');
    $countAssigneesStmt->execute(['id' => $fromCaseId]);
    $result['assignees'] = (int) $countAssigneesStmt->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO case_assignees (case_id, user_id, role, is_primary)
         SELECT :to_id, user_id, role, is_primary
         FROM case_assignees
         WHERE case_id = :from_id
         ON DUPLICATE KEY UPDATE is_primary = GREATEST(case_assignees.is_primary, VALUES(is_primary))'
    );
    $stmt->execute(['to_id' => $toCaseId, 'from_id' => $fromCaseId]);
    $pdo->prepare('DELETE FROM case_assignees WHERE case_id = :id')->execute(['id' => $fromCaseId]);

    $eventsStmt = $pdo->prepare('UPDATE case_events SET case_id = :to_id WHERE case_id = :from_id');
    $eventsStmt->execute(['to_id' => $toCaseId, 'from_id' => $fromCaseId]);
    $result['events'] = $eventsStmt->rowCount();

    $filesStmt = $pdo->prepare('UPDATE case_files SET case_id = :to_id WHERE case_id = :from_id');
    $filesStmt->execute(['to_id' => $toCaseId, 'from_id' => $fromCaseId]);
    $result['files'] = $filesStmt->rowCount();

    return $result;
}
