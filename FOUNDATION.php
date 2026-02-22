<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║                                                                                ║
 * ║   🏗️ ФУНДАМЕНТ — Contract Lifecycle Tracker (223-ФЗ / 44-ФЗ)                  ║
 * ║                                                                                ║
 * ║   Мастер-документ: архитектура, стек, модули, БД, безопасность,                ║
 * ║   UI/UX, маршруты, roadmap — ВСЁ В ОДНОМ ФАЙЛЕ.                               ║
 * ║                                                                                ║
 * ║   Каждый новый модуль, таблица, маршрут — сначала вписывается сюда,            ║
 * ║   потом реализуется.                                                           ║
 * ║                                                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [1] СТЕК ТЕХНОЛОГИЙ                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   ┌─────────────────┬────────────────────────────────────────────────────────────┐
 *   │ PHP 8.5+        │ Enums, readonly properties, named arguments, match,       │
 *   │                 │ fiber-ready, str_starts_with/str_contains, union types,    │
 *   │                 │ первая_class callable (→ ready для 8.2/8.3/8.4/8.5)       │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ MySQL 8.4+      │ InnoDB, FOREIGN KEY, JSON columns, utf8mb4,               │
 *   │ MariaDB 11.8+   │ STRICT_TRANS_TABLES, prepared statements                  │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ HTML5           │ Semantic: <header>, <main>, <nav>, <section>, <article>    │
 *   │                 │ Атрибуты: role, autocomplete, autofocus, required          │
 *   │                 │ Элементы: <input type=date|number|file>, <textarea>,       │
 *   │                 │ <form enctype>, <meta color-scheme/theme-color>            │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ CSS3            │ Custom Properties (design tokens), clamp() для auto-scale, │
 *   │                 │ backdrop-filter (glass-morphism), Grid + auto-fit,         │
 *   │                 │ @keyframes анимации, ::selection, transitions,             │
 *   │                 │ linear-gradient, radial-gradient, 100dvh,                  │
 *   │                 │ @media print, responsive breakpoints                       │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ JavaScript ES16 │ Vanilla JS, querySelectorAll, addEventListener,            │
 *   │                 │ arrow functions, template literals, dataset API,            │
 *   │                 │ defer loading, ZERO зависимостей                           │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ Автомасштаб     │ clamp(14px, 1vw+10px, 16px) — типографика масштабируется  │
 *   │                 │ auto-fit + minmax() — сетки масштабируются                 │
 *   │                 │ clamp() на padding/gap — отступы масштабируются            │
 *   │                 │ @media 768px, 480px — брейкпоинты для мобилки              │
 *   │                 │ 100dvh — корректная высота на мобильных                    │
 *   ├─────────────────┼────────────────────────────────────────────────────────────┤
 *   │ Хостинг         │ Shared-хостинг. Без Docker, Node, Composer, npm.           │
 *   │                 │ Собственный PSR-4 автолоадер (autoload.php).               │
 *   │                 │ Apache (.htaccess) или nginx (rewrite).                    │
 *   └─────────────────┴────────────────────────────────────────────────────────────┘
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [2] ПРИНЦИПЫ РАЗРАБОТКИ                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   1. НОЛЬ КОПИРОВАНИЯ КОДА — повторяемая функция → src/Shared/ (Utils/Html,
 *      Security/Session, Policy/LawPolicy). Одна функция — одно место.
 *
 *   2. МОДУЛЬНАЯ ИЗОЛЯЦИЯ — если модуль Auth сломался → Contracts работают.
 *      Каждый модуль грузится в try/catch. Массив failedModules() для диагностики.
 *
 *   3. НАРАЩИВАНИЕ БЕЗ ПОЛОМОК — новый модуль = новая папка + routes.php.
 *      App.php подхватывает автоматически. Существующие модули не трогаются.
 *
 *   4. АВТОМАСШТАБ — CSS clamp() + Grid auto-fit + responsive breakpoints.
 *      Одна страница работает от 320px мобилки до 2560px монитора.
 *
 *   5. БЕЗОПАСНОСТЬ ПО УМОЛЧАНИЮ — CSRF, XSS-escape, PDO prepared statements,
 *      session hardening, RBAC, audit log — всё включено из коробки.
 *
 *   6. КРАСОТА ПО УМОЛЧАНИЮ — тёмная тема, glass-morphism, анимации,
 *      градиенты, Google Fonts (Outfit + IBM Plex Mono), stagger-reveal.
 *
 *   7. РАЗМЕР ФАЙЛА — свободный. Ограничение не по строкам, а по принципу:
 *      "Один файл = одна ответственность. Не дублировать. Не мешать всё в кучу."
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [3] АРХИТЕКТУРА — СЛОИ                                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   ┌────────────┐    ┌────────────┐    ┌────────────┐    ┌──────────┐    ┌──────┐
 *   │  Request   │───►│ Middleware  │───►│ Controller │───►│ Service  │───►│ Repo │──► MySQL
 *   └────────────┘    └────────────┘    └────────────┘    └──────────┘    └──────┘
 *                      CSRF + Auth       HTTP only         Бизнес-        Только
 *                      (цепочка)         + DTO             логика         SQL/PDO
 *                                        + Response        + Policy
 *                                                          + Audit
 *
 *   ПРАВИЛА:
 *     • Controller  — НЕТ бизнес-логики, НЕТ SQL, НЕТ Policy
 *     • Service     — НЕТ HTTP, НЕТ SQL напрямую (через Repo)
 *     • Repository  — НЕТ бизнес-правил, НЕТ HTTP
 *     • Policy      — чистые функции валидации (LawPolicy)
 *     • DTO         — типизированная передача данных (ContractCreateDto)
 *     • App.php     — DI-lite контейнер, ЕДИНСТВЕННОЕ место с audit()
 *
 *   DI-КОНТЕЙНЕР (App.php):
 *     $app->pdo()                      PDO MySQL-соединение
 *     $app->session()                  Менеджер сессий
 *     $app->csrf()                     CSRF-токены
 *     $app->make(SomeClass::class)     Lazy singleton любого класса
 *     $app->view('template', $data)    Рендер в layout → Response
 *     $app->flash('type', 'message')   Flash-сообщение
 *     $app->currentUser()              Данные текущего пользователя
 *     $app->currentUserId()            int|null
 *     $app->audit(action, ...)         Запись в audit_log (ЕДИНСТВЕННАЯ функция!)
 *     $app->failedModules()            Модули, которые не загрузились
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [4] ОТКАЗОУСТОЙЧИВАЯ МОДУЛЬНОСТЬ                                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   Как это работает:
 *
 *   App::loadModules() сканирует src/Modules/<Module>/routes.php и загружает каждый
 *   файл внутри try/catch:
 *
 *       foreach (glob('src/Modules/{module}/routes.php') as $file) {
 *           try {
 *               require $file;                   // ← модуль регистрирует маршруты
 *           } catch (\Throwable $e) {
 *               $this->moduleErrors[$name] = $e; // ← сохраняем ошибку
 *               error_log("[MODULE FAIL] ...");   // ← пишем в лог
 *               // НЕ бросаем исключение → следующий модуль грузится нормально
 *           }
 *       }
 *
 *   В шаблонах тоже try/catch:
 *       // contracts/view.php
 *       try { $payments = $app->make(PaymentsService::class)->getByContract($id); }
 *       catch (\Throwable) { $payments = []; }
 *       // Если Payments-модуль сломан — страница контракта всё равно рисуется,
 *       // просто секция платежей пустая.
 *
 *   Для admin'а: в layout.php показывается предупреждение о сломанных модулях.
 *
 *   КАК ДОБАВИТЬ НОВЫЙ МОДУЛЬ:
 *     1. mkdir src/Modules/НовыйМодуль/
 *     2. Создать routes.php (авто-подхват App.php)
 *     3. Создать Controller, Service, Repository
 *     4. Создать templates/новый_модуль/*.php
 *     5. ALTER TABLE / CREATE TABLE в database/schema.sql
 *     6. Готово. Существующие модули НЕ трогаются.
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [5] СТРУКТУРА ФАЙЛОВ                                                           ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   project/
 *   ├── FOUNDATION.php          ← ВЫ ЗДЕСЬ: мастер-документ
 *   ├── autoload.php            ← PSR-4 автозагрузчик (замена Composer)
 *   ├── .env.example            ← Шаблон конфигурации
 *   │
 *   ├── public/                 ← DocumentRoot (ЕДИНСТВЕННАЯ публичная папка)
 *   │   ├── index.php           ← Front Controller — 4 строки
 *   │   ├── .htaccess           ← Rewrite + headers + security + gzip + cache
 *   │   └── assets/
 *   │       ├── app.css         ← Дизайн-система (CSS3 custom properties, glass, grid)
 *   │       └── app.js          ← Интерактив (flash, confirm, toggle, upload)
 *   │
 *   ├── src/
 *   │   ├── App.php             ← Ядро: bootstrap, DI, module loader, audit
 *   │   ├── Http/
 *   │   │   ├── Router.php      ← Regex-роутер {id:\d+}
 *   │   │   ├── Request.php     ← Обёртка superglobals
 *   │   │   ├── Response.php    ← Immutable (html/json/redirect/download)
 *   │   │   └── Middleware/
 *   │   │       ├── CsrfMiddleware.php
 *   │   │       └── AuthMiddleware.php
 *   │   │
 *   │   ├── Infrastructure/
 *   │   │   ├── Db/PdoFactory.php     ← MySQL PDO (strict mode, utf8mb4)
 *   │   │   ├── Db/Transaction.php    ← begin/commit/rollback wrapper
 *   │   │   ├── Storage/LocalStorage.php  ← Upload: whitelist, SHA-256, path guard
 *   │   │   └── Telegram/TelegramClient.php
 *   │   │
 *   │   ├── Shared/               ← ОБЩИЕ функции (НЕ ДУБЛИРУЮТСЯ нигде!)
 *   │   │   ├── Enum/LawType.php           223/44
 *   │   │   ├── Enum/ContractStatus.php    draft/active/executed/terminated/cancelled
 *   │   │   ├── Enum/PaymentStatus.php     planned/in_progress/paid/canceled
 *   │   │   ├── Enum/StageStatus.php       planned/in_progress/completed/cancelled
 *   │   │   ├── Policy/LawPolicy.php       Валидация по закону
 *   │   │   ├── Security/Session.php       Сессии + flash + auth
 *   │   │   ├── Security/Csrf.php          CSRF-токены
 *   │   │   ├── Security/Passwords.php     argon2id/bcrypt
 *   │   │   └── Utils/
 *   │   │       ├── Env.php                .env парсер
 *   │   │       └── Html.php               e(), money(), date(), badge(), fileSize()
 *   │   │
 *   │   └── Modules/              ← ИЗОЛИРОВАННЫЕ модули
 *   │       ├── Auth/              [✅ v1] Логин, логаут, rate limit
 *   │       ├── Contracts/         [✅ v1] CRUD, фильтры, финансы, Telegram
 *   │       ├── Documents/         [✅ v1] Upload/download/delete
 *   │       ├── Payments/          [✅ v1] CRUD, статусы, привязка к контрактам
 *   │       ├── Users/             [✅ v1.1] CRUD пользователей + смена пароля
 *   │       ├── Procurements/      [✅ v2.0] Закупки + коммерческие предложения (КП)
 *   │       ├── Stages/            [✅ v2.0] Этапы контракта (plan/fact)
 *   │       └── BillingDocs/       [✅ v2.0] Счета и акты
 *   │
 *   ├── templates/
 *   │   ├── layout.php            ← HTML5 layout: topbar, flash, stagger-animation
 *   │   ├── auth/login.php        ← Standalone login page (glass-card)
 *   │   ├── contracts/
 *   │   │   ├── list.php          ← Таблица + фильтры + пагинация
 *   │   │   ├── form.php          ← Создание/редактирование (НМЦК toggle)
 *   │   │   └── view.php          ← Карточка: финансы + этапы + счета + акты + платежи + документы
 *   │   └── errors/{404,500}.php
 *   │
 *   ├── database/schema.sql       ← DDL: все таблицы + FK + индексы + seed
 *   └── storage/                  ← Файлы (ВНЕ public/, chmod 750)
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [6] БАЗА ДАННЫХ — MySQL                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   Engine: InnoDB (FK, транзакции)
 *   Charset: utf8mb4_unicode_ci
 *   Mode: STRICT_TRANS_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE
 *
 *   ТЕКУЩИЕ ТАБЛИЦЫ (v2.0):
 *
 *   users         id, login(UNIQUE), email, password_hash, full_name,
 *                 role(admin|manager|viewer), is_active, timestamps
 *
 *   contracts     id, number, subject, law_type(223|44), contractor_name,
 *                 contractor_inn, total_amount(DECIMAL 15,2), nmck_amount,
 *                 currency(RUB), status(draft|active|executed|terminated|cancelled),
 *                 signed_at, expires_at, notes, created_by→users, timestamps
 *
 *   documents     id, contract_id→contracts(CASCADE), original_name, safe_name,
 *                 relative_path, mime_type, size_bytes, sha256(CHAR 64),
 *                 doc_type(contract|supplement|act|invoice|other), uploaded_by, created_at
 *
 *   payments      id, contract_id→contracts(CASCADE), amount(DECIMAL 15,2),
 *                 status(planned|in_progress|paid|canceled), payment_date,
 *                 purpose, invoice_number, created_by, timestamps
 *
 *   contract_stages
 *                 id, contract_id→contracts(CASCADE), title,
 *                 status(planned|in_progress|completed|cancelled),
 *                 planned_date, actual_date, sort_order, description,
 *                 created_by, timestamps
 *
 *   contract_invoices
 *                 id, contract_id→contracts(CASCADE), invoice_number,
 *                 invoice_date, due_date, amount, status(issued|paid|cancelled),
 *                 comment, created_by, timestamps
 *
 *   contract_acts id, contract_id→contracts(CASCADE), act_number,
 *                 act_date, amount, status(pending|signed|rejected|cancelled),
 *                 comment, created_by, timestamps
 *
 *   audit_log     id(BIGINT), user_id, action, entity_type, entity_id,
 *                 details(JSON), ip_address, user_agent, created_at
 *
 *   ПЛАНИРУЕМЫЕ ТАБЛИЦЫ (v2+):
 *     notifications, workload
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [7] МАРШРУТЫ                                                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   ТЕКУЩИЕ:
 *   GET  /login                          Форма входа (public)
 *   POST /login                          Аутентификация (public)
 *   POST /logout                         Выход
 *   GET  /                               → redirect /contracts
 *   GET  /contracts                      Список + фильтры + пагинация
 *   GET  /contracts/new                  Форма создания
 *   POST /contracts                      Сохранить
 *   GET  /contracts/{id}                 Карточка
 *   GET  /contracts/{id}/edit            Редактировать
 *   POST /contracts/{id}                 Обновить
 *   POST /contracts/{id}/delete          Удалить (admin)
 *   POST /contracts/{id}/documents       Загрузить файл
 *   GET  /documents/{id}/download        Скачать
 *   POST /documents/{id}/delete          Удалить файл
 *   POST /contracts/{id}/payments        Добавить платёж
 *   POST /payments/{id}/update           Обновить платёж
 *   POST /payments/{id}/delete           Удалить платёж
 *   POST /contracts/{id}/stages          Добавить этап
 *   POST /stages/{id}/update             Обновить этап
 *   POST /stages/{id}/delete             Удалить этап
 *   POST /contracts/{id}/invoices        Добавить счёт
 *   POST /invoices/{id}/update           Обновить счёт
 *   POST /invoices/{id}/delete           Удалить счёт
 *   POST /contracts/{id}/acts            Добавить акт
 *   POST /acts/{id}/update               Обновить акт
 *   POST /acts/{id}/delete               Удалить акт
 *
 *   ПЛАН (v2+):
 *   GET  /procurements                   Закупки
 *   GET  /procurements/{id}              Карточка закупки
 *   POST /procurements/{id}/proposals    Добавить КП
 *   GET  /audit                          Журнал аудита (admin)
 *   GET  /users                          Управление пользователями (admin)
 *   GET  /export/contracts               CSV/XLSX экспорт
 *   GET  /dashboard                      Дашборд
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [8] БЕЗОПАСНОСТЬ                                                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   ✅ CSRF           Per-session token, CsrfMiddleware на все POST
 *   ✅ XSS            Html::e() на всём выводе, Content-Type-Options: nosniff
 *   ✅ SQL Injection   PDO prepared statements, EMULATE_PREPARES=false
 *   ✅ RBAC           3 роли (admin/manager/viewer), проверка в routes meta
 *   ✅ Session         HttpOnly, Secure, SameSite=Lax, regeneration каждые 5 мин
 *   ✅ Passwords      argon2id (bcrypt fallback), auto-rehash
 *   ✅ Rate Limit     5 попыток / 5 мин / IP на логин
 *   ✅ Upload         Whitelist ext, MIME check, SHA-256, random names,
 *                     path traversal guard, double extension guard, files вне public/
 *   ✅ Audit          Все мутации: who + what + when + IP + user-agent
 *   ✅ .htaccess      X-Frame-Options, X-XSS-Protection, block dot-files,
 *                     gzip, static cache, block .php uploads
 *   ✅ SQL Mode       STRICT_TRANS_TABLES + дополнительные режимы
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [9] UI / CSS3 / HTML5 / ДИЗАЙН-СИСТЕМА                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   ТЕМА: тёмная, glass-morphism, gradient accents, stagger-reveal
 *   ШРИФТЫ: Outfit (body, 300-800) + IBM Plex Mono (числа, код)
 *
 *   CSS3 ФИЧИ:
 *     • Custom Properties (30+ токенов: --bg-0..--bg-4, --accent, --emerald и т.д.)
 *     • clamp() — автомасштаб типографики и отступов
 *     • Grid auto-fit + minmax() — сетки адаптируются к любому экрану
 *     • backdrop-filter: blur(20px) — frosted glass topbar
 *     • radial-gradient — фоновые свечения
 *     • linear-gradient — кнопки, заголовки, -webkit-background-clip: text
 *     • @keyframes — flash-in, login-up, stagger-in анимации
 *     • ::selection — кастомный цвет выделения
 *     • 100dvh — динамическая высота viewport (мобилки)
 *     • @media print — печать без навигации
 *
 *   HTML5 ФИЧИ:
 *     • Semantic: <header role="banner">, <main role="main">, <nav role="navigation">
 *     • <meta color-scheme="dark">, <meta name="theme-color">
 *     • <input type="date">, <input type="number" step="0.01">
 *     • required, autofocus, autocomplete="username"/"current-password"
 *     • <form enctype="multipart/form-data">
 *
 *   КОМПОНЕНТЫ:
 *     .btn (--primary, --ghost, --danger, --sm, --icon)
 *     .card (.card__head, .card__title)
 *     .badge (--emerald, --amber, --rose, --cyan, --sky, --slate)
 *     .form-grid, .fg, .form-actions
 *     .table-wrap + table (hover, responsive scroll)
 *     .finance-row + .fin-card (--g green, --a amber, --r rose)
 *     .flash (--success, --error) + auto-dismiss JS
 *     .detail-grid, .di
 *     .filters
 *     .pgn (pagination)
 *     .upload-zone (drag-drop style)
 *     .empty, .err-list, .section-title, .page-head, .login-card
 *     .stagger (stagger-in animation на page load)
 *
 *   RESPONSIVE:
 *     768px — компактная навигация, 2-колоночные finance cards
 *     480px — одноколоночные формы, скрытые текстовые лейблы
 *     Масштабируется от 320px до 2560px+
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [10] БИЗНЕС-ПРАВИЛА                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   223-ФЗ: НМЦК не обязательна. Стандартная валидация.
 *   44-ФЗ:  НМЦК обязательна > 0. Остальное — как 223-ФЗ.
 *
 *   Обязательные поля: номер, предмет, контрагент, сумма >= 0.
 *
 *   Переходы статусов:
 *     draft → active | cancelled
 *     active → executed | terminated
 *     cancelled → draft
 *     executed → (финал)
 *     terminated → (финал)
 *
 *   Финансы:
 *     paid_sum  = SUM(payments WHERE status='paid')
 *     remaining = total_amount - paid_sum
 *     overspend = paid_sum > total_amount
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [11] РАЗВЁРТЫВАНИЕ                                                             ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   SHARED-ХОСТИНГ:
 *     1. Залить проект на сервер
 *     2. public/ → DocumentRoot (или симлинк)
 *     3. cp .env.example .env → заполнить DB_HOST, DB_NAME, DB_USER, DB_PASS
 *     4. Импортировать database/schema.sql (phpMyAdmin или CLI)
 *     5. chmod -R 750 storage/
 *     6. Войти admin / admin123 → СМЕНИТЬ ПАРОЛЬ
 *
 *   ЛОКАЛЬНО:
 *     cp .env.example .env
 *     mysql -u root < database/schema.sql
 *     php -S localhost:8000 -t public/
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ [12] ROADMAP                                                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   v1.0 (ТЕКУЩАЯ) ─── ФУНДАМЕНТ
 *     [✅] Auth: логин/логаут, rate limit, audit
 *     [✅] Contracts: CRUD, фильтры, пагинация, финансы, Telegram
 *     [✅] Documents: upload/download/delete с защитой
 *     [✅] Payments: CRUD, статусы, остаток/перерасход
 *     [✅] Безопасность: CSRF, RBAC, session, hashing, XSS, SQLi
 *     [✅] UI: CSS3, переключение тёмной/светлой темы, русификация интерфейса
 *     [✅] MySQL: users, contracts, documents, payments, procurements, КП, stages, audit_log
 *     [✅] HTML5: семантика, доступность, формы
 *     [✅] Отказоустойчивость: модули изолированы
 *
 *   v1.1
 *     [✅] Users: CRUD (admin), смена пароля
 *     [✅] Export: CSV реестр контрактов
 *     [✅] RBAC: проверки в каждом сервисе
 *
 *   v2.0
 *     [✅] Procurements: закупки с КП
 *     [✅] Stages: этапы контракта (plan/fact)
 *     [✅] Invoices + Acts: счета и акты
 *     [ ] Audit UI: просмотр лога (admin)
 *     [ ] Notifications: дедлайны, просрочки
 *     [ ] Export: XLSX, ZIP-пакет
 *
 *   v2.1
 *     [ ] Dashboard: сводка, графики, метрики
 *     [ ] PDF: паспорт контракта
 *     [ ] Email: SMTP уведомления
 *
 *   v3.0
 *     [ ] API: JSON endpoints
 *     [ ] Workload: нагрузка сотрудников
 *     [ ] S3/MinIO storage
 *     [ ] Versioning контрактов
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║ ИТОГО                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *   Стек:        PHP 8.5+ · MySQL · HTML5 · CSS3 · ES6+ JavaScript
 *   Зависимости: 0 (ни Composer, ни npm, ни фреймворки)
 *   Модули:      8 изолированных (Auth, Contracts, Documents, Payments, Users, Procurements, Stages, BillingDocs)
 *   Таблицы:     9 + audit_log
 *   Принцип:     один модуль падает → система работает
 *   Масштаб:     от 320px мобилки до 4K монитора
 *   Хостинг:     любой shared с PHP 8.5 и MySQL
 */
