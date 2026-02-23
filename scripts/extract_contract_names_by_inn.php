#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$limit = null;
foreach ($argv as $arg) {
    if (startsWith((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
}

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();
$basePath = dirname(__DIR__);
$storagePath = resolveStoragePath($basePath);

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

$rows = $pdo->query(
    "SELECT c.id AS contract_id, c.number, c.contractor_inn, c.contractor_name,
            d.id AS document_id, d.original_name, d.relative_path, d.doc_type
     FROM contracts c
     LEFT JOIN documents d ON d.contract_id = c.id
     WHERE c.contractor_inn IS NOT NULL AND c.contractor_inn <> ''
       AND (c.contractor_name IS NULL OR c.contractor_name = '' OR c.contractor_name = 'Контрагент не указан')
     ORDER BY c.id ASC,
              CASE d.doc_type
                WHEN 'contract' THEN 0
                WHEN 'supplement' THEN 1
                WHEN 'invoice' THEN 2
                WHEN 'act' THEN 3
                ELSE 4
              END,
              d.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$contracts = [];
foreach ($rows as $row) {
    $id = (int) ($row['contract_id'] ?? 0);
    if ($id < 1) {
        continue;
    }
    if (!isset($contracts[$id])) {
        $contracts[$id] = [
            'id' => $id,
            'number' => (string) ($row['number'] ?? ''),
            'inn' => (string) ($row['contractor_inn'] ?? ''),
            'name' => (string) ($row['contractor_name'] ?? ''),
            'docs' => [],
        ];
    }
    if ($row['document_id'] !== null) {
        $contracts[$id]['docs'][] = [
            'id' => (int) $row['document_id'],
            'name' => (string) ($row['original_name'] ?? ''),
            'path' => (string) ($row['relative_path'] ?? ''),
            'type' => (string) ($row['doc_type'] ?? ''),
        ];
    }
}

if ($limit !== null) {
    $contracts = array_slice($contracts, 0, $limit, true);
}

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'contracts_checked' => count($contracts),
    'contracts_without_documents' => 0,
    'documents_processed' => 0,
    'documents_failed' => 0,
    'name_extracted' => 0,
    'contracts_updated' => 0,
    'sample_updates' => [],
];

$updateStmt = $pdo->prepare('UPDATE contracts SET contractor_name = :name, updated_at = NOW() WHERE id = :id');
$auditStmt = $pdo->prepare(
    'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
     VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
);

foreach ($contracts as $contract) {
    $docs = $contract['docs'];
    if ($docs === []) {
        $metrics['contracts_without_documents']++;
        continue;
    }

    $inn = normalizeInn((string) $contract['inn']);
    if ($inn === null) {
        continue;
    }

    $found = null;
    $sourceDoc = null;

    foreach ($docs as $doc) {
        $metrics['documents_processed']++;
        $absPath = $storagePath . '/' . ltrim((string) $doc['path'], '/');
        $text = extractTextFromDocument($absPath, (string) $doc['name']);
        if ($text === null) {
            $metrics['documents_failed']++;
            continue;
        }

        $candidate = detectNameByInn($text, $inn);
        if ($candidate !== null) {
            $found = $candidate;
            $sourceDoc = $doc;
            break;
        }
    }

    if ($found === null) {
        continue;
    }

    $metrics['name_extracted']++;

    if ($apply) {
        $updateStmt->execute([
            'id' => (int) $contract['id'],
            'name' => $found,
        ]);
        $metrics['contracts_updated']++;

        if ($adminId > 0) {
            $auditStmt->execute([
                'uid' => $adminId,
                'action' => 'contract_name_extracted_by_inn',
                'etype' => 'contract',
                'eid' => (int) $contract['id'],
                'details' => json_encode([
                    'number' => (string) $contract['number'],
                    'inn' => $inn,
                    'name' => $found,
                    'document_id' => (int) ($sourceDoc['id'] ?? 0),
                    'document_name' => (string) ($sourceDoc['name'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => '',
                'ua' => 'extract_contract_names_by_inn.php',
            ]);
        }
    }

    if ($verbose) {
        echo sprintf(
            "[%s] #%d %s inn=%s => %s (%s)\n",
            $apply ? 'updated' : 'would-update',
            (int) $contract['id'],
            (string) $contract['number'],
            $inn,
            $found,
            (string) ($sourceDoc['name'] ?? 'n/a')
        );
    }

    if (count($metrics['sample_updates']) < 40) {
        $metrics['sample_updates'][] = [
            'contract_id' => (int) $contract['id'],
            'number' => (string) $contract['number'],
            'inn' => $inn,
            'name' => $found,
            'document_name' => (string) ($sourceDoc['name'] ?? ''),
        ];
    }
}

$metrics['contracts_with_real_contractor_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_name IS NOT NULL AND contractor_name <> '' AND contractor_name <> 'Контрагент не указан'"
)->fetchColumn() ?: 0);
$metrics['contracts_unknown_name_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_name IS NULL OR contractor_name = '' OR contractor_name = 'Контрагент не указан'"
)->fetchColumn() ?: 0);

$json = json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($json)) {
    $json = json_encode([
        'error' => 'json_encode_failed',
        'json_error' => json_last_error_msg(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
echo $json . PHP_EOL;

function resolveStoragePath(string $basePath): string
{
    $storagePath = Env::get('STORAGE_PATH', 'storage');
    if (!startsWith($storagePath, '/')) {
        $storagePath = rtrim($basePath, '/') . '/' . ltrim($storagePath, '/');
    }
    return rtrim($storagePath, '/');
}

function startsWith(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function normalizeInn(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if (strlen($digits) === 10 || strlen($digits) === 12) {
        return $digits;
    }
    return null;
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

    switch ($ext) {
        case 'html':
        case 'htm':
            return extractFromHtml($absPath);
        case 'docx':
            return extractFromDocx($absPath);
        case 'pdf':
            return extractFromPdf($absPath);
        case 'doc':
            return extractFromDoc($absPath);
        default:
            return null;
    }
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
    $cmd = 'pdftotext -f 1 -l 8 -enc UTF-8 -nopgbrk ' . escapeshellarg($absPath) . ' - 2>/dev/null';
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

function detectNameByInn(string $text, string $inn): ?string
{
    if ($text === '' || $inn === '') {
        return null;
    }

    $textLen = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($textLen > 350000) {
        $text = function_exists('mb_substr') ? mb_substr($text, 0, 350000, 'UTF-8') : substr($text, 0, 350000);
    }

    $quoted = preg_quote($inn, '/');
    if (!preg_match_all('/' . $quoted . '/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    foreach ($matches[0] as $hit) {
        $offset = (int) ($hit[1] ?? 0);
        $start = max(0, $offset - 900);
        $length = 1800;
        $segment = mbSubstrByByteOffset($text, $start, $length);

        $candidate = extractLegalEntityFromSegment($segment);
        if ($candidate !== null) {
            return $candidate;
        }

        $candidate = extractNearestSupplierName($segment, $inn);
        if ($candidate !== null) {
            return $candidate;
        }
    }

    return null;
}

function mbSubstrByByteOffset(string $text, int $byteStart, int $byteLength): string
{
    $prefix = substr($text, 0, $byteStart);
    $prefixChars = function_exists('mb_strlen') ? mb_strlen($prefix, 'UTF-8') : strlen($prefix);
    $sliceRaw = substr($text, $byteStart, $byteLength);
    $sliceChars = function_exists('mb_strlen') ? mb_strlen($sliceRaw, 'UTF-8') : strlen($sliceRaw);
    return function_exists('mb_substr')
        ? mb_substr($text, $prefixChars, $sliceChars, 'UTF-8')
        : substr($text, $byteStart, $byteLength);
}

function extractNearestSupplierName(string $segment, string $inn): ?string
{
    $patterns = [
        '/(?:поставщик|исполнитель|подрядчик)[^\\p{L}\\p{N}]{0,20}(?<name>(?:ООО|АО|ПАО|ОАО|ИП|Индивидуальный\\s+предприниматель|Общество\\s+с\\s+ограниченной\\s+ответственностью)[^,;]{1,220})[^\\d]{0,30}ИНН[^\\d]{0,10}' . preg_quote($inn, '/') . '/ui',
        '/(?<name>(?:ООО|АО|ПАО|ОАО|ИП|Индивидуальный\\s+предприниматель|Общество\\s+с\\s+ограниченной\\s+ответственностью)[^,;]{1,220})[^\\d]{0,40}ИНН[^\\d]{0,10}' . preg_quote($inn, '/') . '/ui',
        '/ИНН[^\\d]{0,10}' . preg_quote($inn, '/') . '[^\\p{L}\\p{N}]{0,30}(?<name>(?:ООО|АО|ПАО|ОАО|ИП|Индивидуальный\\s+предприниматель|Общество\\s+с\\s+ограниченной\\s+ответственностью)[^,;]{1,220})/ui',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $segment, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $m) {
            $candidate = cleanupContractorCandidate((string) ($m['name'] ?? ''));
            if ($candidate !== null) {
                return $candidate;
            }
        }
    }

    return null;
}

function extractLegalEntityFromSegment(string $segment): ?string
{
    $patterns = [
        '/\b(?:Полное\s+наименование|Наименование)\s*(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель)[^,;]{1,220})/ui',
        '/\b(?<name>Индивидуальный\s+предприниматель\s+[А-ЯЁA-Z][^,;]{2,180})/u',
        '/\b(?<name>ИП\s+[А-ЯЁA-Z][^,;]{2,180})/u',
        '/\b(?<name>Общество\s+с\s+ограниченной\s+ответственностью\s*[«"][^»"]{2,180}[»"]?)/ui',
        '/\b(?<name>(?:ООО|АО|ПАО|ОАО)\s*[«"][^»"]{2,160}[»"]?)/u',
        '/\b(?<name>(?:ООО|АО|ПАО|ОАО)\s+[А-ЯA-Z0-9][^,;]{2,160})/u',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $segment, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $match) {
            $candidate = cleanupContractorCandidate((string) ($match['name'] ?? ''));
            if ($candidate !== null) {
                return $candidate;
            }
        }
    }
    return null;
}

function cleanupContractorCandidate(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $value = trim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (preg_match('/^(?:ООО|АО|ПАО|ОАО|Общество\s+с\s+ограниченной\s+ответственностью)\s*[«"][^»"]{2,220}[»"]/ui', $value, $m)) {
        $value = (string) $m[0];
    }

    $value = preg_split('/(?:Сокращенн\w*\s+наименование|Полное\s+наименование|ИНН|КПП|ОГРН|ОГРНИП|Статус|Место\s+нахожд\w*|Почтов\w*\s+адрес|Адрес|Телефон|Электронн\w*\s+почт\w*|в\s+лице|именуем\w*|действующ\w*)/ui', $value)[0] ?? $value;
    $value = trim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");

    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        return null;
    }

    $doubleQuotes = substr_count($value, '"');
    if ($doubleQuotes === 1) {
        $value = str_replace('"', '', $value);
    }
    $leftAngle = substr_count($value, '«');
    $rightAngle = substr_count($value, '»');
    if ($leftAngle !== $rightAngle) {
        $value = str_replace(['«', '»'], '', $value);
    }
    $value = trim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");

    // Отсекаем банковские/служебные хвосты и мусор OCR.
    if (preg_match('/(?:Р\/с|К\/с|БИК|E-?Mail|@|http|www\.|тел\.?:)/ui', $value)) {
        return null;
    }
    if (strpos($value, '�') !== false) {
        return null;
    }

    $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($value === '' || $len < 5) {
        return null;
    }
    if ($len > 180) {
        $value = function_exists('mb_substr') ? mb_substr($value, 0, 180, 'UTF-8') : substr($value, 0, 180);
        $value = rtrim($value, " \t\n\r\0\x0B,.;:()[]{}<>\"'«»");
    }

    if (preg_match('/^Индивидуальный\s+предприниматель$/ui', $value)) {
        return null;
    }
    if (preg_match('/^Индивидуальный\s+предприниматель\s+[А-ЯЁA-Z]\.?$/u', $value)) {
        return null;
    }
    if (preg_match('/^Индивидуальный\s+предприниматель\s+[А-ЯЁA-Z][а-яёa-z\-]+$/u', $value)) {
        return null;
    }

    if (preg_match('/^(?:ООО|АО|ПАО|ОАО)$/u', $value)) {
        return null;
    }

    if (preg_match('/\b(заказчик|бюро\s+судебно-медицинской\s+экспертизы)\b/ui', $value)) {
        return null;
    }
    if (preg_match('/банк/ui', $value)) {
        return null;
    }

    // Частый ложный матч в реквизитах (не название поставщика).
    if (preg_match('/^АО\s+ХАНТЫ-МАНСИЙСКИЙ\s+АВТОНОМНЫЙ\s+ОКРУГ\s*-\s*ЮГРА$/u', $value)) {
        return null;
    }

    return $value;
}
