#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$apply = in_array('--apply', $_SERVER['argv'] ?? [], true);
$verbose = in_array('--verbose', $_SERVER['argv'] ?? [], true);
$limit = null;
foreach (($_SERVER['argv'] ?? []) as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();
$basePath = dirname(__DIR__);
$storagePath = resolveStoragePath($basePath);

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

$sql = <<<SQL
SELECT
  c.id AS contract_id,
  c.number,
  c.contractor_name,
  d.id AS document_id,
  d.original_name,
  d.relative_path,
  d.doc_type
FROM contracts c
LEFT JOIN documents d ON d.contract_id = c.id
WHERE c.contractor_name IS NULL OR c.contractor_name = '' OR c.contractor_name = 'Контрагент не указан'
ORDER BY
  c.id ASC,
  CASE d.doc_type
    WHEN 'contract' THEN 0
    WHEN 'supplement' THEN 1
    ELSE 2
  END,
  CASE LOWER(SUBSTRING_INDEX(d.original_name, '.', -1))
    WHEN 'html' THEN 0
    WHEN 'htm' THEN 1
    WHEN 'docx' THEN 2
    WHEN 'pdf' THEN 3
    WHEN 'doc' THEN 4
    ELSE 5
  END,
  d.id ASC
SQL;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$contracts = [];
foreach ($rows as $row) {
    $contractId = (int) ($row['contract_id'] ?? 0);
    if ($contractId < 1) {
        continue;
    }
    if (!isset($contracts[$contractId])) {
        $contracts[$contractId] = [
            'id' => $contractId,
            'number' => (string) ($row['number'] ?? ''),
            'current' => (string) ($row['contractor_name'] ?? ''),
            'documents' => [],
        ];
    }

    $docId = $row['document_id'] ?? null;
    if ($docId !== null) {
        $contracts[$contractId]['documents'][] = [
            'id' => (int) $docId,
            'original_name' => (string) ($row['original_name'] ?? ''),
            'relative_path' => (string) ($row['relative_path'] ?? ''),
            'doc_type' => (string) ($row['doc_type'] ?? ''),
        ];
    }
}

if ($limit !== null) {
    $contracts = array_slice($contracts, 0, $limit, true);
}

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'contracts_total_checked' => count($contracts),
    'contracts_with_documents' => 0,
    'contracts_without_documents' => 0,
    'contracts_with_extracted_contractor' => 0,
    'contracts_updated' => 0,
    'contracts_no_match' => 0,
    'documents_processed' => 0,
    'documents_read_failed' => 0,
    'sample_updates' => [],
];

$updateStmt = $pdo->prepare("UPDATE contracts SET contractor_name = :name, updated_at = NOW() WHERE id = :id");
$auditStmt = $pdo->prepare(
    'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
     VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
);

foreach ($contracts as $contract) {
    $documents = $contract['documents'];
    if ($documents === []) {
        $metrics['contracts_without_documents']++;
        $metrics['contracts_no_match']++;
        continue;
    }

    $metrics['contracts_with_documents']++;
    $chosen = null;
    $chosenDoc = null;

    foreach ($documents as $doc) {
        $metrics['documents_processed']++;
        $absPath = $storagePath . '/' . ltrim((string) $doc['relative_path'], '/');
        $text = extractTextFromDocument($absPath, (string) $doc['original_name']);
        if ($text === null) {
            $metrics['documents_read_failed']++;
            continue;
        }

        $candidate = detectContractorName($text);
        if ($candidate === null) {
            continue;
        }

        $chosen = $candidate;
        $chosenDoc = $doc;
        break;
    }

    if ($chosen === null) {
        $metrics['contracts_no_match']++;
        continue;
    }

    $metrics['contracts_with_extracted_contractor']++;
    if ($apply) {
        $updateStmt->execute([
            'id' => $contract['id'],
            'name' => $chosen,
        ]);

        if ($adminId > 0 && $chosenDoc !== null) {
            $auditStmt->execute([
                'uid' => $adminId,
                'action' => 'contract_contractor_extracted',
                'etype' => 'contract',
                'eid' => (int) $contract['id'],
                'details' => json_encode([
                    'number' => $contract['number'],
                    'contractor_name' => $chosen,
                    'document_id' => (int) $chosenDoc['id'],
                    'document_name' => (string) $chosenDoc['original_name'],
                    'source' => 'auto_extract',
                ], JSON_UNESCAPED_UNICODE),
                'ip' => '',
                'ua' => 'extract_contractors_from_documents.php',
            ]);
        }

        $metrics['contracts_updated']++;
    }

    if ($verbose) {
        echo sprintf(
            "[%s] #%d %s => %s (%s)\n",
            $apply ? 'updated' : 'would-update',
            (int) $contract['id'],
            (string) $contract['number'],
            $chosen,
            (string) ($chosenDoc['original_name'] ?? 'n/a')
        );
    }

    if (count($metrics['sample_updates']) < 30) {
        $metrics['sample_updates'][] = [
            'contract_id' => (int) $contract['id'],
            'number' => (string) $contract['number'],
            'contractor_name' => $chosen,
            'document_name' => (string) ($chosenDoc['original_name'] ?? ''),
        ];
    }
}

$metrics['contracts_with_real_contractor_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_name IS NOT NULL AND contractor_name <> '' AND contractor_name <> 'Контрагент не указан'"
)->fetchColumn() ?: 0);
$metrics['contracts_without_contractor_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_name IS NULL OR contractor_name = '' OR contractor_name = 'Контрагент не указан'"
)->fetchColumn() ?: 0);

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function resolveStoragePath(string $basePath): string
{
    $storagePath = Env::get('STORAGE_PATH', 'storage');
    if (!str_starts_with($storagePath, '/')) {
        $storagePath = rtrim($basePath, '/') . '/' . ltrim($storagePath, '/');
    }
    return rtrim($storagePath, '/');
}

function extractTextFromDocument(string $absPath, string $originalName): ?string
{
    if (!is_file($absPath)) {
        return null;
    }

    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = strtolower((string) pathinfo($absPath, PATHINFO_EXTENSION));
    }

    return match ($ext) {
        'html', 'htm' => extractFromHtml($absPath),
        'docx' => extractFromDocx($absPath),
        'pdf' => extractFromPdf($absPath),
        'doc' => extractFromDoc($absPath),
        default => null,
    };
}

function extractFromHtml(string $absPath): ?string
{
    $raw = @file_get_contents($absPath);
    if ($raw === false || $raw === '') {
        return null;
    }
    $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $raw) ?? $raw;
    $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $raw) ?? $raw;
    $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return normalizeText($text);
}

function extractFromDocx(string $absPath): ?string
{
    $cmd = 'unzip -p ' . escapeshellarg($absPath) . ' word/document.xml 2>/dev/null';
    $xml = shell_exec($cmd);
    if (!is_string($xml) || trim($xml) === '') {
        return null;
    }

    $xml = str_replace(["</w:p>", "</w:tr>", "</w:tbl>", "<w:tab/>"], ["\n", "\n", "\n", " "], $xml);
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return normalizeText($text);
}

function extractFromPdf(string $absPath): ?string
{
    $cmd = 'pdftotext -f 1 -l 4 -enc UTF-8 -nopgbrk ' . escapeshellarg($absPath) . ' - 2>/dev/null';
    $text = shell_exec($cmd);
    if (!is_string($text) || trim($text) === '') {
        return null;
    }
    return normalizeText($text);
}

function extractFromDoc(string $absPath): ?string
{
    $cmd = 'strings -n 8 ' . escapeshellarg($absPath) . ' 2>/dev/null';
    $text = shell_exec($cmd);
    if (!is_string($text) || trim($text) === '') {
        return null;
    }
    return normalizeText($text);
}

function normalizeText(string $text): string
{
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function detectContractorName(string $text): ?string
{
    if ($text === '') {
        return null;
    }

    $maxLen = 260000;
    if (mb_strlen($text, 'UTF-8') > $maxLen) {
        $text = mb_substr($text, 0, $maxLen, 'UTF-8');
    }

    $patterns = [
        '/\b(?:и|,)\s*(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество|Федеральное\s+государственное[^,;]{0,80}|Государственное\s+бюджетное[^,;]{0,80})[^,;]{0,220}?)\s*,?\s*именуем\w*\s+в\s+дальнейшем\s+[«"]?(?:Исполнитель|Поставщик|Подрядчик)\b/ui',
        '/\b(?:Исполнитель|Поставщик|Подрядчик)\s*[:\-–—]\s*(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество)[^,;]{1,220})/ui',
        '/\b(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество)[^,;]{1,220})\s*,?\s*именуем\w*\s+в\s+дальнейшем\s+[«"]?(?:Исполнитель|Поставщик|Подрядчик)\b/ui',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $match) {
            $candidateRaw = trim((string) ($match['name'] ?? ''));
            $candidate = cleanupContractorCandidate($candidateRaw);
            if ($candidate !== null) {
                return $candidate;
            }
        }
    }

    return null;
}

function cleanupContractorCandidate(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $value = trim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    $value = preg_split('/\s+(?:в\s+лице|ИНН|КПП|ОГРН|ОГРНИП|именуем\w*|действующ\w*|место\s+нахожд\w*)\b/ui', $value)[0] ?? $value;
    $value = trim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");

    if ($value === '' || mb_strlen($value, 'UTF-8') < 5 || mb_strlen($value, 'UTF-8') > 180) {
        return null;
    }
    if (!preg_match('/\p{L}/u', $value)) {
        return null;
    }
    if (preg_match('/\b(заказчик|бюро\s+судебно-медицинской\s+экспертизы)\b/ui', $value)) {
        return null;
    }
    return $value;
}
