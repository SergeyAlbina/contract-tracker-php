# ЕИС RSS Collector (PHP 7.3+)

Скрипт: `output/zakupki_contracts_rss.php`

Назначение:
- собрать контракты из ЕИС по RSS-поиску;
- обогатить данные с карточек контрактов;
- получить ссылки на вложения (договоры/платёжные/прочие документы);
- сохранить результат в `json` или `csv`.

## Поддерживаемые поля выгрузки

- `supplier_inn`, `supplier_name`
- `signed_date`
- `execution_start_date`, `execution_end_date`
- `contract_subject`
- `contract_docs`, `contract_doc_urls`
- `payment_docs`, `payment_doc_urls`
- `attachment_files`, `attachment_urls`

## CLI запуск

```bash
php output/zakupki_contracts_rss.php \
  --year=2026 \
  --insecure \
  --request-pause=0.7 \
  --http-retries=4 \
  --format=csv \
  --output=output/contracts_2026.csv \
  --verbose
```

Если ЕИС возвращает `429`, увеличьте `--request-pause` до `1.0-1.5`.

## Основные CLI параметры

- `--search-url`
- `--year`
- `--max-pages`
- `--max-contracts`
- `--request-pause`
- `--timeout`
- `--http-retries`
- `--insecure`
- `--skip-details`
- `--cache-file`
- `--cache-ttl-hours`
- `--verbose`
- `--format=json|csv`
- `--output=/path/file.json|csv`

## HTTP режим

Скрипт можно открыть через веб-сервер: он отдаёт JSON и принимает GET-параметры:

- `year`, `max_pages`, `max_contracts`
- `request_pause`, `timeout`, `http_retries`
- `insecure=1`, `skip_details=1`
- `cache_file`, `cache_ttl_hours`, `verbose=1`

## Кэш

По умолчанию кэш карточек сохраняется в:

- `output/.zakupki_card_cache.json`

Кэш ускоряет повторные запуски и уменьшает нагрузку на ЕИС.
