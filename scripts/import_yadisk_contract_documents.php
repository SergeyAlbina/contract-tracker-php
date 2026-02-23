#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Infrastructure\Db\PdoFactory;
use App\Shared\Utils\Env;

require_once __DIR__ . '/../autoload.php';

$opts = parseCli($_SERVER['argv'] ?? []);
$publicKey = (string) ($opts['public_key'] ?? 'https://disk.yandex.ru/d/t-7xJNcp4CfEgQ');
$apply = (bool) ($opts['apply'] ?? false);
$verbose = (bool) ($opts['verbose'] ?? false);
$limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : null;

Env::load(dirname(__DIR__) . '/.env');
$pdo = PdoFactory::create();
$basePath = dirname(__DIR__);
$storageBase = resolveStoragePath($basePath);

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
if ($adminId < 1) {
    fwrite(STDERR, "Admin user not found.\n");
    exit(1);
}

$files = listPublicFiles($publicKey);
if ($limit !== null) {
    $files = array_slice($files, 0, $limit);
}

$contracts = fetchContracts($pdo);
$indexes = buildContractIndexes($contracts);
$docNameCache = [];

$metrics = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'public_key' => $publicKey,
    'files_total' => count($files),
    'contracts_existing_before' => count($contracts),
    'contracts_matched' => 0,
    'contracts_created' => 0,
    'documents_uploaded' => 0,
    'documents_skipped_exists' => 0,
    'documents_failed' => 0,
    'errors' => [],
    'sample' => [],
];

foreach ($files as $idx => $file) {
    $path = (string) ($file['path'] ?? '');
    $name = (string) ($file['name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($path === '' || $name === '') {
        continue;
    }

    $candidates = extractContractNumbers($path . ' ' . $name);
    $contractNumber = chooseContractNumber($path, $name, $candidates);
    $contract = findContract($contractNumber, $candidates, $indexes);

    if ($contract !== null) {
        $metrics['contracts_matched']++;
    } elseif ($apply) {
        $contract = createContractFromFile($pdo, $adminId, $contractNumber, $name, $path);
        $indexes = indexContract($indexes, $contract);
        $metrics['contracts_created']++;
    }

    if ($contract === null) {
        addSample($metrics['sample'], [
            'path' => $path,
            'contract_number' => $contractNumber,
            'status' => 'contract_not_found',
        ]);
        continue;
    }

    $contractId = (int) $contract['id'];
    $docType = detectDocType($name, $path);

    $existingNameSet = $docNameCache[$contractId] ??= loadDocumentNameSet($pdo, $contractId);
    $nameKey = normalizeNameForCompare($name);
    if (isset($existingNameSet[$nameKey])) {
        $metrics['documents_skipped_exists']++;
        addSample($metrics['sample'], [
            'path' => $path,
            'contract_id' => $contractId,
            'contract_number' => $contract['number'],
            'status' => 'skip_exists',
        ]);
        continue;
    }

    if (!$apply) {
        addSample($metrics['sample'], [
            'path' => $path,
            'contract_id' => $contractId,
            'contract_number' => $contract['number'],
            'status' => 'would_upload',
        ]);
        continue;
    }

    try {
        $downloadUrl = getDownloadUrl($publicKey, $path);
        $stored = downloadToStorage($downloadUrl, $storageBase, $contractId, $name);
        $docId = insertDocument($pdo, [
            'contract_id' => $contractId,
            'original_name' => $name,
            'safe_name' => $stored['safe_name'],
            'relative_path' => $stored['relative_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'sha256' => $stored['sha256'],
            'doc_type' => $docType,
            'uploaded_by' => $adminId,
        ]);
        $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
             VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
        )->execute([
            'uid' => $adminId,
            'action' => 'document_uploaded',
            'etype' => 'document',
            'eid' => $docId,
            'details' => json_encode(['contract_id' => $contractId, 'source' => 'yadisk', 'path' => $path], JSON_UNESCAPED_UNICODE),
            'ip' => '',
            'ua' => 'import_yadisk_contract_documents.php',
        ]);

        $metrics['documents_uploaded']++;
        $docNameCache[$contractId][$nameKey] = true;

        if ($verbose) {
            echo sprintf(
                "[%d/%d] + %s -> contract #%d (%s), file=%d bytes, source=%d bytes\n",
                $idx + 1,
                count($files),
                $path,
                $contractId,
                (string) $contract['number'],
                (int) $stored['size_bytes'],
                $size
            );
        }

        addSample($metrics['sample'], [
            'path' => $path,
            'contract_id' => $contractId,
            'contract_number' => $contract['number'],
            'status' => 'uploaded',
        ]);
    } catch (Throwable $e) {
        $metrics['documents_failed']++;
        addSample($metrics['errors'], [
            'path' => $path,
            'contract_id' => $contractId,
            'error' => $e->getMessage(),
        ], 50);
    }
}

$metrics['contracts_total_after'] = (int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn();
$metrics['documents_total_after'] = (int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();

echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function parseCli(array $argv): array
{
    $result = [];
    foreach ($argv as $arg) {
        if ($arg === '--apply') {
            $result['apply'] = true;
            continue;
        }
        if ($arg === '--verbose') {
            $result['verbose'] = true;
            continue;
        }
        if (str_starts_with($arg, '--public-key=')) {
            $result['public_key'] = substr($arg, 13);
            continue;
        }
        if (str_starts_with($arg, '--limit=')) {
            $result['limit'] = substr($arg, 8);
        }
    }
    return $result;
}

function resolveStoragePath(string $basePath): string
{
    $storagePath = Env::get('STORAGE_PATH', 'storage');
    if (!str_starts_with($storagePath, '/')) {
        $storagePath = rtrim($basePath, '/') . '/' . ltrim($storagePath, '/');
    }
    $storagePath = rtrim($storagePath, '/');
    if (!is_dir($storagePath) && !mkdir($storagePath, 0750, true) && !is_dir($storagePath)) {
        throw new RuntimeException('Cannot create storage directory: ' . $storagePath);
    }
    return $storagePath;
}

/** @return array<int,array<string,mixed>> */
function listPublicFiles(string $publicKey): array
{
    $files = [];
    $queue = ['/'];
    while ($queue !== []) {
        $path = array_shift($queue);
        $offset = 0;
        $limit = 1000;
        do {
            $data = yadiskApi('resources', [
                'public_key' => $publicKey,
                'path' => $path,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $embedded = $data['_embedded'] ?? [];
            $items = is_array($embedded['items'] ?? null) ? $embedded['items'] : [];
            $total = (int) ($embedded['total'] ?? count($items));

            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'dir') {
                    $queue[] = (string) $item['path'];
                } elseif ($type === 'file') {
                    $files[] = $item;
                }
            }

            $offset += count($items);
        } while ($offset < $total);
    }

    usort($files, static fn(array $a, array $b): int => strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? '')));
    return $files;
}

/** @return array<string,mixed> */
function yadiskApi(string $endpoint, array $params): array
{
    $url = 'https://cloud-api.yandex.net/v1/disk/public/' . ltrim($endpoint, '/')
        . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $body = httpGet($url);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid Yandex API response for ' . $endpoint);
    }
    if (isset($decoded['error'])) {
        throw new RuntimeException('Yandex API error: ' . (string) ($decoded['description'] ?? $decoded['error']));
    }
    return $decoded;
}

function httpGet(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => ['User-Agent: contract-tracker-import/1.0'],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP GET failed: ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code >= 400) {
            throw new RuntimeException('HTTP GET failed with status ' . $code . ' for ' . $url);
        }
        return (string) $body;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 180,
            'ignore_errors' => true,
            'header' => "User-Agent: contract-tracker-import/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        throw new RuntimeException('HTTP GET failed for ' . $url);
    }
    return $body;
}

/** @return array<int,array<string,mixed>> */
function fetchContracts(PDO $pdo): array
{
    return $pdo->query('SELECT id, number, subject FROM contracts ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,array<string,mixed>> */
function buildContractIndexes(array $contracts): array
{
    $byNorm = [];
    $byLoose = [];
    foreach ($contracts as $row) {
        $norm = normalizeNumberForMatch((string) ($row['number'] ?? ''));
        if ($norm !== '') {
            $byNorm[$norm][] = $row;
            $byLoose[normalizeLoose($norm)][] = $row;
        }
    }
    return ['by_norm' => $byNorm, 'by_loose' => $byLoose];
}

/** @return array<string,array<string,mixed>> */
function indexContract(array $indexes, array $contract): array
{
    $norm = normalizeNumberForMatch((string) ($contract['number'] ?? ''));
    if ($norm !== '') {
        $indexes['by_norm'][$norm][] = $contract;
        $indexes['by_loose'][normalizeLoose($norm)][] = $contract;
    }
    return $indexes;
}

/** @return array<int,string> */
function extractContractNumbers(string $text): array
{
    $text = str_replace(["\xc2\xa0", '№'], [' ', ' № '], $text);
    $patterns = [
        '/20\d{2}\.\s*\d{4,8}(?=[^0-9]|$)/u',
        '/\b\d{1,4}[\/_]\d{2,4}(?:-[\p{L}]{1,10})?\b/u',
        '/\b\d{1,8}[\p{L}]{2,12}\d{1,8}\b/u',
        '/№\s*([0-9A-Za-zА-Яа-я._\/-]{2,})/u',
    ];

    $result = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $m)) {
            $values = isset($m[1]) && $m[1] !== [] ? $m[1] : $m[0];
            foreach ($values as $candidate) {
                $value = cleanExtractedNumber((string) $candidate);
                if ($value !== '' && preg_match('/\d/u', $value)) {
                    $result['#' . $value] = $value;
                }
            }
        }
    }
    return array_values($result);
}

function cleanExtractedNumber(string $raw): string
{
    $value = trim($raw);
    $value = preg_replace('/\s+/u', '', $value) ?? $value;
    $value = trim($value, ".,;:()[]{}<>\"'`");
    return $value;
}

function chooseContractNumber(string $path, string $name, array $candidates): string
{
    $candidates = array_map(static fn($v): string => (string) $v, $candidates);
    if ($candidates !== []) {
        usort($candidates, static function (string $a, string $b): int {
            $sa = scoreNumberCandidate($a);
            $sb = scoreNumberCandidate($b);
            return $sb <=> $sa ?: strlen($b) <=> strlen($a);
        });
        return mb_substr($candidates[0], 0, 100);
    }

    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = trim($base);
    if ($base === '') {
        $base = 'IMPORT-' . substr(sha1($path), 0, 12);
    }
    return mb_substr($base, 0, 100);
}

function scoreNumberCandidate(string $value): int
{
    $score = 0;
    if (preg_match('/^20\d{2}\.\d{4,8}$/u', $value)) {
        $score += 1000;
    }
    if (preg_match('/\d/u', $value)) {
        $score += 100;
    }
    if (preg_match('/\p{L}/u', $value)) {
        $score += 40;
    }
    $score += min(strlen($value), 80);
    return $score;
}

function normalizeNumberForMatch(string $number): string
{
    $s = mb_strtolower($number, 'UTF-8');
    $s = str_replace('ё', 'е', $s);
    $s = str_replace('№', '', $s);
    $s = preg_replace('/\s+/u', '', $s) ?? $s;
    $s = str_replace(['контракт', 'договор', 'гк', 'гос.контракт', 'госконтракт'], '', $s);
    $s = preg_replace('/[^0-9a-zа-я._\/-]+/u', '', $s) ?? $s;
    return trim($s, "._/-");
}

function normalizeLoose(string $value): string
{
    return preg_replace('/[._\/-]+/u', '', $value) ?? $value;
}

function findContract(string $number, array $candidates, array $indexes): ?array
{
    $all = array_values(array_unique(array_filter(array_merge([$number], $candidates), static fn(string $v): bool => $v !== '')));

    foreach ($all as $candidate) {
        $norm = normalizeNumberForMatch($candidate);
        if ($norm === '') {
            continue;
        }
        $matches = $indexes['by_norm'][$norm] ?? [];
        if ($matches !== []) {
            return $matches[0];
        }
    }

    foreach ($all as $candidate) {
        $norm = normalizeNumberForMatch($candidate);
        if ($norm === '') {
            continue;
        }
        $loose = normalizeLoose($norm);
        $matches = $indexes['by_loose'][$loose] ?? [];
        if ($matches !== []) {
            return $matches[0];
        }
    }

    return null;
}

/** @return array<string,mixed> */
function createContractFromFile(PDO $pdo, int $adminId, string $number, string $name, string $sourcePath): array
{
    $subject = trim(pathinfo($name, PATHINFO_FILENAME));
    if ($subject === '') {
        $subject = trim($name);
    }
    $lawType = detectLawType($name . ' ' . $sourcePath);

    $stmt = $pdo->prepare(
        "INSERT INTO contracts
            (number, subject, law_type, contractor_name, contractor_inn, total_amount, nmck_amount, currency, status, signed_at, expires_at, notes, created_by, created_at, updated_at)
         VALUES
            (:number, :subject, :law_type, :contractor_name, :contractor_inn, :total_amount, :nmck_amount, :currency, :status, :signed_at, :expires_at, :notes, :created_by, NOW(), NOW())"
    );
    $stmt->execute([
        'number' => mb_substr($number, 0, 100),
        'subject' => $subject,
        'law_type' => $lawType,
        'contractor_name' => 'Контрагент не указан',
        'contractor_inn' => null,
        'total_amount' => 0,
        'nmck_amount' => null,
        'currency' => 'RUB',
        'status' => 'active',
        'signed_at' => null,
        'expires_at' => null,
        'notes' => 'Импорт из Яндекс.Диска: ' . $sourcePath,
        'created_by' => $adminId,
    ]);

    $id = (int) $pdo->lastInsertId();
    return [
        'id' => $id,
        'number' => mb_substr($number, 0, 100),
        'subject' => $subject,
    ];
}

function detectLawType(string $text): string
{
    $t = mb_strtolower($text, 'UTF-8');
    return (str_contains($t, '44') || str_contains($t, '44-фз')) ? '44' : '223';
}

/** @return array<string,bool> */
function loadDocumentNameSet(PDO $pdo, int $contractId): array
{
    $stmt = $pdo->prepare('SELECT original_name FROM documents WHERE contract_id = :id');
    $stmt->execute(['id' => $contractId]);
    $set = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $set[normalizeNameForCompare((string) ($row['original_name'] ?? ''))] = true;
    }
    return $set;
}

function normalizeNameForCompare(string $name): string
{
    $s = mb_strtolower(trim($name), 'UTF-8');
    $s = str_replace('ё', 'е', $s);
    return preg_replace('/\s+/u', ' ', $s) ?? $s;
}

function detectDocType(string $name, string $path): string
{
    $t = mb_strtolower($name . ' ' . $path, 'UTF-8');
    if (str_contains($t, 'дополнительное соглашение') || str_contains($t, 'допсоглаш')) {
        return 'supplement';
    }
    if (preg_match('/(^|[^а-я])акт([^а-я]|$)/u', $t)) {
        return 'act';
    }
    if (str_contains($t, 'счет') || str_contains($t, 'счёт')) {
        return 'invoice';
    }
    if (str_contains($t, 'контракт') || str_contains($t, 'договор') || str_contains($t, 'гк')) {
        return 'contract';
    }
    return 'other';
}

function getDownloadUrl(string $publicKey, string $path): string
{
    $data = yadiskApi('resources/download', [
        'public_key' => $publicKey,
        'path' => $path,
    ]);
    $href = (string) ($data['href'] ?? '');
    if ($href === '') {
        throw new RuntimeException('Download URL not found for ' . $path);
    }
    return $href;
}

/** @return array{safe_name:string,relative_path:string,mime_type:string,size_bytes:int,sha256:string} */
function downloadToStorage(string $url, string $storageBase, int $contractId, string $originalName): array
{
    $subDir = 'contracts/' . $contractId;
    $targetDir = $storageBase . '/' . $subDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create directory ' . $targetDir);
    }

    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    $targetPath = $targetDir . '/' . $safeName;
    $tmpPath = tempnam(sys_get_temp_dir(), 'yadoc_');
    if ($tmpPath === false) {
        throw new RuntimeException('Cannot create temp file');
    }

    try {
        downloadBinary($url, $tmpPath);
        if (!rename($tmpPath, $targetPath)) {
            if (!copy($tmpPath, $targetPath)) {
                throw new RuntimeException('Cannot move downloaded file');
            }
            unlink($tmpPath);
        }
        @chmod($targetPath, 0640);
        $finfo = mime_content_type($targetPath);
        $size = (int) (filesize($targetPath) ?: 0);
        $hash = hash_file('sha256', $targetPath) ?: '';

        return [
            'safe_name' => $safeName,
            'relative_path' => $subDir . '/' . $safeName,
            'mime_type' => $finfo ?: 'application/octet-stream',
            'size_bytes' => $size,
            'sha256' => $hash,
        ];
    } catch (Throwable $e) {
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
        throw $e;
    }
}

function downloadBinary(string $url, string $targetPath): void
{
    if (function_exists('curl_init')) {
        $fp = fopen($targetPath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Cannot open temp file for write');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_HTTPHEADER => ['User-Agent: contract-tracker-import/1.0'],
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $code >= 400) {
            throw new RuntimeException('Download failed (' . $code . '): ' . $err);
        }
        return;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 600,
            'header' => "User-Agent: contract-tracker-import/1.0\r\n",
            'follow_location' => 1,
        ],
    ]);
    $read = @fopen($url, 'rb', false, $ctx);
    if ($read === false) {
        throw new RuntimeException('Cannot open download URL');
    }
    $write = fopen($targetPath, 'wb');
    if ($write === false) {
        fclose($read);
        throw new RuntimeException('Cannot open target file for write');
    }
    stream_copy_to_stream($read, $write);
    fclose($read);
    fclose($write);
}

function insertDocument(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO documents
           (contract_id, original_name, safe_name, relative_path, mime_type, size_bytes, sha256, doc_type, uploaded_by, created_at)
         VALUES
           (:contract_id, :original_name, :safe_name, :relative_path, :mime_type, :size_bytes, :sha256, :doc_type, :uploaded_by, NOW())'
    );
    $stmt->execute($payload);
    return (int) $pdo->lastInsertId();
}

function addSample(array &$bucket, array $row, int $max = 30): void
{
    if (count($bucket) < $max) {
        $bucket[] = $row;
    }
}
