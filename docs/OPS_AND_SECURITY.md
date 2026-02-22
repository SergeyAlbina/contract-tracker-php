# Operations & Security

## Deployment (Shared Hosting)

### Directory Layout
```
/home/user/
  public_html/        ← DocumentRoot (= project's public/)
    index.php
    .htaccess
    assets/
  contract-tracker/   ← project root (NOT accessible from web)
    src/
    templates/
    storage/
    vendor/
    .env
```

### Steps
1. Upload project files above `public_html`
2. Symlink or copy `public/` contents into `public_html/`
3. Adjust `index.php` require path: `require_once __DIR__ . '/../contract-tracker/vendor/autoload.php';`
4. Run `composer install --no-dev -o`
5. Copy `.env.example` → `.env`, fill in credentials
6. Import `database/schema.sql` via phpMyAdmin or CLI
7. Set storage directory permissions: `chmod -R 750 storage/`
8. **Change default admin password immediately!**

### .htaccess Security
The included `.htaccess`:
- Routes all requests through front controller
- Blocks access to dotfiles (`.env`, `.git`)
- Sets security headers (X-Content-Type-Options, X-Frame-Options)

### Storage Security
- Files stored OUTSIDE public web root
- Served only through DocumentsController (RBAC checked)
- Filenames randomized (32-char hex)
- PHP execution disabled in storage directory
- Extensions whitelist enforced on upload

## Session Security
- `HttpOnly` — no JS access
- `Secure` — HTTPS only (enable in .env)
- `SameSite=Lax` — CSRF protection
- ID regenerated every 5 minutes

## Rate Limiting
- Login: max 5 attempts per 5 min per IP (session-based)
- For production: consider fail2ban or Cloudflare

## Audit Log
All mutations recorded in `audit_log` table:
- user_id, action, entity_type, entity_id
- IP address, timestamp
- JSON details field for context

## Backups
Recommended:
- Daily DB dump: `mysqldump -u user -p dbname > backup_$(date +%F).sql`
- Daily storage rsync: `rsync -a storage/ /backup/storage/`
- Keep 30 days of rolling backups
