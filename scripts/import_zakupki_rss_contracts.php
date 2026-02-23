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
$csvPath = null;

foreach ($argv as $arg) {
    if (startsWith((string) $arg, '--csv=')) {
        $csvPath = (string) substr((string) $arg, 6);
    } elseif (startsWith((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
}

if ($csvPath === null || trim($csvPath) === '') {
    fwrite(STDERR, "Usage: php scripts/import_zakupki_rss_contracts.php --csv=/path/to/contracts_2026.csv [--apply] [--verbose] [--limit=N]\n");
    exit(1);
}
if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: {$csvPath}\n");
    exit(1);
}

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();

$rows = readCsvAssoc($csvPath);
if ($rows === []) {
    fwrite(STDERR, "No rows in CSV: {$csvPath}\n");
    exit(1);
}
if ($limit !== null) {
    $rows = array_slice($rows, 0, $limit);
}

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'csv_path' => $csvPath,
    'rows_total' => count($rows),
    'rows_skipped' => 0,
    'contracts_created' => 0,
    'contracts_updated' => 0,
    'matched_by_registry' => 0,
    'matched_by_number' => 0,
    'ambiguous_number_matches' => 0,
    'sample' => [],
];

$insertStmt = $pdo->prepare(
    'INSERT INTO contracts (
        number, subject, law_type, contractor_name, contractor_inn, total_amount, nmck_amount, currency, status,
        signed_at, expires_at, notes, created_by, created_at, updated_at
    ) VALUES (
        :number, :subject, :law_type, :contractor_name, :contractor_inn, :total_amount, :nmck_amount, :currency, :status,
        :signed_at, :expires_at, :notes, :created_by, NOW(), NOW()
    )'
);

$updateStmt = null;
$auditStmt = $pdo->prepare(
    'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
     VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
);

foreach ($rows as $row) {
    $registryNumber = digitsOnly((string) ($row['registry_number'] ?? ''));
    $contractNumberRaw = normalizeText((string) ($row['contract_number'] ?? ''));
    $parsedNumber = parseContractNumber($contractNumberRaw);
    $numberToken = $parsedNumber['number'];

    $contractDate = normalizeDate((string) ($row['contract_date'] ?? ''));
    if ($contractDate === null) {
        $contractDate = normalizeDate((string) ($parsedNumber['date'] ?? ''));
    }

    $statusRss = normalizeText((string) ($row['contract_status'] ?? ''));
    $invalidRss = normalizeText((string) ($row['contract_invalid'] ?? ''));
    $mappedStatus = mapRssStatusToContractStatus($statusRss, $invalidRss);
    $amount = parseMoneyAmount((string) ($row['price'] ?? ''));
    $currency = mapCurrency((string) ($row['currency'] ?? ''));

    $customer = normalizeText((string) ($row['customer'] ?? ''));
    $publishedDate = normalizeDate((string) ($row['published_date'] ?? ''));
    $updatedDate = normalizeDate((string) ($row['updated_date'] ?? ''));
    $cardUrl = normalizeText((string) ($row['card_url'] ?? ''));
    $pubDateGmt = normalizeText((string) ($row['pub_date_gmt'] ?? ''));

    if ($registryNumber === '' && $contractNumberRaw === '' && $numberToken === '') {
        $metrics['rows_skipped']++;
        continue;
    }

    $existing = null;
    $matchedBy = '';

    if ($registryNumber !== '') {
        $existing = findByRegistry($pdo, $registryNumber);
        if ($existing !== null) {
            $metrics['matched_by_registry']++;
            $matchedBy = 'registry';
        }
    }

    if ($existing === null) {
        $numberForMatch = $numberToken !== '' ? $numberToken : $contractNumberRaw;
        if ($numberForMatch !== '' && !isWeakNumber($numberForMatch)) {
            $match = findByNumber($pdo, $contractNumberRaw, $numberToken, $contractDate);
            if ($match['ambiguous']) {
                $metrics['ambiguous_number_matches']++;
            }
            $existing = $match['row'];
            if ($existing !== null) {
                $metrics['matched_by_number']++;
                $matchedBy = 'number';
            }
        }
    }

    $noteBlock = buildEisNoteBlock(
        $registryNumber,
        $cardUrl,
        $statusRss,
        $invalidRss,
        $customer,
        $publishedDate,
        $updatedDate,
        $pubDateGmt
    );

    if ($existing === null) {
        $newNumber = pickBestNumber($numberToken, $contractNumberRaw, $registryNumber);
        $payload = [
            'number' => $newNumber,
            'subject' => buildSubject($newNumber, $registryNumber),
            'law_type' => '44',
            'contractor_name' => 'Контрагент не указан',
            'contractor_inn' => null,
            'total_amount' => $amount > 0 ? $amount : 0.0,
            'nmck_amount' => null,
            'currency' => $currency,
            'status' => $mappedStatus,
            'signed_at' => $contractDate,
            'expires_at' => null,
            'notes' => $noteBlock,
            'created_by' => $adminId > 0 ? $adminId : null,
        ];

        if ($apply) {
            $insertStmt->execute($payload);
            $newId = (int) $pdo->lastInsertId();
            $metrics['contracts_created']++;
            if ($adminId > 0) {
                $auditStmt->execute([
                    'uid' => $adminId,
                    'action' => 'contract_imported_from_zakupki_rss',
                    'etype' => 'contract',
                    'eid' => $newId,
                    'details' => json_encode([
                        'registry_number' => $registryNumber,
                        'number' => $newNumber,
                        'status_rss' => $statusRss,
                        'mapped_status' => $mappedStatus,
                        'amount' => $amount,
                    ], JSON_UNESCAPED_UNICODE),
                    'ip' => '',
                    'ua' => 'import_zakupki_rss_contracts.php',
                ]);
            }
        }

        if ($verbose) {
            echo sprintf("[%s] create number=%s registry=%s\n", $apply ? 'created' : 'would-create', $newNumber, $registryNumber);
        }

        if (count($metrics['sample']) < 40) {
            $metrics['sample'][] = [
                'action' => $apply ? 'created' : 'would-create',
                'registry_number' => $registryNumber,
                'number' => $newNumber,
                'status' => $mappedStatus,
                'amount' => $amount,
            ];
        }
        continue;
    }

    $updateData = [];

    $existingNumber = normalizeText((string) ($existing['number'] ?? ''));
    $betterNumber = pickBestNumber($numberToken, $contractNumberRaw, $registryNumber);
    if (isPlaceholderNumber($existingNumber) && $betterNumber !== '') {
        $updateData['number'] = $betterNumber;
    }

    $existingSubject = normalizeText((string) ($existing['subject'] ?? ''));
    if ($existingSubject === '') {
        $updateData['subject'] = buildSubject($betterNumber !== '' ? $betterNumber : $existingNumber, $registryNumber);
    }

    $existingLaw = normalizeText((string) ($existing['law_type'] ?? ''));
    if ($existingLaw !== '44') {
        $updateData['law_type'] = '44';
    }

    $existingAmount = (float) ($existing['total_amount'] ?? 0);
    if ($amount > 0 && (abs($existingAmount - $amount) > 0.009 || $existingAmount <= 0)) {
        $updateData['total_amount'] = $amount;
    }

    $existingCurrency = strtoupper(normalizeText((string) ($existing['currency'] ?? '')));
    if ($existingCurrency === '' || $existingCurrency !== $currency) {
        $updateData['currency'] = $currency;
    }

    $existingStatus = normalizeText((string) ($existing['status'] ?? 'draft'));
    $chosenStatus = chooseStatus($existingStatus, $mappedStatus);
    if ($chosenStatus !== $existingStatus) {
        $updateData['status'] = $chosenStatus;
    }

    $existingSigned = normalizeDate((string) ($existing['signed_at'] ?? ''));
    if ($existingSigned === null && $contractDate !== null) {
        $updateData['signed_at'] = $contractDate;
    }

    $existingNotes = (string) ($existing['notes'] ?? '');
    $mergedNotes = mergeEisNotes($existingNotes, $noteBlock);
    if ($mergedNotes !== $existingNotes) {
        $updateData['notes'] = $mergedNotes;
    }

    if ($updateData === []) {
        if (count($metrics['sample']) < 40) {
            $metrics['sample'][] = [
                'action' => 'matched-no-change',
                'id' => (int) $existing['id'],
                'number' => (string) $existing['number'],
                'registry_number' => $registryNumber,
                'matched_by' => $matchedBy,
            ];
        }
        continue;
    }

    if ($apply) {
        $updateData['id'] = (int) $existing['id'];
        if ($updateStmt === null || !isset($updateStmtCacheKey) || $updateStmtCacheKey !== implode('|', array_keys($updateData))) {
            $updateStmtCacheKey = implode('|', array_keys($updateData));
            $sets = [];
            foreach ($updateData as $k => $v) {
                if ($k === 'id') {
                    continue;
                }
                $sets[] = "{$k} = :{$k}";
            }
            $sql = 'UPDATE contracts SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
            $updateStmt = $pdo->prepare($sql);
        }
        $updateStmt->execute($updateData);
        $metrics['contracts_updated']++;

        if ($adminId > 0) {
            $auditStmt->execute([
                'uid' => $adminId,
                'action' => 'contract_updated_from_zakupki_rss',
                'etype' => 'contract',
                'eid' => (int) $existing['id'],
                'details' => json_encode([
                    'registry_number' => $registryNumber,
                    'matched_by' => $matchedBy,
                    'changes' => array_keys(array_diff_key($updateData, ['id' => true])),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => '',
                'ua' => 'import_zakupki_rss_contracts.php',
            ]);
        }
    }

    if ($verbose) {
        echo sprintf(
            "[%s] update #%d registry=%s fields=%s\n",
            $apply ? 'updated' : 'would-update',
            (int) $existing['id'],
            $registryNumber,
            implode(',', array_keys($updateData))
        );
    }

    if (count($metrics['sample']) < 40) {
        $metrics['sample'][] = [
            'action' => $apply ? 'updated' : 'would-update',
            'id' => (int) $existing['id'],
            'number' => (string) $existing['number'],
            'registry_number' => $registryNumber,
            'matched_by' => $matchedBy,
            'fields' => array_keys(array_diff_key($updateData, ['id' => true])),
        ];
    }
}

$metrics['contracts_total_after'] = (int) ($pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn() ?: 0);
$metrics['contracts_2026_after'] = (int) ($pdo->query("SELECT COUNT(*) FROM contracts WHERE signed_at >= '2026-01-01' AND signed_at <= '2026-12-31'")->fetchColumn() ?: 0);

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function readCsvAssoc(string $path): array
{
    $firstLine = '';
    $probe = fopen($path, 'rb');
    if ($probe !== false) {
        $firstLine = (string) fgets($probe);
        fclose($probe);
    }
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        return [];
    }

    $headers = null;
    $rows = [];
    while (($line = fgetcsv($fp, 0, $delimiter)) !== false) {
        if ($headers === null) {
            $headers = array_map(static function ($v): string {
                $text = (string) $v;
                $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
                return trim($text);
            }, $line);
            continue;
        }
        if ($line === [] || $line === [null]) {
            continue;
        }
        $assoc = [];
        foreach ($headers as $idx => $key) {
            $assoc[$key] = array_key_exists($idx, $line) ? (string) $line[$idx] : '';
        }
        $rows[] = $assoc;
    }
    fclose($fp);
    return $rows;
}

function startsWith(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function normalizeText(string $value): string
{
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mbLower($value) === 'nan') {
        return '';
    }
    return $value;
}

function digitsOnly(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function parseContractNumber(string $raw): array
{
    $raw = normalizeText($raw);
    $number = $raw;
    $date = '';

    if ($raw !== '' && preg_match('/^(.*?)\s+от\s+(\d{2}\.\d{2}\.\d{4})$/ui', $raw, $m)) {
        $number = normalizeText((string) $m[1]);
        $date = (string) $m[2];
    }
    $number = preg_replace('/^№\s*/u', '', $number) ?? $number;
    $number = normalizeText($number);

    return ['number' => $number, 'date' => $date];
}

function normalizeDate(string $value): ?string
{
    $value = normalizeText($value);
    if ($value === '') {
        return null;
    }
    $formats = ['Y-m-d', 'd.m.Y', 'd.m.y', 'Y-m-d H:i:s', 'd.m.Y H:i:s'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
        return (string) $m[1];
    }
    return null;
}

function parseMoneyAmount(string $value): float
{
    $value = normalizeText($value);
    if ($value === '') {
        return 0.0;
    }
    $value = str_replace([' ', "\xC2\xA0"], '', $value);
    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^\d.\-]/u', '', $value) ?? '';
    if ($value === '' || !is_numeric($value)) {
        return 0.0;
    }
    return round((float) $value, 2);
}

function mapCurrency(string $value): string
{
    $value = mbLower(normalizeText($value));
    if ($value === '' || contains($value, 'руб')) {
        return 'RUB';
    }
    if (contains($value, 'usd') || contains($value, 'доллар')) {
        return 'USD';
    }
    if (contains($value, 'eur') || contains($value, 'евро')) {
        return 'EUR';
    }
    return 'RUB';
}

function mapRssStatusToContractStatus(string $statusRss, string $invalidRss): string
{
    $status = mbLower(normalizeText($statusRss));
    $invalid = mbLower(normalizeText($invalidRss));
    if ($invalid === 'да' || contains($invalid, 'да')) {
        return 'cancelled';
    }
    if (contains($status, 'расторг')) {
        return 'terminated';
    }
    if (contains($status, 'прекращ') || contains($status, 'аннули')) {
        return 'cancelled';
    }
    if (contains($status, 'исполнен') || contains($status, 'заверш')) {
        return 'executed';
    }
    if (contains($status, 'исполнение')) {
        return 'active';
    }
    if (contains($status, 'подготов') || contains($status, 'проект')) {
        return 'draft';
    }
    return 'active';
}

function chooseStatus(string $current, string $incoming): string
{
    $current = normalizeText($current);
    $incoming = normalizeText($incoming);
    if ($current === '') {
        return $incoming;
    }
    if ($incoming === '') {
        return $current;
    }
    if ($current === $incoming) {
        return $current;
    }

    if ($current === 'executed' && $incoming === 'active') {
        return 'executed';
    }
    if (($current === 'terminated' || $current === 'cancelled') && ($incoming === 'active' || $incoming === 'draft')) {
        return $current;
    }

    $rank = [
        'draft' => 0,
        'active' => 1,
        'executed' => 2,
        'terminated' => 3,
        'cancelled' => 3,
    ];
    $rc = isset($rank[$current]) ? $rank[$current] : 0;
    $ri = isset($rank[$incoming]) ? $rank[$incoming] : 0;
    return $ri >= $rc ? $incoming : $current;
}

function contains(string $haystack, string $needle): bool
{
    return $needle !== '' && strpos($haystack, $needle) !== false;
}

function mbLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function buildEisNoteBlock(
    string $registryNumber,
    string $cardUrl,
    string $statusRss,
    string $invalidRss,
    string $customer,
    ?string $publishedDate,
    ?string $updatedDate,
    string $pubDateGmt
): string {
    $lines = [];
    if ($registryNumber !== '') {
        $lines[] = '[ЕИС] Реестровый №: ' . $registryNumber;
    }
    if ($cardUrl !== '') {
        $lines[] = '[ЕИС] Карточка: ' . $cardUrl;
    }
    if ($statusRss !== '') {
        $statusLine = '[ЕИС] Статус (RSS): ' . $statusRss;
        if ($invalidRss !== '') {
            $statusLine .= '; недействительный: ' . $invalidRss;
        }
        $lines[] = $statusLine;
    }
    if ($customer !== '') {
        $lines[] = '[ЕИС] Заказчик: ' . $customer;
    }
    if ($publishedDate !== null) {
        $lines[] = '[ЕИС] Размещено: ' . $publishedDate;
    }
    if ($updatedDate !== null) {
        $lines[] = '[ЕИС] Обновлено: ' . $updatedDate;
    }
    if ($pubDateGmt !== '') {
        $lines[] = '[ЕИС] pubDate: ' . $pubDateGmt;
    }
    return implode(PHP_EOL, $lines);
}

function mergeEisNotes(string $existingNotes, string $newEisBlock): ?string
{
    $lines = preg_split('/\R/u', $existingNotes) ?: [];
    $filtered = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }
        if (startsWith($trimmed, '[ЕИС]')) {
            continue;
        }
        $filtered[] = $trimmed;
    }
    if ($newEisBlock !== '') {
        foreach (preg_split('/\R/u', $newEisBlock) ?: [] as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed !== '') {
                $filtered[] = $trimmed;
            }
        }
    }
    return $filtered === [] ? null : implode(PHP_EOL, $filtered);
}

function pickBestNumber(string $numberToken, string $raw, string $registry): string
{
    if ($numberToken !== '') {
        return $numberToken;
    }
    if ($raw !== '') {
        return $raw;
    }
    return $registry !== '' ? $registry : 'EIS-UNKNOWN';
}

function buildSubject(string $number, string $registryNumber): string
{
    if ($registryNumber !== '') {
        return 'Контракт ЕИС № ' . $number . ' (реестр ' . $registryNumber . ')';
    }
    return 'Контракт ЕИС № ' . $number;
}

function isWeakNumber(string $number): bool
{
    $number = normalizeText($number);
    if ($number === '') {
        return true;
    }
    if (preg_match('/^\d{1,5}$/', $number)) {
        return true;
    }
    return false;
}

function isPlaceholderNumber(string $number): bool
{
    $low = mbLower($number);
    return $low === '' || $low === 'гк №' || $low === 'контракт' || $low === 'договор';
}

function findByRegistry(PDO $pdo, string $registryNumber): ?array
{
    $pattern = '%[ЕИС] Реестровый №: ' . $registryNumber . '%';
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE notes LIKE :p ORDER BY id ASC LIMIT 1');
    $stmt->execute(['p' => $pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findByNumber(PDO $pdo, string $raw, string $token, ?string $date): array
{
    $candidates = [];
    if ($token !== '') {
        $candidates[] = $token;
    }
    if ($raw !== '' && !in_array($raw, $candidates, true)) {
        $candidates[] = $raw;
    }
    if ($candidates === []) {
        return ['row' => null, 'ambiguous' => false];
    }

    $ph = [];
    $params = [];
    foreach ($candidates as $idx => $candidate) {
        $key = 'n' . $idx;
        $ph[] = ':' . $key;
        $params[$key] = $candidate;
    }

    $sql = 'SELECT * FROM contracts WHERE number IN (' . implode(', ', $ph) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return ['row' => null, 'ambiguous' => false];
    }

    if ($date !== null) {
        $exactDate = [];
        foreach ($rows as $row) {
            $rowDate = normalizeDate((string) ($row['signed_at'] ?? ''));
            if ($rowDate === $date) {
                $exactDate[] = $row;
            }
        }
        if (count($exactDate) === 1) {
            return ['row' => $exactDate[0], 'ambiguous' => false];
        }
        if (count($exactDate) > 1) {
            return ['row' => null, 'ambiguous' => true];
        }
    }

    if (count($rows) === 1) {
        return ['row' => $rows[0], 'ambiguous' => false];
    }
    return ['row' => null, 'ambiguous' => true];
}
