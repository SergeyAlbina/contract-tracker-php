# Architecture — Contract Lifecycle Tracker

## Layers

```
Request → Front Controller → Router → Middleware → Controller → Service → Repository → DB
                                                       ↓
                                                    Policy (validation)
                                                       ↓
                                                    DTO / Mapper
```

## Key Rules

1. **Controller** — only HTTP: parse request, call service, return response
2. **Service** — business logic, transactions, policies, notifications
3. **Repository** — only SQL/PDO, no business rules
4. **Policy** — law-specific validation (223-FZ / 44-FZ rules)
5. **DTO** — typed data transfer between layers

## DI Container

`App.php` acts as a lightweight DI container:
- `$app->pdo()` — database connection
- `$app->session()` — session manager
- `$app->csrf()` — CSRF token manager
- `$app->make(ClassName::class)` — lazy singleton factory

## Module Structure

Each module in `src/Modules/<Name>/` contains:
- `routes.php` — route definitions
- `<Name>Controller.php` — HTTP handling
- `<Name>Service.php` — business logic
- `<Name>Repository.php` — database access
- `Dto/` — data transfer objects (optional)

## Request Lifecycle

1. `public/index.php` boots `App`
2. `.env` loaded → PDO created → Session started → CSRF initialized
3. Module `routes.php` files auto-loaded
4. Router matches URI → extracts params
5. CSRF middleware checks POST requests
6. Auth middleware checks session (unless route is `public`)
7. Controller method invoked
8. Response sent

## Security Layers

- Session: HttpOnly + Secure + SameSite cookies
- CSRF: per-session token validated on all POST
- Auth: session-based with periodic ID regeneration
- Passwords: argon2id (bcrypt fallback)
- Upload: whitelist + size limit + path traversal protection
- Audit: all mutations logged with user/IP/timestamp
