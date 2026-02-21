# BACKUP_AND_RESTORE — v1/v2

## 1) v1: MySQL + local storage

### Backup
```bash
mkdir -p backups
mysqldump -h localhost -u contract -p --single-transaction contract_tracker > backups/db_$(date +%F).sql
tar -czf backups/storage_$(date +%F).tar.gz storage/
```

### Restore (на тестовом окружении)
```bash
mysql -h localhost -u contract -p contract_tracker < backups/db_YYYY-MM-DD.sql
tar -xzf backups/storage_YYYY-MM-DD.tar.gz -C .
```

## 2) v2: MySQL + MinIO (S3)

### Backup
- DB: как в v1
- MinIO:
  - через `mc mirror` (MinIO Client) на отдельный диск/сервер
  - или бэкап docker volume (если допустимо)

### Restore
- DB restore
- MinIO restore (mirror обратно)
