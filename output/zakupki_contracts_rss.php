#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Zakupki contracts collector (PHP).
 *
 * Features:
 * - pulls contracts from RSS search results with filters from search URL
 * - enriches contracts from contract card pages (supplier INN, dates, subject)
 * - extracts attachment URLs and classifies contract/payment documents
 * - supports JSON/CSV output
 * - supports local cache + retry/backoff for 429/5xx
 *
 * CLI example:
 * php output/zakupki_contracts_rss.php --year=2026 --insecure --format=json --output=output/contracts_2026.json
 */

const DEFAULT_SEARCH_URL = 'https://zakupki.gov.ru/epz/contract/search/results.html?morphology=on&search-filter=%D0%94%D0%B0%D1%82%D0%B5+%D1%80%D0%B0%D0%B7%D0%BC%D0%B5%D1%89%D0%B5%D0%BD%D0%B8%D1%8F&fz44=on&contractStageList_0=on&contractStageList_1=on&contractStageList_2=on&contractStageList_3=on&contractStageList=0%2C1%2C2%2C3&budgetLevelsIdNameHidden=%7B%7D&customerIdOrg=52410%3A%D0%9A%D0%90%D0%97%D0%95%D0%9D%D0%9D%D0%9E%D0%95+%D0%A3%D0%A7%D0%A0%D0%95%D0%96%D0%94%D0%95%D0%9D%D0%98%D0%95+%D0%A5%D0%90%D0%9D%D0%A2%D0%AB-%D0%9C%D0%90%D0%9D%D0%A1%D0%98%D0%99%D0%A1%D0%9A%D0%9E%D0%93%D0%9E+%D0%90%D0%92%D0%A2%D0%9E%D0%9D%D0%9E%D0%9C%D0%9D%D0%9E%D0%93%D0%9E+%D0%9E%D0%9A%D0%A0%D0%A3%D0%93%D0%90+-+%D0%AE%D0%93%D0%A0%D0%AB+%22%D0%91%D0%AE%D0%A0%D0%9E+%D0%A1%D0%A3%D0%94%D0%95%D0%91%D0%9D%D0%9E-%D0%9C%D0%95%D0%94%D0%98%D0%A6%D0%98%D0%9D%D0%A1%D0%9A%D0%9E%D0%99+%D0%AD%D0%9A%D0%A1%D0%9F%D0%95%D0%A0%D0%A2%D0%98%D0%97%D0%AB%22zZ03872000026zZ618936zZzZ8601009193zZzZ860101001zZ1028600507231&sortBy=UPDATE_DATE&pageNumber=1&sortDirection=false&recordsPerPage=_50&showLotsInfoHidden=false';

final class ZakupkiCollector
{
    /** @var array<string,mixed> */
    private $cfg;

    /** @var array<string,mixed> */
    private $cache = [];

    /** @var array<string,array<string,string>> */
    private $detailsMemo = [];

    /** @var string[] */
    private $csvFields = [
        'registry_number',
        'contract_number',
        'contract_date',
        'signed_date',
        'execution_start_date',
        'execution_end_date',
        'contract_status',
        'supplier_name',
        'supplier_inn',
        'price',
        'currency',
        'customer',
        'contract_subject',
        'contract_docs',
        'contract_doc_urls',
        'payment_docs',
        'payment_doc_urls',
        'attachment_files',
        'attachment_urls',
        'contract_invalid',
        'published_date',
        'updated_date',
        'pub_date_gmt',
        'card_url',
    ];

    /**
     * @param array<string,mixed> $cfg
     */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->cache = $this->loadCache((string)$cfg['cache_file']);
    }

    public function saveCache(): void
    {
        $cacheFile = (string)$this->cfg['cache_file'];
        if ($cacheFile === '') {
            return;
        }
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents(
            $cacheFile,
            json_encode($this->cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function collect(): array
    {
        $rows = [];
        $seen = [];
        $pageSize = $this->parseRecordsPerPage((string)$this->readQueryParam('recordsPerPage', '_50'));
        $maxPages = (int)$this->cfg['max_pages'];
        $maxContracts = (int)$this->cfg['max_contracts'];

        for ($page = 1; $page <= $maxPages; $page++) {
            $this->log("Fetch RSS page {$page}");
            [$rssUrl, $referer] = $this->buildRssRequest((string)$this->cfg['search_url'], (int)$this->cfg['year'], $page);
            $rssXml = $this->fetch($rssUrl, $referer);
            $items = $this->parseRssItems($rssXml);
            if ($items === []) {
                break;
            }

            $newRows = 0;
            foreach ($items as $item) {
                if (!$this->isYearMatch($item, (int)$this->cfg['year'])) {
                    continue;
                }
                $rowId = $item['registry_number'] !== '' ? $item['registry_number'] : $item['card_url'];
                if (isset($seen[$rowId])) {
                    continue;
                }
                $seen[$rowId] = true;
                $rows[] = $item;
                $newRows++;

                if ($maxContracts > 0 && count($rows) >= $maxContracts) {
                    break;
                }
            }

            if ($maxContracts > 0 && count($rows) >= $maxContracts) {
                break;
            }
            if (count($items) < $pageSize || $newRows === 0) {
                break;
            }
            $this->pause();
        }

        usort($rows, function (array $a, array $b): int {
            $aTs = max(
                $this->parseRuDate($a['published_date']),
                $this->parseRuDate($a['updated_date']),
                $this->parseRuDate($a['contract_date'])
            );
            $bTs = max(
                $this->parseRuDate($b['published_date']),
                $this->parseRuDate($b['updated_date']),
                $this->parseRuDate($b['contract_date'])
            );
            return $bTs <=> $aTs;
        });

        if ((bool)$this->cfg['skip_details']) {
            $this->log('Skip details mode enabled (RSS only).');
            return $rows;
        }

        $ttlHours = (float)$this->cfg['cache_ttl_hours'];
        $cacheHits = 0;
        $total = count($rows);
        foreach ($rows as $idx => &$row) {
            $cardUrl = $row['card_url'];
            if (isset($this->detailsMemo[$cardUrl])) {
                $row = array_merge($row, $this->detailsMemo[$cardUrl]);
                continue;
            }

            $cached = $this->getCachedDetails($cardUrl, $ttlHours);
            if ($cached !== null) {
                $cacheHits++;
                $details = $cached;
                $this->log(sprintf('Enrich %d/%d %s (cache)', $idx + 1, $total, $row['registry_number']));
            } else {
                $this->log(sprintf('Enrich %d/%d %s (web)', $idx + 1, $total, $row['registry_number']));
                $details = $this->enrichRow($row);
                $this->putCachedDetails($cardUrl, $details);
            }

            $this->detailsMemo[$cardUrl] = $details;
            $row = array_merge($row, $details);
            $this->pause();
        }
        unset($row);

        $this->log(sprintf('Cache hits: %d/%d', $cacheHits, $total));
        return $rows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     */
    public function write(string $format, string $output, array $rows): string
    {
        if ($format === 'csv') {
            $target = $output !== '' ? $output : 'php://stdout';
            $fp = fopen($target, 'wb');
            if ($fp === false) {
                throw new RuntimeException("Cannot open {$target} for writing");
            }
            fputcsv($fp, $this->csvFields);
            foreach ($rows as $row) {
                $line = [];
                foreach ($this->csvFields as $f) {
                    $line[] = $row[$f] ?? '';
                }
                fputcsv($fp, $line);
            }
            fclose($fp);
            return $output !== '' ? $output : 'stdout';
        }

        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        if ($output !== '') {
            file_put_contents($output, $json);
            return $output;
        }
        echo $json;
        return 'stdout';
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function parseRssItems(string $xmlRaw): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlRaw, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($xml === false || !isset($xml->channel->item)) {
            return [];
        }

        $rows = [];
        foreach ($xml->channel->item as $item) {
            $title = trim((string)$item->title);
            $link = trim((string)$item->link);
            $desc = html_entity_decode((string)$item->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $contractNumber = $this->extractLastField($desc, 'Контракт №');
            $rows[] = [
                'registry_number' => $this->extractRegistryNumber($title, $link),
                'contract_number' => $contractNumber,
                'contract_date' => $this->extractContractDate($contractNumber),
                'signed_date' => '',
                'execution_start_date' => '',
                'execution_end_date' => '',
                'contract_status' => $this->extractLastField($desc, 'Статус контракта'),
                'supplier_name' => '',
                'supplier_inn' => '',
                'price' => $this->extractLastField($desc, 'Цена контракта'),
                'currency' => $this->extractLastField($desc, 'Валюта'),
                'customer' => $this->extractLastField($desc, 'Заказчик'),
                'contract_subject' => '',
                'contract_docs' => '',
                'contract_doc_urls' => '',
                'payment_docs' => '',
                'payment_doc_urls' => '',
                'attachment_files' => '',
                'attachment_urls' => '',
                'contract_invalid' => $this->extractLastField($desc, 'Контракт признан недействительным'),
                'published_date' => $this->extractLastField($desc, 'Размещено'),
                'updated_date' => $this->extractLastField($desc, 'Обновлено'),
                'pub_date_gmt' => $this->normalizeText((string)$item->pubDate),
                'card_url' => $this->absoluteUrl($link),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string,string> $row
     * @return array<string,string>
     */
    private function enrichRow(array $row): array
    {
        $details = [
            'signed_date' => '',
            'execution_start_date' => '',
            'execution_end_date' => '',
            'supplier_name' => '',
            'supplier_inn' => '',
            'contract_subject' => '',
            'contract_docs' => '',
            'contract_doc_urls' => '',
            'payment_docs' => '',
            'payment_doc_urls' => '',
            'attachment_files' => '',
            'attachment_urls' => '',
        ];

        $cardUrl = $row['card_url'];
        try {
            $commonHtml = $this->fetch($cardUrl, (string)$this->cfg['search_url']);
            $supplier = $this->extractSupplierInfo($commonHtml);
            $details['supplier_name'] = $supplier['supplier_name'];
            $details['supplier_inn'] = $supplier['supplier_inn'];
            $details['signed_date'] = $this->extractCommonField($commonHtml, 'Дата заключения контракта');
            $details['execution_start_date'] = $this->extractCommonField($commonHtml, 'Дата начала исполнения контракта');
            $details['execution_end_date'] = $this->extractCommonField($commonHtml, 'Дата окончания исполнения контракта');
            $details['contract_subject'] = $this->extractCommonField($commonHtml, 'Предмет контракта');
        } catch (Throwable $e) {
            // keep defaults
        }

        if ($details['supplier_inn'] === '' || $details['supplier_name'] === '') {
            try {
                $paymentUrl = str_replace('/contractCard/common-info.html', '/contractCard/payment-info-and-target-of-order.html', $cardUrl);
                $paymentHtml = $this->fetch($paymentUrl, $cardUrl);
                $paymentSupplier = $this->extractSupplierFromPaymentInfo($paymentHtml);
                if ($details['supplier_inn'] === '') {
                    $details['supplier_inn'] = $paymentSupplier['supplier_inn'];
                }
                if ($details['supplier_name'] === '') {
                    $details['supplier_name'] = $paymentSupplier['supplier_name'];
                }
            } catch (Throwable $e) {
                // keep defaults
            }
        }

        try {
            $docUrl = str_replace('/contractCard/common-info.html', '/contractCard/document-info.html', $cardUrl);
            $docHtml = $this->fetch($docUrl, $cardUrl);
            $entries = $this->extractAttachmentEntries($docHtml);
            $classified = $this->classifyEntries($entries);
            $details = array_merge($details, $classified);
        } catch (Throwable $e) {
            // keep defaults
        }

        return $details;
    }

    private function fetch(string $url, string $referer): string
    {
        $retries = (int)$this->cfg['http_retries'];
        $timeout = (int)$this->cfg['timeout'];
        $insecure = (bool)$this->cfg['insecure'];
        $lastErr = 'unknown';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_HTTPHEADER => [
                    'Referer: ' . $referer,
                    'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
                ],
            ]);
            if ($insecure) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0 && $status >= 200 && $status < 300 && is_string($body)) {
                return $body;
            }

            $lastErr = $errno !== 0 ? ("curl {$errno}: {$err}") : "http {$status}";
            $retryableStatus = in_array($status, [429, 500, 502, 503, 504], true);
            if ($attempt >= $retries || (!$retryableStatus && $errno === 0)) {
                break;
            }
            $sleep = min(30.0, 1.2 * (2 ** $attempt) + mt_rand(5, 45) / 100.0);
            usleep((int)($sleep * 1000000));
        }

        throw new RuntimeException("Request failed: {$lastErr}; url={$url}");
    }

    /**
     * @return array{0:string,1:string}
     */
    private function buildRssRequest(string $searchUrl, int $year, int $page): array
    {
        $parts = parse_url($searchUrl);
        if ($parts === false) {
            throw new RuntimeException('Invalid search URL');
        }
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['publishDateFrom'] = sprintf('01.01.%d', $year);
        $query['publishDateTo'] = sprintf('31.12.%d', $year);
        $query['pageNumber'] = (string)$page;

        $path = $parts['path'] ?? '/epz/contract/search/results.html';
        $path = str_replace('/results.html', '/rss', $path);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'zakupki.gov.ru';
        $rssUrl = $scheme . '://' . $host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return [$rssUrl, $searchUrl];
    }

    private function parseRecordsPerPage(string $value): int
    {
        if (preg_match('/(\d+)/', $value, $m)) {
            return (int)$m[1];
        }
        return 50;
    }

    private function readQueryParam(string $key, string $fallback): string
    {
        $parts = parse_url((string)$this->cfg['search_url']);
        if ($parts === false || !isset($parts['query'])) {
            return $fallback;
        }
        $query = [];
        parse_str($parts['query'], $query);
        return isset($query[$key]) ? (string)$query[$key] : $fallback;
    }

    /**
     * @param array<string,string> $row
     */
    private function isYearMatch(array $row, int $year): bool
    {
        $ys = (string)$year;
        foreach (['published_date', 'updated_date', 'contract_date'] as $k) {
            if ($this->endsWith((string)$row[$k], '.' . $ys)) {
                return true;
            }
        }
        return strpos($row['pub_date_gmt'], $ys) !== false;
    }

    private function extractLastField(string $descHtml, string $label): string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*:\s*<\/strong>\s*([^<]+)/ui';
        if (!preg_match_all($pattern, $descHtml, $m) || empty($m[1])) {
            return '';
        }
        $last = $m[1][count($m[1]) - 1];
        return $this->normalizeText($last);
    }

    private function extractContractDate(string $contractNumber): string
    {
        if (preg_match('/от\s+(\d{2}\.\d{2}\.\d{4})/u', $contractNumber, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractRegistryNumber(string $title, string $link): string
    {
        if (preg_match('/(\d{10,})/', $title, $m)) {
            return $m[1];
        }
        if (preg_match('/reestrNumber=(\d+)/', $link, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractCommonField(string $commonHtml, string $label): string
    {
        $pattern = '/<span[^>]*class="section__title"[^>]*>\s*' . preg_quote($label, '/') .
            '\s*<\/span>\s*<span[^>]*class="section__info"[^>]*>(.*?)<\/span>/uis';
        if (!preg_match($pattern, $commonHtml, $m)) {
            return '';
        }
        return $this->stripHtml($m[1]);
    }

    /**
     * @return array{supplier_name:string,supplier_inn:string}
     */
    private function extractSupplierInfo(string $commonHtml): array
    {
        if (!preg_match('/<h2 class="blockInfo__title">Информация о поставщиках<\/h2>(.*?)<\/table>/uis', $commonHtml, $sm)) {
            return ['supplier_name' => '', 'supplier_inn' => ''];
        }
        $section = $sm[1];
        preg_match_all('/ИНН:\s*<\/span>\s*<span>\s*([0-9]{10,12})\s*<\/span>/ui', $section, $innM);
        $inns = array_values(array_unique($innM[1] ?? []));

        preg_match_all('/<td[^>]*class="tableBlock__col tableBlock__col_first text-break"[^>]*>(.*?)<\/td>/uis', $section, $nameM);
        $names = [];
        foreach (($nameM[1] ?? []) as $raw) {
            $clean = trim(explode('ИНН:', $this->stripHtml($raw))[0]);
            if ($clean !== '') {
                $names[] = $clean;
            }
        }
        $names = array_values(array_unique($names));

        return [
            'supplier_name' => implode('; ', $names),
            'supplier_inn' => implode(',', $inns),
        ];
    }

    /**
     * @return array{supplier_name:string,supplier_inn:string}
     */
    private function extractSupplierFromPaymentInfo(string $paymentHtml): array
    {
        if (!preg_match('/<table[^>]+id="_supplier"[^>]*>(.*?)<\/table>/uis', $paymentHtml, $sm)) {
            return ['supplier_name' => '', 'supplier_inn' => ''];
        }
        $section = $sm[1];
        preg_match_all('/ИНН:\s*<\/span>\s*([0-9]{10,12})/ui', $section, $innM);
        $inns = array_values(array_unique($innM[1] ?? []));

        preg_match_all('/<td[^>]*class="col-3 p-2"[^>]*>(.*?)<\/td>/uis', $section, $nameM);
        $names = [];
        foreach (($nameM[1] ?? []) as $raw) {
            $clean = trim(explode('ИНН:', $this->stripHtml($raw))[0]);
            if ($clean !== '') {
                $names[] = $clean;
            }
        }
        $names = array_values(array_unique($names));

        return [
            'supplier_name' => implode('; ', $names),
            'supplier_inn' => implode(',', $inns),
        ];
    }

    /**
     * @return array<int,array{title:string,url:string}>
     */
    private function extractAttachmentEntries(string $docHtml): array
    {
        $entries = [];
        $seen = [];
        if (!preg_match_all(
            '/<a[^>]+href="((?:https:\/\/zakupki\.gov\.ru)?\/44fz\/filestore\/public\/1\.0\/download\/[^"]+)"[^>]*>(.*?)<\/a>/uis',
            $docHtml,
            $matches,
            PREG_SET_ORDER
        )) {
            return $entries;
        }

        foreach ($matches as $m) {
            $full = $m[0];
            $url = $this->absoluteUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $title = $this->stripHtml($m[2] ?? '');
            if (preg_match('/title="([^"]+)"/ui', $full, $tm)) {
                $title = $this->stripHtml($tm[1]);
            }
            if ($title === '') {
                $title = $url;
            }
            $entries[] = ['title' => $title, 'url' => $url];
        }
        return $entries;
    }

    /**
     * @param array<int,array{title:string,url:string}> $entries
     * @return array<string,string>
     */
    private function classifyEntries(array $entries): array
    {
        $paymentPatterns = [
            '/\bсч[её]т(?:[а-я]*)\b/ui',
            '/\bакт(?:[а-я]*)\b/ui',
            '/оплат/ui',
            '/плат[её]ж/ui',
            '/при[её]мк/ui',
            '/накладн/ui',
            '/\binvoice\b/ui',
        ];
        $contractPatterns = [
            '/\bдоговор(?:[а-я]*)\b/ui',
            '/\bконтракт(?:[а-я]*)\b/ui',
            '/\bгк\b/ui',
        ];

        $payment = [];
        $contract = [];
        foreach ($entries as $e) {
            if ($this->matchesAny($e['title'], $paymentPatterns)) {
                $payment[] = $e;
            }
            if ($this->matchesAny($e['title'], $contractPatterns)) {
                $contract[] = $e;
            }
        }

        return [
            'attachment_files' => implode('; ', array_map(function ($e) { return $e['title']; }, $entries)),
            'attachment_urls' => implode('; ', array_map(function ($e) { return $e['url']; }, $entries)),
            'payment_docs' => implode('; ', array_map(function ($e) { return $e['title']; }, $payment)),
            'payment_doc_urls' => implode('; ', array_map(function ($e) { return $e['url']; }, $payment)),
            'contract_docs' => implode('; ', array_map(function ($e) { return $e['title']; }, $contract)),
            'contract_doc_urls' => implode('; ', array_map(function ($e) { return $e['url']; }, $contract)),
        ];
    }

    /**
     * @param string[] $patterns
     */
    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (preg_match($p, $text) === 1) {
                return true;
            }
        }
        return false;
    }

    private function parseRuDate(string $date): int
    {
        $dt = DateTimeImmutable::createFromFormat('d.m.Y', trim($date));
        return $dt ? $dt->getTimestamp() : 0;
    }

    private function normalizeText(string $v): string
    {
        $v = str_replace("\xc2\xa0", ' ', $v);
        $v = preg_replace('/\s+/u', ' ', trim($v)) ?? trim($v);
        return $v;
    }

    private function stripHtml(string $v): string
    {
        $v = preg_replace('/<br\s*\/?>/ui', ' ', $v) ?? $v;
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = strip_tags($v);
        return $this->normalizeText($v);
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if ($this->startsWith($url, '//')) {
            return 'https:' . $url;
        }
        if ($this->startsWith($url, '/')) {
            return 'https://zakupki.gov.ru' . $url;
        }
        return 'https://zakupki.gov.ru/' . ltrim($url, '/');
    }

    /**
     * @return array<string,mixed>
     */
    private function loadCache(string $file): array
    {
        if ($file === '' || !is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string,string>|null
     */
    private function getCachedDetails(string $cardUrl, float $ttlHours): ?array
    {
        if (!isset($this->cache[$cardUrl]) || !is_array($this->cache[$cardUrl])) {
            return null;
        }
        $item = $this->cache[$cardUrl];
        $fetchedAt = isset($item['fetched_at']) ? (float)$item['fetched_at'] : 0.0;
        $data = $item['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        if ($ttlHours > 0 && (time() - $fetchedAt) > ($ttlHours * 3600)) {
            return null;
        }
        /** @var array<string,string> $casted */
        $casted = [];
        foreach ($data as $k => $v) {
            $casted[(string)$k] = (string)$v;
        }
        return $casted;
    }

    /**
     * @param array<string,string> $details
     */
    private function putCachedDetails(string $cardUrl, array $details): void
    {
        $this->cache[$cardUrl] = [
            'fetched_at' => time(),
            'data' => $details,
        ];
    }

    private function pause(): void
    {
        $pause = (float)$this->cfg['request_pause'];
        if ($pause > 0) {
            usleep((int)($pause * 1000000));
        }
    }

    private function log(string $message): void
    {
        if ((bool)$this->cfg['verbose']) {
            fwrite(STDERR, "[info] {$message}\n");
        }
    }

    private function startsWith(string $text, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }
        return substr($text, 0, strlen($prefix)) === $prefix;
    }

    private function endsWith(string $text, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }
        $len = strlen($suffix);
        if ($len > strlen($text)) {
            return false;
        }
        return substr($text, -$len) === $suffix;
    }
}

/**
 * @return array<string,mixed>
 */
function defaultConfig(): array
{
    return [
        'search_url' => DEFAULT_SEARCH_URL,
        'year' => 2026,
        'max_pages' => 20,
        'max_contracts' => 0,
        'request_pause' => 0.7,
        'timeout' => 30,
        'http_retries' => 4,
        'insecure' => false,
        'skip_details' => false,
        'cache_file' => __DIR__ . '/.zakupki_card_cache.json',
        'cache_ttl_hours' => 72.0,
        'verbose' => false,
        'format' => 'json',
        'output' => '',
    ];
}

/**
 * @param array<string,mixed> $cfg
 */
function runCollector(array $cfg): int
{
    $collector = new ZakupkiCollector($cfg);
    $rows = $collector->collect();
    $target = $collector->write((string)$cfg['format'], (string)$cfg['output'], $rows);
    $collector->saveCache();
    fwrite(STDERR, sprintf("Saved %d contracts for %d to %s.\n", count($rows), (int)$cfg['year'], $target));
    return 0;
}

if (PHP_SAPI === 'cli') {
    $cfg = defaultConfig();
    $opts = getopt('', [
        'search-url::',
        'year::',
        'max-pages::',
        'max-contracts::',
        'request-pause::',
        'timeout::',
        'http-retries::',
        'insecure',
        'skip-details',
        'cache-file::',
        'cache-ttl-hours::',
        'verbose',
        'format::',
        'output::',
    ]);
    if ($opts !== false) {
        if (isset($opts['search-url'])) {
            $cfg['search_url'] = (string)$opts['search-url'];
        }
        if (isset($opts['year'])) {
            $cfg['year'] = (int)$opts['year'];
        }
        if (isset($opts['max-pages'])) {
            $cfg['max_pages'] = (int)$opts['max-pages'];
        }
        if (isset($opts['max-contracts'])) {
            $cfg['max_contracts'] = (int)$opts['max-contracts'];
        }
        if (isset($opts['request-pause'])) {
            $cfg['request_pause'] = (float)$opts['request-pause'];
        }
        if (isset($opts['timeout'])) {
            $cfg['timeout'] = (int)$opts['timeout'];
        }
        if (isset($opts['http-retries'])) {
            $cfg['http_retries'] = (int)$opts['http-retries'];
        }
        if (isset($opts['insecure'])) {
            $cfg['insecure'] = true;
        }
        if (isset($opts['skip-details'])) {
            $cfg['skip_details'] = true;
        }
        if (isset($opts['cache-file'])) {
            $cfg['cache_file'] = (string)$opts['cache-file'];
        }
        if (isset($opts['cache-ttl-hours'])) {
            $cfg['cache_ttl_hours'] = (float)$opts['cache-ttl-hours'];
        }
        if (isset($opts['verbose'])) {
            $cfg['verbose'] = true;
        }
        if (isset($opts['format'])) {
            $fmt = strtolower((string)$opts['format']);
            if (in_array($fmt, ['json', 'csv'], true)) {
                $cfg['format'] = $fmt;
            }
        }
        if (isset($opts['output'])) {
            $cfg['output'] = (string)$opts['output'];
        }
    }
    exit(runCollector($cfg));
}

// HTTP mode (if included in a PHP site route)
$cfg = defaultConfig();
if (isset($_GET['search_url'])) {
    $cfg['search_url'] = (string)$_GET['search_url'];
}
if (isset($_GET['year'])) {
    $cfg['year'] = (int)$_GET['year'];
}
if (isset($_GET['max_pages'])) {
    $cfg['max_pages'] = (int)$_GET['max_pages'];
}
if (isset($_GET['max_contracts'])) {
    $cfg['max_contracts'] = (int)$_GET['max_contracts'];
}
if (isset($_GET['request_pause'])) {
    $cfg['request_pause'] = (float)$_GET['request_pause'];
}
if (isset($_GET['timeout'])) {
    $cfg['timeout'] = (int)$_GET['timeout'];
}
if (isset($_GET['http_retries'])) {
    $cfg['http_retries'] = (int)$_GET['http_retries'];
}
if (isset($_GET['insecure']) && $_GET['insecure'] === '1') {
    $cfg['insecure'] = true;
}
if (isset($_GET['skip_details']) && $_GET['skip_details'] === '1') {
    $cfg['skip_details'] = true;
}
if (isset($_GET['cache_file'])) {
    $cfg['cache_file'] = (string)$_GET['cache_file'];
}
if (isset($_GET['cache_ttl_hours'])) {
    $cfg['cache_ttl_hours'] = (float)$_GET['cache_ttl_hours'];
}
if (isset($_GET['verbose']) && $_GET['verbose'] === '1') {
    $cfg['verbose'] = true;
}

header('Content-Type: application/json; charset=utf-8');
try {
    $collector = new ZakupkiCollector($cfg);
    $rows = $collector->collect();
    $collector->saveCache();
    echo json_encode(
        [
            'ok' => true,
            'count' => count($rows),
            'year' => (int)$cfg['year'],
            'data' => $rows,
        ],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        [
            'ok' => false,
            'error' => $e->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
}
