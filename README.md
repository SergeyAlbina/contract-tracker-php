# Contract Lifecycle Tracker (223-ФЗ / 44-ФЗ)

CRM-подобная система для контроля жизненного цикла закупки и контракта:
**Закупка → КП → решение → контракт → этапы → счета/акты → оплаты → закрытие → архив**.

## Зачем
- единый реестр контрактов + история и аудит
- автоматические риски: истечения 90/30/10, перерасход, просрочки этапов
- документы “не теряются”: хранилище на сервере + скачивание + выгрузки (ZIP/PDF)
- удобный UI в стиле **Material You** (desktop + mobile responsive)

## MVP (v1)
- Закупки (Procurement): создание, статусы, привязка КП
- КП (Commercial Proposals): PENDING/ACCEPTED/REJECTED, замечания, цены
- Контракты: 223 (приоритет) + 44 (закладка модели), паспорт, статусы
- Этапы (обязательные): план/факт/просрочка
- Финансы: счета/акты/оплаты, остаток = сумма контракта − сумма оплат (PAID)
- Документы: загрузка на VPS, скачивание, ссылки (Диадок/1С), пакетная выгрузка ZIP
- Уведомления: Telegram (руководителю КС), позже email/группы
- Аудит: кто/что/когда изменил

## Технологии (рекомендация)
- Backend: NestJS (Node.js + TypeScript)
- DB: MySQL
- ORM: Prisma
- Frontend: Next.js + MUI (Material UI, Material You)
- Storage v1: локальный диск VPS (`./storage`)
- Storage v2: MinIO (S3-совместимое хранилище) в docker-compose

## Старт
1) Создайте `.env` (см. `.env.example`)
2) Поднимите зависимости:
- `docker compose up -d db`
3) Миграции:
- `pnpm db:migrate`
4) Запуск:
- API: `pnpm dev:api`
- Web: `pnpm dev:web`

## Документация
- `docs/FUNDAMENTAL_CODING.md` — конституция проекта (архитектура, правила, API)
- `docs/ROADMAP.md` — дорожная карта v1 → v2
- `docs/CLOUD_CLAUDE_RULES.md` — правила использования Claude Cloud/Claude Code/Skills/MCP
- `docs/ARCHITECTURE.md` — архитектурный обзор (страницы UI, модули, потоки)

## Лицензия
TBD
