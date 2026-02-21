# FUNDAMENTAL_CODING — Contract Lifecycle Tracker (223/44)

## 0) Принципы
1) Меньше кода — больше функционала.
2) Никаких “полотен”: файл > 250–300 строк → дробим.
3) Никакого копипаста: повторяемое → `packages/shared`.
4) Модульность: один модуль = одна предметная область.
5) UI-скелет красивый с первого дня (Material You + responsive).
6) Место для манёвров: версионирование + feature flags/policies.

---

## 1) Стек
- Backend: NestJS + TypeScript (strict)
- DB: MySQL
- ORM: Prisma
- Frontend: Next.js + MUI (Material You)
- Notifications: Telegram (v1), Email (v2)
- Storage: local disk (v1), MinIO/S3 (v2)
- Export: XLSX/CSV/ZIP + passport HTML→PDF

---

## 2) Repo layout (монорепа)
/
  apps/
    api/        # NestJS
    web/        # Next.js + MUI
  packages/
    shared/     # общие типы, enums, валидаторы, утилиты
  docs/
    FUNDAMENTAL_CODING.md
    ROADMAP.md
    CLOUD_CLAUDE_RULES.md
    ARCHITECTURE.md

---

## 3) Архитектура слоёв
- Controller: HTTP, DTO, статус-коды, без бизнес-логики
- Service: бизнес-правила, расчёты, транзакции
- Repo/DAO: доступ к данным, без правил
- Mapper/Converter: DTO <-> Domain

Запрещено:
- Prisma в контроллерах
- отдавать DB-модели напрямую
- “магические” проверки по месту — всё в services/policies

---

## 4) Доменные модули (v1)
- auth, users
- procurements (Закупки)
- commercial-proposals (КП)
- contracts
- stages (обязательные)
- invoices, acts, payments
- documents
- notifications
- audit
- workload

---

## 5) Модель: ключевые правила
### 5.1 Законы
- lawType: 223 | 44
- НМЦК (nmckAmount) обязательна только при lawType=44 (policy)

### 5.2 Финансы
- payment.status: PLANNED | IN_PROGRESS | PAID | CANCELED
- остаток = contract.totalAmount - sum(payments.amount WHERE status=PAID)
- перерасход: sum(PAID) > totalAmount → flag OVERSPEND

### 5.3 Закупка → КП → Контракт
- Procurement (Закупка): происхождение будущего контракта
- КП: PENDING/ACCEPTED/REJECTED + notes + offeredAmount
- Создание контракта из закупки: “принятая КП” может заполнить поля контракта

---

## 6) Storage (документы)
### v1 Local disk
- корень: `./storage`
- путь: `storage/contracts/<contractId>/<docId>/<safe_filename>`
- в DB: {originalName, mime, size, sha256, relativePath, uploadedBy, access}

### v2 MinIO/S3
- STORAGE_DRIVER=local|s3
- presigned URL downloads
- политики доступа (bucket policy)

---

## 7) Export
- Реестр: XLSX/CSV
- Пакет контракта: ZIP (документы + метаданные + passport.html)
- Паспорт: HTML (v1) → PDF (v1.1)

Обязательная защита:
- zip-slip: запрещены `../` в путях архива


---

## 12) Эксплуатация и безопасность
См. `docs/OPS_AND_SECURITY.md`, `docs/BACKUP_AND_RESTORE.md`, `docs/RBAC_MATRIX.md`.
