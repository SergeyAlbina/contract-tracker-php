# Claude Cloud / Claude Code: правила, skills, MCP, безопасность

## 0) Термины
- **Claude Code** — агентный инструмент в терминале: https://code.claude.com/docs/en/quickstart
- **Skills** — декларативные “навыки”: https://code.claude.com/docs/en/skills
- **MCP (Model Context Protocol)** — стандарт подключения инструментов: https://www.anthropic.com/news/model-context-protocol
- **MinIO** — S3-совместимое объектное хранилище (локальный “S3” в Docker во v2)

---

## 1) Рекомендуемые skills/режимы
- safe-reader — читать и анализировать, без изменений
- refactor-writer — правки только в заданной области
- api-contracts — DTO/OpenAPI + ошибки
- frontend-material-you — каркас UI на Next.js + MUI
- security-review — auth/RBAC/secrets/uploads/zip-slip

---

## 2) Guardrails
- секреты только через ENV
- upload/download только авторизованным + проверка прав
- ZIP export: защита от zip-slip (запрет `../`)
- массовые изменения — маленькими коммитами/PR
