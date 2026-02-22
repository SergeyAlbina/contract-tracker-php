# Contract Tracker (PHP 8.5)

Contract Lifecycle Tracker для 223-ФЗ / 44-ФЗ без фреймворков:
- PHP 8.5+
- MySQL 8+
- Vanilla JS + CSS3

## Что реализовано

- Auth: логин/логаут, rate limit, audit
- Contracts: CRUD, фильтры, пагинация, карточка, финансы
- Procurements: CRUD закупок + КП (выбор победителя)
- Payments: CRUD, статусы, привязка к контракту
- Documents: upload/download/delete с защитой
- Users: admin CRUD + смена пароля
- Export: CSV-выгрузка реестра контрактов
- Security: CSRF, session hardening, prepared statements, RBAC

## Требования

- PHP `8.5+` с расширениями: `pdo_mysql`, `mbstring` (желательно), `json`
- MySQL `8.0+` (или совместимый MariaDB)
- Web server с document root на `public/` (или встроенный `php -S` для dev)

## Локальный запуск

1. Скопировать конфиг:
```bash
cp .env.example .env
```

2. Создать БД и пользователя (пример):
```sql
CREATE DATABASE contract_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'contract_user'@'127.0.0.1' IDENTIFIED BY 'contract_pass';
GRANT ALL PRIVILEGES ON contract_tracker.* TO 'contract_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

3. Импортировать схему:
```bash
mysql -h127.0.0.1 -ucontract_user -pcontract_pass contract_tracker < database/schema.sql
```

4. Запустить сервер:
```bash
php -S 127.0.0.1:8000 -t public
```

5. Открыть:
- `http://127.0.0.1:8000/login`
- логин: `admin`
- пароль: `admin123`

## Основные маршруты

- `GET /contracts` — список контрактов
- `GET /contracts/export.csv` — CSV экспорт (учитывает фильтры `search/law_type/status`)
- `GET /procurements` — список закупок
- `GET /users` — управление пользователями (admin)
- `GET /profile/password` — смена своего пароля

## Минимальный прод-чеклист

1. Сгенерировать безопасный `APP_SECRET`:
```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```
и записать его в `.env`.

2. Включить secure-cookie:
```env
SESSION_SECURE=true
```
3. Выключить debug:
```env
APP_DEBUG=false
```
4. Поменять пароль пользователя `admin` сразу после первого входа.
5. Держать `storage/` вне публичного доступа.
