# OPS_AND_SECURITY — эксплуатация, безопасность, бэкапы, деплой

Этот документ — практический чек‑лист, чтобы система была безопасной и переносимой:
- бэкапы и восстановление (v1 local storage / v2 MinIO)
- загрузка файлов (ограничения + скан в v2)
- безопасный экспорт ZIP
- логирование + traceId
- healthchecks и readiness
- reverse proxy + TLS + HSTS
- миграции БД (Prisma discipline)
- RBAC матрица доступа

---

## 1) Бэкапы и восстановление (обязательно)

### 1.1 Что бэкапим
**v1 (Local disk):**
- DB: MySQL dump
- Files: директория `./storage`
- Export cache: `./tmp/exports` (по желанию)

**v2 (MinIO/S3):**
- DB: MySQL dump
- Files: MinIO bucket(ы) (`contracts` и т.п.)

### 1.2 Частота (рекомендация)
- DB dump: ежедневно (и + перед релизом/миграцией)
- Files/storage: ежедневно/еженедельно (зависит от объёма)
- Retention: 14–30 дней + monthly snapshot

### 1.3 Минимальные команды (пример)
**MySQL dump:**
```bash
mysqldump -h <host> -u <user> -p --single-transaction --routines --events contract_tracker > backups/db_$(date +%F).sql
```

**Архив storage (v1):**
```bash
tar -czf backups/storage_$(date +%F).tar.gz storage/
```

**MinIO bucket export (v2):**
- через `mc` (MinIO Client) или бэкап docker volume (если MinIO в compose)

### 1.4 Проверка восстановления (важно)
Раз в месяц делай тест:
- поднять копию окружения
- восстановить DB + storage
- открыть UI и скачать любой документ

---

## 2) Upload документов (ограничения и безопасность)

### 2.1 Ограничения v1
- лимит размера: `UPLOAD_MAX_MB` (по умолчанию 50)
- allowlist расширений (пример): `pdf, doc, docx, xls, xlsx, csv, zip, png, jpg`
- allowlist MIME типов (проверять и расширение, и MIME)
- нормализация имени файла (safe filename), запрет path traversal

### 2.2 Антивирус/скан в v2
Рекомендуется интеграция с **ClamAV**:
- upload -> временная папка -> scan -> только после OK сохраняем в storage
- при отказе: удаляем временный файл + audit event

---

## 3) Безопасный ZIP export

### 3.1 Риски
- Zip Slip: `../` в имени файла внутри архива может перезаписать файлы при распаковке
- Вредоносные/сломанные имена файлов

### 3.2 Обязательные правила
- все пути внутри ZIP генерируются сервером (не доверять пользовательским путям)
- запрет `..`, `:` и абсолютных путей
- безопасная нормализация имени файла
- ограничение размера итогового архива

---

## 4) Логи + traceId

### 4.1 Зачем
- разбор инцидентов (ошибки API, Telegram, экспорты)
- корреляция запросов

### 4.2 Правила
- генерируем `traceId` на каждый запрос (middleware)
- добавляем `traceId` в ответ и в логи
- логируем ошибки с контекстом (endpoint, userId, entityId)
- запрещено логировать: токены, пароли, 2FA секреты, содержимое документов

---

## 5) Healthchecks и readiness

### 5.1 Endpoints
- `GET /health` — liveness (API живо)
- `GET /ready` — readiness (DB доступна, storage доступен, миграции применены)

### 5.2 Docker / Proxy
- docker healthcheck дергает `/health`
- reverse proxy может использовать `/ready` для upstream

---

## 6) Reverse proxy + TLS

### 6.1 Рекомендация
- Caddy или Nginx
- HTTPS обязателен
- HSTS включить (после проверки)

### 6.2 Минимальные заголовки
- HSTS
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY/SAMEORIGIN
- Referrer-Policy
- CSP (если нужно)

---

## 7) Миграции БД (Prisma discipline)

### 7.1 Правила
- любые изменения схемы — только через миграции `prisma migrate`
- “ручные” правки на проде запрещены
- перед деплоем: бэкап DB
- миграции прогоняются автоматически при деплое (или отдельным шагом)

### 7.2 Процесс релиза (минимум)
1) backup db + storage
2) apply migrations
3) deploy api/web
4) smoke test (login + list contracts + download document)

---

## 8) RBAC матрица (в одном месте)
См. `docs/RBAC_MATRIX.md` — единый источник прав.
Любое изменение прав → обновление матрицы.
