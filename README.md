# AIMM

AIMM is a Docker-based development environment for generating financial reports. It combines a Yii2 (PHP) application, a Python renderer for PDFs, and a MySQL database.

## What’s in this repo

- `yii/` — Yii2 application (web + console)
- `python-renderer/` — Python PDF renderer (invoked by the app)
- `docker-compose.yml` — local dev stack (Nginx, PHP-FPM, MySQL, Python)
- `docs/` — rules, skills, and prompt templates

## Quick start (local dev)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec -T aimm_yii composer install
```

Open the web app:

- `http://localhost:${NGINX_PORT}` (default `http://localhost:8510`)

## Database access (DBeaver / host tools)

Use the non-root users from `.env`:

- **App user:** `DB_USER` / `DB_PASSWORD` (full access to `DB_DATABASE` + `DB_DATABASE_TEST`)
- **Admin user (optional):** `DB_ADMIN_USER` / `DB_ADMIN_PASSWORD` (dev-only; created via init script if configured)

Connection settings:

- Host: `127.0.0.1`
- Port: `DB_PORT` (default `3308`)
- Database: `DB_DATABASE` (default `aimm`)

## Docs and workflow

- `docs/README.md` — documentation entry points
- `.claude/rules/` — global guardrails (conventions, folder taxonomy, testing baseline)
- `.claude/skills/index.md` — skill catalog used for feature work

