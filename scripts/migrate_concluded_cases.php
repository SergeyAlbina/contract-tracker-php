#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$keepSource = in_array('--keep-source', $argv, true);

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();

$adminIdStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
$defaultAdminId = $adminIdStmt->fetchColumn();
$defaultAdminId = $defaultAdminId !== false ? (int) $defaultAdminId : null;

$cases = $pdo->query("SELECT * FROM cases WHERE block_type = 'CONCLUDED' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'keep_source' => $keepSource,
    'source_rows' => count($cases),
    'created_contracts' => 0,
    'updated_contracts' => 0,
    'deleted_source_rows' => 0,
    'skipped_rows' => 0,
    'contracts_total_after' => 0,
    'cases_concluded_after' => 0,
    'sample' => [],
];

foreach ($cases as $row) {
    $caseId = (string) $row['id'];

    $number = trim((string) ($row['contract_number'] ?? ''));
    if ($number === '') {
        $number = trim((string) ($row['case_code'] ?? ''));
    }
    if ($number === '') {
        $year = (int) ($row['year'] ?? 0);
        $regNo = (int) ($row['reg_no'] ?? 0);
        $suffix = $regNo > 0 ? (string) $regNo : substr($caseId, 0, 8);
        $number = 'CONCLUDED-' . ($year > 0 ? $year : date('Y')) . '-' . $suffix;
    }

    $subject = trim((string) ($row['subject_raw'] ?? ''));
    if ($subject === '') {
        $subject = 'Без предмета (перенос из блока CONCLUDED)';
    }

    $notesParts = [];
    $existingNotes = trim((string) ($row['notes'] ?? ''));
    if ($existingNotes !== '') {
        $notesParts[] = $existingNotes;
    }
    $notes = implode(PHP_EOL, $notesParts);

    $totalAmount = firstPositiveFloat([
        $row['contract_amount'] ?? null,
        $row['result_amount'] ?? null,
        $row['amount_planned'] ?? null,
    ]);
    $nmckAmount = firstPositiveFloat([$row['rnmc_amount'] ?? null], allowZero: false);
    $lawType = detectLawType(
        trim((string) ($row['procurement_form'] ?? '')) . ' ' .
        trim((string) ($row['contract_ref_raw'] ?? '')) . ' ' .
        $subject
    );

    $signedAt = normalizeDate($row['contract_date'] ?? null) ?? normalizeDate($row['task_date'] ?? null);
    $expiresAt = normalizeDate($row['due_date'] ?? null);

    $existingStmt = $pdo->prepare('SELECT * FROM contracts WHERE number = :number ORDER BY id ASC LIMIT 1');
    $existingStmt->execute(['number' => $number]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$existing) {
        $payload = [
            'number' => $number,
            'subject' => $subject,
            'law_type' => $lawType,
            'contractor_name' => 'Контрагент не указан',
            'contractor_inn' => null,
            'total_amount' => $totalAmount,
            'nmck_amount' => $nmckAmount,
            'currency' => 'RUB',
            'status' => 'executed',
            'signed_at' => $signedAt,
            'expires_at' => $expiresAt,
            'notes' => $notes,
            'created_by' => $defaultAdminId,
        ];

        if ($apply) {
            $columns = array_keys($payload);
            $sql = 'INSERT INTO contracts (' . implode(', ', $columns) . ', created_at, updated_at) VALUES ('
                . implode(', ', array_map(static fn(string $col): string => ':' . $col, $columns))
                . ', NOW(), NOW())';
            $pdo->prepare($sql)->execute($payload);
        }
        $metrics['created_contracts']++;
    } else {
        $update = ['status' => 'executed'];

        if ((float) ($existing['total_amount'] ?? 0) <= 0.0 && $totalAmount > 0.0) {
            $update['total_amount'] = $totalAmount;
        }
        if (($existing['nmck_amount'] ?? null) === null && $nmckAmount > 0.0) {
            $update['nmck_amount'] = $nmckAmount;
        }
        if (($existing['signed_at'] ?? null) === null && $signedAt !== null) {
            $update['signed_at'] = $signedAt;
        }
        if (($existing['expires_at'] ?? null) === null && $expiresAt !== null) {
            $update['expires_at'] = $expiresAt;
        }
        if (trim((string) ($existing['subject'] ?? '')) === '' && $subject !== '') {
            $update['subject'] = $subject;
        }
        if (trim((string) ($existing['notes'] ?? '')) === '' && $notes !== '') {
            $update['notes'] = $notes;
        }

        if ($apply && $update !== []) {
            $set = [];
            foreach (array_keys($update) as $field) {
                $set[] = "{$field} = :{$field}";
            }
            $update['_id'] = (int) $existing['id'];
            $sql = 'UPDATE contracts SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :_id';
            $pdo->prepare($sql)->execute($update);
        }
        $metrics['updated_contracts']++;
    }

    if ($apply && !$keepSource) {
        $deleteStmt = $pdo->prepare("DELETE FROM cases WHERE id = :id AND block_type = 'CONCLUDED'");
        $deleteStmt->execute(['id' => $caseId]);
        $metrics['deleted_source_rows'] += $deleteStmt->rowCount();
    }

    if (count($metrics['sample']) < 10) {
        $shortSubject = function_exists('mb_substr') ? mb_substr($subject, 0, 80) : substr($subject, 0, 80);
        $metrics['sample'][] = [
            'case_id' => $caseId,
            'contract_number' => $number,
            'subject' => $shortSubject,
        ];
    }
}

$metrics['contracts_total_after'] = (int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn();
$metrics['cases_concluded_after'] = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE block_type = 'CONCLUDED'")->fetchColumn();

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function firstPositiveFloat(array $values, bool $allowZero = false): float
{
    foreach ($values as $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $number = (float) $value;
        if ($allowZero && $number >= 0.0) {
            return round($number, 2);
        }
        if (!$allowZero && $number > 0.0) {
            return round($number, 2);
        }
    }
    return 0.0;
}

function detectLawType(string $text): string
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    return (str_contains($normalized, '44') || str_contains($normalized, '44-фз')) ? '44' : '223';
}

function normalizeDate(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $formats = ['Y-m-d', 'd.m.Y', 'd.m.y', 'Y-m-d H:i:s', 'd.m.Y H:i:s'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $text);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}
