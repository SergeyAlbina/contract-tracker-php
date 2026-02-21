# CODEX HANDOFF (2026-02-22)

## Что уже сделано
- Обновлены зависимости монорепо до актуальных версий (включая major):
  - Next.js 16, React 19, MUI 7, NestJS 11, Prisma 7.
- Исправлены фронтенд-поломки после обновления:
  - заменены импорты `Grid2` на `Grid`;
  - поправлен экспорт enum/value в `apps/web/src/types/api.ts`;
  - убраны неактуальные `@ts-expect-error` в `ThemeRegistry`.
- Исправлены backend-поломки после Prisma 7:
  - добавлен `apps/api/prisma.config.ts`;
  - генерация клиента идёт в `apps/api/src/generated/prisma`;
  - `PrismaService` переведён на Prisma 7 adapter (`@prisma/adapter-mariadb`).
- Починен seed под Prisma 7 adapter.
- Стабилизирована проверка типов:
  - `apps/web/package.json` -> `typecheck: tsc --noEmit --incremental false`;
  - `apps/api/tsconfig.json` -> `incremental: false`;
  - `.gitignore` доп. правило `*.tsbuildinfo`.

## Текущий статус запуска
- `http://localhost:3000/login` отвечает (`200`).
- API слушает `:4000`, `/api/v1/auth/me` даёт `401` без токена (ожидаемо).
- MySQL на `:3306` не слушает.
- Из-за отсутствия БД не проходит нормальный login и не выполняются `db push/seed`.

## Дефолтные учетные данные (после поднятия БД и seed)
- `admin@contract-tracker.local` / `Admin1234!`
- `head@contract-tracker.local` / `Head1234!`

## Что осталось доделать (когда будет БД)
1. Поднять MySQL (локально или через Docker, если решите вернуть Docker).
2. Выполнить в `apps/api`:
   - `pnpm exec prisma db push`
   - `pnpm db:seed`
3. Перезапустить API:
   - `pnpm dev:api`

## Быстрый старт после перезапуска машины
- Web: `pnpm dev:web`
- API: `pnpm dev:api`
- Проверка:
  - `http://localhost:3000/login`
  - `http://localhost:4000/api/v1/auth/me` (должен быть `401` без токена)

## Важные измененные файлы (ядро)
- `apps/api/prisma.config.ts`
- `apps/api/prisma/seed.ts`
- `apps/api/src/prisma/prisma.service.ts`
- `apps/web/src/components/contracts/ContractForm.tsx`
- `apps/web/src/app/(dashboard)/contracts/[id]/page.tsx`
- `apps/web/src/app/(dashboard)/procurements/[id]/page.tsx`
- `apps/web/src/components/providers/ThemeRegistry.tsx`
- `apps/web/src/types/api.ts`
- `apps/web/package.json`
- `apps/api/tsconfig.json`
- `.gitignore`

## Для нового чата
В новом чате просто попросите:
`Прочитай docs/CODEX_HANDOFF.md и продолжим с этого места`.
