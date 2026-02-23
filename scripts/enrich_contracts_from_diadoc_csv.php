#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$csvPath = null;
$limit = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $csvPath = (string) substr($arg, 6);
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

if ($csvPath === null || trim($csvPath) === '') {
    fwrite(STDERR, "Usage: php scripts/enrich_contracts_from_diadoc_csv.php --csv=/path/to/file.csv [--apply] [--verbose] [--limit=N]\n");
    exit(1);
}
if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: {$csvPath}\n");
    exit(1);
}

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();

$headers = [];
$rows = readCsvRows($csvPath, $headers);
if ($rows === []) {
    fwrite(STDERR, "CSV is empty: {$csvPath}\n");
    exit(1);
}

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

$byInn = [];
$byNameInn = [];
$byNumber = [];

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'csv_path' => $csvPath,
    'csv_rows' => count($rows),
    'counterparty_rows' => 0,
    'rows_with_number_keys' => 0,
    'number_keys_unique' => 0,
    'inn_keys_unique' => 0,
    'contracts_checked' => 0,
    'contracts_with_number_match' => 0,
    'contracts_with_inn_match' => 0,
    'contracts_with_name_match' => 0,
    'contracts_with_any_update' => 0,
    'contracts_updated' => 0,
    'name_updates' => 0,
    'inn_updates' => 0,
    'sample_updates' => [],
];

foreach ($rows as $row) {
    $counterparty = pickCounterpartyFromRow($row);
    $name = $counterparty['name'];
    $inn = $counterparty['inn'];
    if ($name === '' && $inn === null) {
        continue;
    }

    $metrics['counterparty_rows']++;

    if ($inn !== null) {
        if (!isset($byInn[$inn])) {
            $byInn[$inn] = [
                'name_counts' => [],
                'latest_date' => null,
                'latest_status' => null,
                'doc_count' => 0,
            ];
        }
        $byInn[$inn]['doc_count']++;

        if ($name !== '') {
            $byInn[$inn]['name_counts'][$name] = ($byInn[$inn]['name_counts'][$name] ?? 0) + 1;
        }

        $rowDate = normalizeDate((string) ($row['messageDate'] ?? ''));
        if ($rowDate !== null) {
            $latest = (string) ($byInn[$inn]['latest_date'] ?? '');
            if ($latest === '' || $rowDate > $latest) {
                $statusPrimary = cleanText((string) ($row['statusPrimary'] ?? ''));
                $statusSecondary = cleanText((string) ($row['statusSecondary'] ?? ''));
                $status = trim($statusPrimary . ($statusSecondary !== '' ? ' / ' . $statusSecondary : ''));
                $byInn[$inn]['latest_date'] = $rowDate;
                $byInn[$inn]['latest_status'] = $status !== '' ? $status : null;
            }
        }
    }

    if ($name !== '' && $inn !== null) {
        $nameKey = normalizeNameKey($name);
        if ($nameKey !== '') {
            if (!isset($byNameInn[$nameKey])) {
                $byNameInn[$nameKey] = [];
            }
            $byNameInn[$nameKey][$inn] = ($byNameInn[$nameKey][$inn] ?? 0) + 1;
        }
    }

    $textForNumbers = trim(
        cleanText((string) ($row['documentName'] ?? '')) . ' ' .
        cleanText((string) ($row['metadataSummary'] ?? ''))
    );
    $numberKeys = extractContractNumberKeys($textForNumbers);
    if ($numberKeys === []) {
        continue;
    }

    $metrics['rows_with_number_keys']++;
    $record = [
        'name' => $name,
        'inn' => $inn,
        'date' => normalizeDate((string) ($row['messageDate'] ?? '')),
        'status' => cleanText((string) ($row['statusPrimary'] ?? '')),
        'document_name' => cleanText((string) ($row['documentName'] ?? '')),
    ];

    foreach ($numberKeys as $key) {
        if (!isset($byNumber[$key])) {
            $byNumber[$key] = [];
        }
        if (count($byNumber[$key]) < 150) {
            $byNumber[$key][] = $record;
        }
    }
}

$metrics['number_keys_unique'] = count($byNumber);
$metrics['inn_keys_unique'] = count($byInn);

$contracts = $pdo->query(
    'SELECT id, number, contractor_name, contractor_inn, total_amount, notes
     FROM contracts
     ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

if ($limit !== null) {
    $contracts = array_slice($contracts, 0, $limit);
}

$metrics['contracts_checked'] = count($contracts);

$updateStmt = $pdo->prepare(
    'UPDATE contracts
     SET contractor_name = :name,
         contractor_inn = :inn,
         notes = :notes,
         updated_at = NOW()
     WHERE id = :id'
);
$auditStmt = $pdo->prepare(
    'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
     VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
);

foreach ($contracts as $contract) {
    $id = (int) ($contract['id'] ?? 0);
    if ($id < 1) {
        continue;
    }

    $number = cleanText((string) ($contract['number'] ?? ''));
    $currentName = cleanText((string) ($contract['contractor_name'] ?? ''));
    $currentInn = normalizeInn((string) ($contract['contractor_inn'] ?? ''));
    $currentNotes = (string) ($contract['notes'] ?? '');

    $needName = ($currentName === '' || $currentName === 'Контрагент не указан');
    $needInn = ($currentInn === null);

    if (!$needName && !$needInn) {
        continue;
    }

    $candidateName = null;
    $candidateInn = null;
    $matchSource = null;
    $matchContext = null;

    $contractNumberKeys = extractContractNumberKeys($number);
    foreach ($contractNumberKeys as $key) {
        if (!isset($byNumber[$key])) {
            continue;
        }
        $best = pickBestNumberMatch($byNumber[$key]);
        if ($best === null) {
            continue;
        }
        if ($needInn && $best['inn'] !== null && $candidateInn === null) {
            $candidateInn = (string) $best['inn'];
        }
        if ($needName && $best['name'] !== '' && $candidateName === null) {
            $candidateName = (string) $best['name'];
        }
        $matchSource = 'number';
        $matchContext = $key;
        break;
    }

    if ($matchSource === 'number') {
        $metrics['contracts_with_number_match']++;
    }

    if ($needName && $candidateName === null && $currentInn !== null && isset($byInn[$currentInn])) {
        $bestName = pickTopName($byInn[$currentInn]['name_counts']);
        if ($bestName !== null && $bestName !== '') {
            $candidateName = $bestName;
            if ($matchSource === null) {
                $matchSource = 'inn';
                $matchContext = $currentInn;
            }
            $metrics['contracts_with_inn_match']++;
        }
    }

    if ($needInn && $candidateInn === null && $currentName !== '' && $currentName !== 'Контрагент не указан') {
        $nameKey = normalizeNameKey($currentName);
        if ($nameKey !== '' && isset($byNameInn[$nameKey])) {
            $bestInn = pickTopInn($byNameInn[$nameKey]);
            if ($bestInn !== null) {
                $candidateInn = $bestInn;
                if ($matchSource === null) {
                    $matchSource = 'name';
                    $matchContext = $nameKey;
                }
                $metrics['contracts_with_name_match']++;
            }
        }
    }

    if ($candidateName === null && $candidateInn === null) {
        continue;
    }

    $newName = $currentName;
    $newInn = $currentInn;
    $didName = false;
    $didInn = false;

    if ($needName && $candidateName !== null) {
        $newName = $candidateName;
        $didName = true;
        $metrics['name_updates']++;
    }
    if ($needInn && $candidateInn !== null) {
        $newInn = $candidateInn;
        $didInn = true;
        $metrics['inn_updates']++;
    }

    if (!$didName && !$didInn) {
        continue;
    }

    $notes = upsertDiadocNote($currentNotes, $newInn !== null && isset($byInn[$newInn]) ? $byInn[$newInn] : null);

    $metrics['contracts_with_any_update']++;

    if ($apply) {
        $updateStmt->execute([
            'id' => $id,
            'name' => $newName !== '' ? $newName : 'Контрагент не указан',
            'inn' => $newInn,
            'notes' => $notes,
        ]);
        $metrics['contracts_updated']++;

        if ($adminId > 0) {
            $auditStmt->execute([
                'uid' => $adminId,
                'action' => 'contract_enriched_from_diadoc',
                'etype' => 'contract',
                'eid' => $id,
                'details' => json_encode([
                    'number' => $number,
                    'updated_name' => $didName ? $newName : null,
                    'updated_inn' => $didInn ? $newInn : null,
                    'source' => $matchSource,
                    'source_key' => $matchContext,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => '',
                'ua' => 'enrich_contracts_from_diadoc_csv.php',
            ]);
        }
    }

    if ($verbose) {
        echo sprintf(
            "[%s] #%d %s | name: %s | inn: %s | via: %s\n",
            $apply ? 'updated' : 'would-update',
            $id,
            $number,
            $didName ? $newName : '-',
            $didInn ? (string) $newInn : '-',
            $matchSource ?? '-'
        );
    }

    if (count($metrics['sample_updates']) < 40) {
        $metrics['sample_updates'][] = [
            'contract_id' => $id,
            'number' => $number,
            'name' => $didName ? $newName : null,
            'inn' => $didInn ? $newInn : null,
            'source' => $matchSource,
            'source_key' => $matchContext,
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

function readCsvRows(string $path, array &$headers): array
{
    $line = (string) (fgets(fopen($path, 'rb')) ?: '');
    $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        return [];
    }

    $headers = [];
    $rows = [];
    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        if ($headers === []) {
            $headers = array_map(static function (string $h): string {
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
                return trim($h);
            }, $row);
            continue;
        }
        if ($row === [null] || $row === []) {
            continue;
        }

        $assoc = [];
        foreach ($headers as $idx => $name) {
            $assoc[$name] = array_key_exists($idx, $row) ? (string) $row[$idx] : '';
        }
        $rows[] = $assoc;
    }
    fclose($fp);

    return $rows;
}

function pickCounterpartyFromRow(array $row): array
{
    $category = mbLower(cleanText((string) ($row['category'] ?? '')));
    $employeeRole = mbLower(cleanText((string) ($row['employeeRole'] ?? '')));

    $senderName = cleanText((string) ($row['senderName'] ?? ''));
    $senderInn = normalizeInn((string) ($row['senderInn'] ?? ''));
    $recipientName = cleanText((string) ($row['recipientName'] ?? ''));
    $recipientInn = normalizeInn((string) ($row['recipientInn'] ?? ''));

    $useSender = true;
    if ($category !== '') {
        if (str_contains($category, 'outgoing')) {
            $useSender = false;
        } elseif (str_contains($category, 'incoming')) {
            $useSender = true;
        }
    } elseif ($employeeRole !== '') {
        $useSender = str_contains($employeeRole, 'recipient');
    }

    $name = $useSender ? $senderName : $recipientName;
    $inn = $useSender ? $senderInn : $recipientInn;

    if ($inn === '8601009193') {
        $inn = null;
    }
    if (isLikelyOurOrgName($name)) {
        $name = '';
    }

    return ['name' => $name, 'inn' => $inn];
}

function extractContractNumberKeys(string $text): array
{
    if ($text === '') {
        return [];
    }

    $keys = [];
    $patterns = [
        '/\b20\d{2}\.\d{5,}\b/u',
        '/\b\d{4}\.\d{5,}\b/u',
    ];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $m)) {
            continue;
        }
        foreach ($m[0] as $value) {
            $key = normalizeContractNumber((string) $value);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
    }

    if (preg_match_all('/(?:договор|контракт|гк|гос(?:ударственный)?\s*контракт)\s*№\s*([A-Za-zА-Яа-я0-9.\-\/_]{2,40})/ui', $text, $m)) {
        foreach ($m[1] as $value) {
            $key = normalizeContractNumber((string) $value);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
    }

    return array_keys($keys);
}

function pickBestNumberMatch(array $records): ?array
{
    if ($records === []) {
        return null;
    }

    usort($records, static function (array $a, array $b): int {
        $aInn = $a['inn'] !== null ? 1 : 0;
        $bInn = $b['inn'] !== null ? 1 : 0;
        if ($aInn !== $bInn) {
            return $bInn <=> $aInn;
        }
        $aDate = (string) ($a['date'] ?? '');
        $bDate = (string) ($b['date'] ?? '');
        return $bDate <=> $aDate;
    });

    return $records[0] ?? null;
}

function pickTopName(array $nameCounts): ?string
{
    if ($nameCounts === []) {
        return null;
    }
    arsort($nameCounts);
    $name = array_key_first($nameCounts);
    return $name === null ? null : (string) $name;
}

function pickTopInn(array $innCounts): ?string
{
    if ($innCounts === []) {
        return null;
    }
    arsort($innCounts);
    $inn = array_key_first($innCounts);
    return $inn === null ? null : (string) $inn;
}

function upsertDiadocNote(string $notes, ?array $byInnInfo): ?string
{
    if ($byInnInfo === null) {
        return $notes === '' ? null : $notes;
    }

    $lines = preg_split('/\R/u', $notes) ?: [];
    $filtered = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }
        if (str_starts_with($trimmed, '[Диадок]')) {
            continue;
        }
        $filtered[] = $trimmed;
    }

    $docCount = (int) ($byInnInfo['doc_count'] ?? 0);
    $latestDate = (string) ($byInnInfo['latest_date'] ?? '');
    $latestStatus = cleanText((string) ($byInnInfo['latest_status'] ?? ''));
    $suffix = $latestDate !== '' ? ('; последняя дата: ' . $latestDate) : '';
    if ($latestStatus !== '') {
        $suffix .= '; статус: ' . $latestStatus;
    }

    $filtered[] = '[Диадок] документов: ' . $docCount . $suffix;
    return implode(PHP_EOL, $filtered);
}

function normalizeInn(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 10 || strlen($digits) === 12) {
        return $digits;
    }
    return null;
}

function normalizeContractNumber(string $value): string
{
    $value = cleanText($value);
    $value = preg_replace('/^[№#\s]+/u', '', $value) ?? $value;
    $value = str_replace([' ', "\xC2\xA0"], '', $value);
    return $value;
}

function normalizeNameKey(string $value): string
{
    $value = cleanText($value);
    $value = mbLower($value);
    $value = str_replace(['"', "'", '«', '»', '`'], '', $value);
    $value = preg_replace('/\b(ооо|ао|пао|оао|зао|ип|индивидуальный\s+предприниматель)\b/ui', ' ', $value) ?? $value;
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
    return trim($value);
}

function cleanText(string $value): string
{
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mbLower($value) === 'nan') {
        return '';
    }
    return $value;
}

function normalizeDate(string $value): ?string
{
    $value = cleanText($value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('Z', '', $value);
    $formats = ['Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
        return $m[1];
    }
    return null;
}

function mbLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function isLikelyOurOrgName(string $name): bool
{
    $key = normalizeNameKey($name);
    if ($key === '') {
        return false;
    }
    return str_contains($key, 'бюросудебномедицинскойэкспертизы')
        || str_contains($key, 'судмед')
        || str_contains($key, 'кубюро');
}
