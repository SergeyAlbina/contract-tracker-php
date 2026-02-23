#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Import normalized cases CSV into cases registry.
 *
 * Usage:
 *   php scripts/import_cases_csv.php [path/to/cases_import.csv] --dry-run
 *   php scripts/import_cases_csv.php [path/to/cases_import.csv] --apply
 */

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

const ALLOWED_BLOCK_TYPES = ['TASKS', 'TERMINATIONS', 'CLAIMS', 'CONCLUDED', 'APPROVED_FZ'];
const ALLOWED_RESULT_STATUSES = ['NEW', 'IN_PROGRESS', 'DONE', 'NO_ACTION', 'CANCELLED'];

function stderr(string $text): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

function normalize_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function normalize_int(mixed $value): ?int
{
    $text = normalize_text($value);
    if ($text === null) {
        return null;
    }
    if (!preg_match('/^-?\d+$/', $text)) {
        return null;
    }
    return (int) $text;
}

function normalize_decimal(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return round((float) $value, 2);
    }
    $text = str_replace("\xC2\xA0", ' ', (string) $value);
    $text = str_replace([' ', ','], ['', '.'], $text);
    $text = preg_replace('/[^\d.\-]/u', '', $text) ?? '';
    if ($text === '' || !is_numeric($text)) {
        return null;
    }
    return round((float) $text, 2);
}

function normalize_percent(mixed $value): ?int
{
    $num = normalize_int($value);
    if ($num === null) {
        return null;
    }
    if ($num < 0 || $num > 100) {
        return null;
    }
    return $num;
}

function normalize_date(mixed $value): ?string
{
    $raw = normalize_text($value);
    if ($raw === null) {
        return null;
    }

    $formats = ['Y-m-d', 'd.m.Y', 'Y-m-d H:i:s', 'd.m.Y H:i:s', 'd.m.y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $raw);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    if (preg_match('/(\d{2}\.\d{2}\.\d{4})/', $raw, $m)) {
        $dt = DateTimeImmutable::createFromFormat('d.m.Y', $m[1]);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function infer_status(?string $resultRaw): ?string
{
    if ($resultRaw === null) {
        return null;
    }
    $text = function_exists('mb_strtolower') ? mb_strtolower($resultRaw) : strtolower($resultRaw);

    if (preg_match('/без\s+исполн|не\s+требуе/u', $text)) {
        return 'NO_ACTION';
    }
    if (preg_match('/отмен|снят/u', $text)) {
        return 'CANCELLED';
    }
    if (preg_match('/неисполн|нов|не\s*начат/u', $text)) {
        return 'NEW';
    }
    if (preg_match('/в\s*работе|частич|исполня|передан/u', $text)) {
        return 'IN_PROGRESS';
    }
    if (preg_match('/исполн|выполн|закрыт/u', $text)) {
        return 'DONE';
    }
    return null;
}

function normalize_person_key(string $value): string
{
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function split_assignees(?string $value): array
{
    if ($value === null) {
        return [];
    }
    $text = trim($value);
    if ($text === '') {
        return [];
    }

    $text = preg_replace('/\s*(\/|;|,|\sи\s)\s*/iu', ';', $text) ?? $text;
    $parts = array_values(array_filter(array_map(
        static function (string $x): string {
            return trim($x);
        },
        explode(';', $text)
    )));

    $unique = [];
    $seen = [];
    foreach ($parts as $part) {
        $key = normalize_person_key($part);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $part;
    }
    return $unique;
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
    $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function fallback_bundle_key(array $row): string
{
    $seed = implode('|', array_filter([
        normalize_text($row['source_file'] ?? null),
        normalize_text($row['source_sheet'] ?? null),
        normalize_text($row['source_row'] ?? null),
        normalize_text($row['block_type'] ?? null),
        normalize_text($row['case_code'] ?? null),
        normalize_text($row['subject_raw'] ?? null),
        normalize_text($row['contract_number'] ?? null),
    ]));
    if ($seed === '') {
        $seed = json_encode($row, JSON_UNESCAPED_UNICODE) ?: 'empty';
    }
    return 'import|' . substr(sha1($seed), 0, 20);
}

function fetch_users_map(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, login, full_name FROM users WHERE is_active = 1');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [
        'by_full_name' => [],
        'by_login' => [],
        'all_names' => [],
    ];

    foreach ($users as $user) {
        $id = (int) $user['id'];
        $full = trim((string) ($user['full_name'] ?? ''));
        $login = trim((string) ($user['login'] ?? ''));

        if ($full !== '') {
            $key = normalize_person_key($full);
            if (!isset($map['by_full_name'][$key])) {
                $map['by_full_name'][$key] = [];
            }
            $map['by_full_name'][$key][] = $id;
            $map['all_names'][$id] = $full;
        }
        if ($login !== '') {
            $map['by_login'][normalize_person_key($login)] = $id;
        }
    }

    return $map;
}

function resolve_assignee_id(string $name, array $usersMap): ?int
{
    $key = normalize_person_key($name);

    if (isset($usersMap['by_login'][$key])) {
        return (int) $usersMap['by_login'][$key];
    }

    $full = $usersMap['by_full_name'][$key] ?? null;
    if (is_array($full) && count($full) === 1) {
        return (int) $full[0];
    }

    // fallback: unique substring match by full name
    $matches = [];
    foreach ($usersMap['all_names'] as $id => $fullName) {
        $fullNameKey = normalize_person_key($fullName);
        if (str_contains($fullNameKey, $key) || str_contains($key, $fullNameKey)) {
            $matches[] = (int) $id;
        }
    }

    if (count($matches) === 1) {
        return $matches[0];
    }

    return null;
}

function to_row_map(array $headers, array $values): array
{
    $row = [];
    foreach ($headers as $index => $header) {
        $row[$header] = $values[$index] ?? null;
    }
    return $row;
}

function read_csv_rows(string $path): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException("Не удалось открыть CSV: {$path}");
    }

    $headers = fgetcsv($fh);
    if ($headers === false) {
        fclose($fh);
        return [];
    }

    // remove UTF-8 BOM from first header if present
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
    }
    $headers = array_map(
        static function ($h): string {
            return trim((string) $h);
        },
        $headers
    );

    $rows = [];
    while (($values = fgetcsv($fh)) !== false) {
        if ($values === [null] || $values === []) {
            continue;
        }
        $rows[] = to_row_map($headers, $values);
    }
    fclose($fh);
    return $rows;
}

function usage(): void
{
    $help = <<<TXT
Usage:
  php scripts/import_cases_csv.php [csv_path] --dry-run
  php scripts/import_cases_csv.php [csv_path] --apply

Options:
  --dry-run   Parse and validate only, DB is not modified (default)
  --apply     Write changes to DB
TXT;
    echo $help . PHP_EOL;
}

$args = $argv;
array_shift($args);

$mode = 'dry-run';
$csvPath = null;

foreach ($args as $arg) {
    if ($arg === '--apply') {
        $mode = 'apply';
        continue;
    }
    if ($arg === '--dry-run') {
        $mode = 'dry-run';
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        usage();
        exit(0);
    }
    if (!str_starts_with($arg, '--') && $csvPath === null) {
        $csvPath = $arg;
        continue;
    }
}

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    stderr('Не удалось определить корень проекта.');
    exit(1);
}

if ($csvPath === null) {
    $csvPath = $projectRoot . '/output/spreadsheet/cases_import.csv';
} elseif (!str_starts_with($csvPath, '/')) {
    $csvPath = $projectRoot . '/' . $csvPath;
}

if (!is_file($csvPath)) {
    stderr("CSV файл не найден: {$csvPath}");
    exit(1);
}

Env::load($projectRoot . '/.env');
$pdo = PdoFactory::create();

$rows = read_csv_rows($csvPath);
$usersMap = fetch_users_map($pdo);

$stats = [
    'mode' => $mode,
    'csv_path' => $csvPath,
    'rows_total' => count($rows),
    'rows_skipped' => 0,
    'rows_invalid' => 0,
    'rows_inserted' => 0,
    'rows_updated' => 0,
    'assignees_mapped' => 0,
    'assignees_unmapped' => 0,
    'assignees_unmapped_values' => [],
    'errors' => [],
];

$findByBundle = $pdo->prepare(
    'SELECT id FROM cases WHERE bundle_key = :bundle_key ORDER BY created_at ASC LIMIT 1'
);

$insertSql = 'INSERT INTO cases (
    id, block_type, year, reg_no, case_code, subject_raw, subject_clean, budget_article, procurement_form,
    amount_planned, rnmc_amount, task_date, stage_raw, due_date, notes, archive_path, result_raw, result_status,
    result_amount, result_percent, contract_ref_raw, contract_number, contract_date, contract_amount, bundle_key, duplicate_of_case_id
) VALUES (
    :id, :block_type, :year, :reg_no, :case_code, :subject_raw, :subject_clean, :budget_article, :procurement_form,
    :amount_planned, :rnmc_amount, :task_date, :stage_raw, :due_date, :notes, :archive_path, :result_raw, :result_status,
    :result_amount, :result_percent, :contract_ref_raw, :contract_number, :contract_date, :contract_amount, :bundle_key, NULL
)';
$insertCase = $pdo->prepare($insertSql);

$updateSql = 'UPDATE cases SET
    block_type = :block_type,
    year = :year,
    reg_no = :reg_no,
    case_code = :case_code,
    subject_raw = :subject_raw,
    subject_clean = :subject_clean,
    budget_article = :budget_article,
    procurement_form = :procurement_form,
    amount_planned = :amount_planned,
    rnmc_amount = :rnmc_amount,
    task_date = :task_date,
    stage_raw = :stage_raw,
    due_date = :due_date,
    notes = :notes,
    archive_path = :archive_path,
    result_raw = :result_raw,
    result_status = :result_status,
    result_amount = :result_amount,
    result_percent = :result_percent,
    contract_ref_raw = :contract_ref_raw,
    contract_number = :contract_number,
    contract_date = :contract_date,
    contract_amount = :contract_amount
WHERE id = :id';
$updateCase = $pdo->prepare($updateSql);

$deleteAssignees = $pdo->prepare('DELETE FROM case_assignees WHERE case_id = :case_id');
$insertAssignee = $pdo->prepare(
    'INSERT INTO case_assignees (case_id, user_id, role, is_primary)
     VALUES (:case_id, :user_id, :role, :is_primary)
     ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)'
);

$apply = ($mode === 'apply');

foreach ($rows as $index => $row) {
    $lineNo = $index + 2;

    $blockType = strtoupper((string) (normalize_text($row['block_type'] ?? null) ?? ''));
    if (!in_array($blockType, ALLOWED_BLOCK_TYPES, true)) {
        $stats['rows_invalid']++;
        $stats['errors'][] = "line {$lineNo}: invalid block_type";
        continue;
    }

    $subjectRaw = normalize_text($row['subject_raw'] ?? null);
    if ($subjectRaw === null) {
        $stats['rows_invalid']++;
        $stats['errors'][] = "line {$lineNo}: missing subject_raw";
        continue;
    }

    $resultStatus = strtoupper((string) (normalize_text($row['result_status'] ?? null) ?? ''));
    if ($resultStatus === '') {
        $resultStatus = infer_status(normalize_text($row['result_raw'] ?? null)) ?? '';
    }
    if ($resultStatus !== '' && !in_array($resultStatus, ALLOWED_RESULT_STATUSES, true)) {
        $resultStatus = '';
    }

    $data = [
        'block_type' => $blockType,
        'year' => normalize_int($row['year'] ?? null),
        'reg_no' => normalize_int($row['reg_no'] ?? null),
        'case_code' => normalize_text($row['case_code'] ?? null),
        'subject_raw' => $subjectRaw,
        'subject_clean' => normalize_text($row['subject_clean'] ?? null),
        'budget_article' => normalize_text($row['budget_article'] ?? null),
        'procurement_form' => normalize_text($row['procurement_form'] ?? null),
        'amount_planned' => normalize_decimal($row['amount_planned'] ?? null),
        'rnmc_amount' => normalize_decimal($row['rnmc_amount'] ?? null),
        'task_date' => normalize_date($row['task_date'] ?? null),
        'stage_raw' => normalize_text($row['stage_raw'] ?? null),
        'due_date' => normalize_date($row['due_date'] ?? null),
        'notes' => normalize_text($row['notes'] ?? null),
        'archive_path' => normalize_text($row['archive_path'] ?? null),
        'result_raw' => normalize_text($row['result_raw'] ?? null),
        'result_status' => $resultStatus !== '' ? $resultStatus : null,
        'result_amount' => normalize_decimal($row['result_amount'] ?? null),
        'result_percent' => normalize_percent($row['result_percent'] ?? null),
        'contract_ref_raw' => normalize_text($row['contract_ref_raw'] ?? null),
        'contract_number' => normalize_text($row['contract_number'] ?? null),
        'contract_date' => normalize_date($row['contract_date'] ?? null),
        'contract_amount' => normalize_decimal($row['contract_amount'] ?? null),
        'bundle_key' => normalize_text($row['bundle_key'] ?? null) ?? fallback_bundle_key($row),
    ];

    if ($data['year'] === null && $data['task_date'] !== null) {
        $data['year'] = (int) substr($data['task_date'], 0, 4);
    }

    $caseId = null;
    try {
        $findByBundle->execute(['bundle_key' => $data['bundle_key']]);
        $found = $findByBundle->fetchColumn();
        $caseId = $found ? (string) $found : null;
    } catch (Throwable $e) {
        $stats['rows_invalid']++;
        $stats['errors'][] = "line {$lineNo}: failed finding bundle_key ({$e->getMessage()})";
        continue;
    }

    if (!$apply) {
        if ($caseId === null) {
            $stats['rows_inserted']++;
        } else {
            $stats['rows_updated']++;
        }
    } else {
        try {
            if ($caseId === null) {
                $data['id'] = uuid_v4();
                $insertCase->execute($data);
                $caseId = (string) $data['id'];
                $stats['rows_inserted']++;
            } else {
                $data['id'] = $caseId;
                $updateCase->execute($data);
                $stats['rows_updated']++;
            }
        } catch (Throwable $e) {
            $stats['rows_invalid']++;
            $stats['errors'][] = "line {$lineNo}: DB write failed ({$e->getMessage()})";
            continue;
        }
    }

    $assignees = split_assignees(normalize_text($row['assignees_text'] ?? null));
    if ($assignees === []) {
        continue;
    }

    $resolvedIds = [];
    foreach ($assignees as $name) {
        $userId = resolve_assignee_id($name, $usersMap);
        if ($userId === null) {
            $stats['assignees_unmapped']++;
            $stats['assignees_unmapped_values'][$name] = ($stats['assignees_unmapped_values'][$name] ?? 0) + 1;
            continue;
        }
        if (!in_array($userId, $resolvedIds, true)) {
            $resolvedIds[] = $userId;
            $stats['assignees_mapped']++;
        }
    }

    if (!$apply || $caseId === null || $resolvedIds === []) {
        continue;
    }

    try {
        $deleteAssignees->execute(['case_id' => $caseId]);
        foreach ($resolvedIds as $idxResolved => $userId) {
            $insertAssignee->execute([
                'case_id' => $caseId,
                'user_id' => $userId,
                'role' => 'EXECUTOR',
                'is_primary' => $idxResolved === 0 ? 1 : 0,
            ]);
        }
    } catch (Throwable $e) {
        $stats['errors'][] = "line {$lineNo}: failed assignee sync ({$e->getMessage()})";
    }
}

arsort($stats['assignees_unmapped_values']);
$stats['assignees_unmapped_values'] = array_slice($stats['assignees_unmapped_values'], 0, 20, true);
$stats['errors'] = array_slice($stats['errors'], 0, 200);

echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
