# ARCHITECTURE — Contract Lifecycle Tracker

## 1) Обзор
Система для контроля жизненного цикла закупки и контракта (223/44):
**Закупка → КП → Решение → Контракт → Этапы → Счета/Акты → Оплаты → Закрытие/Архив**.

Цели архитектуры:
- модульность (без монолитных файлов и копипаста)
- безопасность по умолчанию (закрытый доступ, RBAC, аудит)
- расширяемость (v1→v2: бухгалтерия/филиалы/2FA/MinIO/интеграции)
- красивый и единый UI (Material You, responsive)

---

## 2) Компоненты
### 2.1 Backend (NestJS)
- REST API `/api/v1`
- JWT auth + refresh
- RBAC (ADMIN, HEAD_CS, SPECIALIST_CS)

### 2.2 Frontend (Next.js + MUI)
- AppShell (drawer/topbar), light/dark theme
- responsive: desktop/tablet/mobile

### 2.3 Storage
- v1: Local disk (`./storage`)
- v2: MinIO (S3) via `STORAGE_DRIVER=s3`

---

## 3) Страницы UI (каркас)
- Dashboard (KPI + тревоги)
- Закупки (кейсы + КП + создать контракт)
- Контракты (реестр + карточка с tabs)
- Документы/Экспорт (ZIP/PDF/HTML)

---

## 4) API (каркас)
- Auth: login/refresh/logout/me
- Procurements + Proposals: CRUD + create-contract
- Contracts: CRUD + stages + invoices/acts/payments + finance
- Documents: upload/download/list
- Export: registry xlsx/csv, contract zip, passport html/pdf

---

## 5) Диаграммы (текстом)
### 5.1 Поток
Procurement -> Proposals -> Decision -> Contract -> Stages -> Invoices/Acts/Payments -> Close

### 5.2 Слои
Controller -> Service -> Repo -> DB
           -> StorageProvider
           -> NotificationProvider
           -> AuditService
