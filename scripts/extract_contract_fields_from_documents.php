#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$args = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $args, true);
$verbose = in_array('--verbose', $args, true);
$limit = null;
foreach ($args as $arg) {
    if (startsWith($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();
$basePath = dirname(__DIR__);
$storagePath = resolveStoragePath($basePath);

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

$sql = <<<SQL
SELECT
  c.id AS contract_id,
  c.number,
  c.contractor_name,
  c.contractor_inn,
  c.total_amount,
  d.id AS document_id,
  d.original_name,
  d.relative_path,
  d.doc_type
FROM contracts c
LEFT JOIN documents d ON d.contract_id = c.id
WHERE
  c.contractor_name IS NULL OR c.contractor_name = '' OR c.contractor_name = 'Контрагент не указан'
  OR c.contractor_inn IS NULL OR c.contractor_inn = ''
  OR c.total_amount IS NULL OR c.total_amount <= 0
ORDER BY
  c.id ASC,
  CASE d.doc_type
    WHEN 'contract' THEN 0
    WHEN 'supplement' THEN 1
    WHEN 'invoice' THEN 2
    WHEN 'act' THEN 3
    ELSE 4
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
            'contractor_name' => (string) ($row['contractor_name'] ?? ''),
            'contractor_inn' => (string) ($row['contractor_inn'] ?? ''),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'documents' => [],
        ];
    }

    if ($row['document_id'] !== null) {
        $contracts[$contractId]['documents'][] = [
            'id' => (int) $row['document_id'],
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
    'contracts_with_any_update' => 0,
    'contracts_updated' => 0,
    'name_extracted' => 0,
    'inn_extracted' => 0,
    'amount_extracted' => 0,
    'documents_processed' => 0,
    'documents_read_failed' => 0,
    'sample_updates' => [],
];

$updateStmt = $pdo->prepare(
    'UPDATE contracts SET contractor_name = :name, contractor_inn = :inn, total_amount = :amount, updated_at = NOW() WHERE id = :id'
);
$auditStmt = $pdo->prepare(
    'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
     VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
);

foreach ($contracts as $contract) {
    $documents = $contract['documents'];
    if ($documents === []) {
        $metrics['contracts_without_documents']++;
        continue;
    }
    $metrics['contracts_with_documents']++;

    $currentName = trim((string) $contract['contractor_name']);
    $currentInn = trim((string) $contract['contractor_inn']);
    $currentAmount = (float) $contract['total_amount'];

    $needName = $currentName === '' || $currentName === 'Контрагент не указан';
    $needInn = $currentInn === '';
    $needAmount = $currentAmount <= 0.0;

    if (!$needName && !$needInn && !$needAmount) {
        continue;
    }

    $foundName = null;
    $foundInn = null;
    $foundAmount = null;
    $sourceDoc = null;

    foreach ($documents as $doc) {
        $metrics['documents_processed']++;
        $absPath = $storagePath . '/' . ltrim((string) $doc['relative_path'], '/');
        $text = extractTextFromDocument($absPath, (string) $doc['original_name']);
        if ($text === null) {
            $metrics['documents_read_failed']++;
            continue;
        }

        if ($needName && $foundName === null) {
            $candidate = detectContractorName($text);
            if ($candidate !== null) {
                $foundName = $candidate;
                $sourceDoc = $sourceDoc ?? $doc;
            }
        }
        if ($needInn && $foundInn === null) {
            $candidate = detectSupplierInn($text);
            if ($candidate !== null) {
                $foundInn = $candidate;
                $sourceDoc = $sourceDoc ?? $doc;
            }
        }
        if ($needAmount && $foundAmount === null) {
            $candidate = detectContractAmount($text);
            if ($candidate !== null && $candidate > 0.0) {
                $foundAmount = $candidate;
                $sourceDoc = $sourceDoc ?? $doc;
            }
        }

        if ((!$needName || $foundName !== null)
            && (!$needInn || $foundInn !== null)
            && (!$needAmount || $foundAmount !== null)
        ) {
            break;
        }
    }

    if ($foundName === null && $foundInn === null && $foundAmount === null) {
        continue;
    }

    $newName = $needName && $foundName !== null ? $foundName : ($currentName !== '' ? $currentName : 'Контрагент не указан');
    $newInn = $needInn && $foundInn !== null ? $foundInn : ($currentInn !== '' ? $currentInn : null);
    $newAmount = $needAmount && $foundAmount !== null ? $foundAmount : $currentAmount;

    $metrics['contracts_with_any_update']++;
    if ($foundName !== null) {
        $metrics['name_extracted']++;
    }
    if ($foundInn !== null) {
        $metrics['inn_extracted']++;
    }
    if ($foundAmount !== null) {
        $metrics['amount_extracted']++;
    }

    if ($apply) {
        $updateStmt->execute([
            'id' => (int) $contract['id'],
            'name' => $newName,
            'inn' => $newInn,
            'amount' => $newAmount,
        ]);
        $metrics['contracts_updated']++;

        if ($adminId > 0) {
            $auditStmt->execute([
                'uid' => $adminId,
                'action' => 'contract_fields_extracted',
                'etype' => 'contract',
                'eid' => (int) $contract['id'],
                'details' => json_encode([
                    'number' => (string) $contract['number'],
                    'contractor_name' => $foundName,
                    'contractor_inn' => $foundInn,
                    'total_amount' => $foundAmount,
                    'document_id' => (int) ($sourceDoc['id'] ?? 0),
                    'document_name' => (string) ($sourceDoc['original_name'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => '',
                'ua' => 'extract_contract_fields_from_documents.php',
            ]);
        }
    }

    if ($verbose) {
        echo sprintf(
            "[%s] #%d %s name=%s inn=%s amount=%s doc=%s\n",
            $apply ? 'updated' : 'would-update',
            (int) $contract['id'],
            (string) $contract['number'],
            $foundName ?? '-',
            $foundInn ?? '-',
            $foundAmount !== null ? (string) $foundAmount : '-',
            (string) ($sourceDoc['original_name'] ?? '-')
        );
    }

    if (count($metrics['sample_updates']) < 40) {
        $metrics['sample_updates'][] = [
            'contract_id' => (int) $contract['id'],
            'number' => (string) $contract['number'],
            'contractor_name' => $foundName,
            'contractor_inn' => $foundInn,
            'total_amount' => $foundAmount,
            'document_name' => (string) ($sourceDoc['original_name'] ?? ''),
        ];
    }
}

$metrics['contracts_with_real_contractor_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_name IS NOT NULL AND contractor_name <> '' AND contractor_name <> 'Контрагент не указан'"
)->fetchColumn() ?: 0);
$metrics['contracts_with_inn_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE contractor_inn IS NOT NULL AND contractor_inn <> ''"
)->fetchColumn() ?: 0);
$metrics['contracts_with_amount_after'] = (int) ($pdo->query(
    "SELECT COUNT(*) FROM contracts WHERE total_amount IS NOT NULL AND total_amount > 0"
)->fetchColumn() ?: 0);

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function startsWith(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function resolveStoragePath(string $basePath): string
{
    $storagePath = Env::get('STORAGE_PATH', 'storage');
    if (!startsWith($storagePath, '/')) {
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
    $cmd = 'pdftotext -f 1 -l 6 -enc UTF-8 -nopgbrk ' . escapeshellarg($absPath) . ' - 2>/dev/null';
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

    if (mb_strlen($text, 'UTF-8') > 280000) {
        $text = mb_substr($text, 0, 280000, 'UTF-8');
    }

    $contextMarkers = [
        'сведения о поставщике',
        'сведения о поставщике (подрядчике, исполнителе)',
        'поставщик (подрядчик, исполнитель)',
        'сведения об исполнителе',
        'сведения о подрядчике',
    ];
    foreach ($contextMarkers as $marker) {
        $pos = mb_stripos($text, $marker, 0, 'UTF-8');
        if ($pos === false) {
            continue;
        }
        $segment = mb_substr($text, (int) $pos, 2500, 'UTF-8');
        $fromSegment = extractLegalEntityFromSegment($segment);
        if ($fromSegment !== null) {
            return $fromSegment;
        }
    }

    $patterns = [
        '/\b(?:и|,)\s*(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество)[^,;]{0,220}?)\s*,?\s*именуем\w*\s+в\s+дальнейшем\s+[«"]?(?:Исполнитель|Поставщик|Подрядчик)\b/ui',
        '/\b(?:Исполнитель|Поставщик|Подрядчик)\s*[:\-–—]\s*(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество)[^,;]{1,220})/ui',
        '/\b(?<name>(?:Общество\s+с\s+ограниченной\s+ответственностью|ООО|АО|ПАО|ОАО|ИП|Индивидуальный\s+предприниматель|Акционерное\s+общество)[^,;]{1,220})\s*,?\s*именуем\w*\s+в\s+дальнейшем\s+[«"]?(?:Исполнитель|Поставщик|Подрядчик)\b/ui',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $match) {
            $candidate = cleanupContractorCandidate(trim((string) ($match['name'] ?? '')));
            if ($candidate !== null) {
                return $candidate;
            }
        }
    }

    return null;
}

function detectSupplierInn(string $text): ?string
{
    $excluded = ['8601009193'];
    $markers = [
        'сведения о поставщике',
        'поставщик (подрядчик, исполнитель)',
        'сведения об исполнителе',
    ];
    foreach ($markers as $marker) {
        $pos = mb_stripos($text, $marker, 0, 'UTF-8');
        if ($pos === false) {
            continue;
        }
        $segment = mb_substr($text, (int) $pos, 2500, 'UTF-8');
        if (preg_match('/ИНН[^0-9]{0,16}(\d{10}|\d{12})/u', $segment, $m)) {
            $inn = (string) $m[1];
            if (!in_array($inn, $excluded, true)) {
                return $inn;
            }
        }
    }

    if (preg_match_all('/ИНН[^0-9]{0,16}(\d{10}|\d{12})/u', $text, $m)) {
        $seen = [];
        foreach ($m[1] as $inn) {
            $inn = (string) $inn;
            if (in_array($inn, $excluded, true)) {
                continue;
            }
            if (isset($seen[$inn])) {
                continue;
            }
            $seen[$inn] = true;
        }
        if ($seen !== []) {
            $firstInn = array_key_first($seen);
            return $firstInn === null ? null : (string) $firstInn;
        }
    }
    return null;
}

function detectContractAmount(string $text): ?float
{
    $patterns = [
        '/цена\s+(?:государственного\s+)?(?:контракта|договора)[^0-9]{0,80}([0-9][0-9\s.,]{2,30})(?:\s*(?:руб|рубл\w*|р\.|российских\s+руб|₽|RUB))/ui',
        '/стоимость\s+(?:контракта|договора)[^0-9]{0,80}([0-9][0-9\s.,]{2,30})(?:\s*(?:руб|рубл\w*|р\.|российских\s+руб|₽|RUB))/ui',
        '/сумма\s+(?:контракта|договора)[^0-9]{0,80}([0-9][0-9\s.,]{2,30})(?:\s*(?:руб|рубл\w*|р\.|российских\s+руб|₽|RUB))/ui',
        '/цена\s+(?:контракта|договора)[^0-9]{0,140}([0-9][0-9\s.,]{2,30})/ui',
    ];

    $candidates = [];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches)) {
            continue;
        }
        foreach ($matches[1] as $raw) {
            $rawString = (string) $raw;
            if (isLikelyContractNumberToken($rawString)) {
                continue;
            }

            $amount = parseAmount($rawString);
            if ($amount !== null && $amount > 100 && $amount < 5000000000) {
                $candidates[] = $amount;
            }
        }
    }

    if ($candidates === []) {
        return null;
    }
    rsort($candidates, SORT_NUMERIC);
    return (float) $candidates[0];
}

function isLikelyContractNumberToken(string $raw): bool
{
    $candidate = trim(str_replace("\xC2\xA0", ' ', $raw));
    $candidate = preg_replace('/\s+/u', '', $candidate) ?? $candidate;

    if (preg_match('/^20\d{2}[.,]\d{3,}$/u', $candidate)) {
        return true;
    }
    if (preg_match('/^\d{4}[.,]\d{4,}$/u', $candidate)) {
        return true;
    }

    return false;
}

function parseAmount(string $raw): ?float
{
    $s = trim(str_replace("\xC2\xA0", ' ', $raw));
    $s = preg_replace('/\s+/u', '', $s) ?? $s;
    $s = preg_replace('/[^0-9,\.]/u', '', $s) ?? $s;
    if ($s === '' || !preg_match('/\d/', $s)) {
        return null;
    }

    $commaPos = strrpos($s, ',');
    $dotPos = strrpos($s, '.');
    if ($commaPos !== false && $dotPos !== false) {
        if ($commaPos > $dotPos) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    } elseif ($commaPos !== false) {
        $s = str_replace(',', '.', $s);
    }

    $value = (float) $s;
    if ($value <= 0) {
        return null;
    }
    return round($value, 2);
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
            $candidate = cleanupContractorCandidate(trim((string) ($match['name'] ?? '')));
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
