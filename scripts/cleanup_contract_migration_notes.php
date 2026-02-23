#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();

$stmt = $pdo->query(
    "SELECT id, notes
     FROM contracts
     WHERE notes LIKE '%Перенесено из дел:%'
        OR notes LIKE '%source_case_id=%'
        OR notes LIKE '%bundle=%'"
);

$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'rows_scanned' => count($rows),
    'rows_changed' => 0,
    'rows_cleared' => 0,
    'sample' => [],
];

$updateStmt = $pdo->prepare('UPDATE contracts SET notes = :notes, updated_at = NOW() WHERE id = :id');

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $original = (string) ($row['notes'] ?? '');
    $cleaned = sanitizeNotes($original);

    if ($cleaned === $original) {
        continue;
    }

    $metrics['rows_changed']++;
    if ($cleaned === null) {
        $metrics['rows_cleared']++;
    }

    if ($apply) {
        $updateStmt->execute([
            'id' => $id,
            'notes' => $cleaned,
        ]);
    }

    if (count($metrics['sample']) < 15) {
        $metrics['sample'][] = [
            'id' => $id,
            'before' => preview($original),
            'after' => preview($cleaned ?? ''),
        ];
    }
}

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function sanitizeNotes(string $notes): ?string
{
    $lines = preg_split('/\R/u', $notes) ?: [];
    $result = [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }
        if (preg_match('/^Перенесено из дел:/ui', $trimmed)) {
            continue;
        }
        if (preg_match('/^source_case_id=/ui', $trimmed)) {
            continue;
        }
        if (preg_match('/^bundle=/ui', $trimmed)) {
            continue;
        }
        $result[] = $trimmed;
    }

    if ($result === []) {
        return null;
    }

    return implode(PHP_EOL, $result);
}

function preview(string $text): string
{
    $flat = preg_replace('/\s+/u', ' ', trim($text)) ?: '';
    if ($flat === '') {
        return '';
    }
    return function_exists('mb_substr') ? mb_substr($flat, 0, 130) : substr($flat, 0, 130);
}
