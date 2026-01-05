---
name: upgrade-php-version
description: Upgrade the PHP runtime version used by the Yii2 application (Docker + Composer constraints) and validate the dev stack still builds and runs.
area: meta
depends_on:
  - docs/RULES.md
---

# UpgradePhpVersion

Upgrade the repository’s PHP version in a minimal, consistent way:

- Docker base image for the Yii2 container
- Composer PHP requirement and lock platform
- Documentation references to the supported PHP version

## Contract

### Inputs

- `repoRoot` (directory): repository root (must contain `docker-compose.yml` and `yii/composer.json`)
- `phpVersion` (string): target PHP version (e.g. `8.5`)

### Outputs

- `docker/yii/Dockerfile` uses `php:${phpVersion}-fpm`
- `yii/composer.json` requires `php >= ${phpVersion}`
- `yii/composer.lock` platform PHP is updated to match
- Documentation references to the minimum PHP version are updated

### Safety / invariants

- Do not add `declare(strict_types=1);`
- Keep Yii2 architecture unchanged; only update version/config/docs needed for the runtime upgrade
- Do not commit `.env`

## Steps

### 1) Update Docker PHP base image

- Edit `docker/yii/Dockerfile` `FROM php:<old>-fpm` to `FROM php:${phpVersion}-fpm`

### 2) Update Composer PHP requirement

- Edit `yii/composer.json`:
  - `require.php` to `>=${phpVersion}`

### 3) Update lock file platform

Run under the target PHP version (recommended inside the Docker container):

```bash
docker compose up -d --build aimm_yii
docker compose exec -T aimm_yii composer update --lock
```

### 4) Update docs

- Update minimum PHP version references in docs (e.g. `docs/PROJECT.md`, skill docs)

## Definition of Done

- `docker compose build aimm_yii` succeeds
- `docker compose exec -T aimm_yii php -v` reports the target version
- `docker compose exec -T aimm_yii composer check-platform-reqs` passes
- Repo documentation reflects the new minimum PHP version
