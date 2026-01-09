---
name: setup-project
description: "Bootstrap the AIMM Yii2 + Gotenberg development environment using Docker Compose v2 (PHP-FPM, Nginx, MySQL, Gotenberg). Scaffolds Docker/Yii files and provides verification commands. Non-goal: feature work."
area: meta
depends_on:
  - docs/RULES.md
---

# SetupProject

Bootstrap the AIMM development environment in the repository root using Docker Compose.

This skill creates a minimal Yii2 (console + web) scaffold, a Gotenberg PDF renderer service, and a MySQL database,
wired together through Nginx + PHP-FPM.

For global conventions (coding style, folder taxonomy), follow `docs/RULES.md` rather than re-defining them here.

## Quick start (once files exist)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec -T aimm_yii composer install

docker compose exec -T aimm_yii php yii test/index
docker compose exec -T aimm_yii php yii test/db
docker compose exec -T aimm_gotenberg curl -f http://localhost:3000/health
```

## Prerequisites

- Docker Engine / Docker Desktop with Compose v2 (`docker compose`)
- A shell capable of running the commands below:
    - Linux/macOS: bash/zsh
    - Windows: WSL2 or Git Bash (recommended). PowerShell users can still follow the file templates and create
      directories manually.

## Contract

### Inputs

- `repoRoot` (directory): the repo root (must contain `AGENTS.md` and `docs/RULES.md`)
- Local configuration in `.env` (copy from `.env.example`), at minimum:
    - `DB_ROOT_PASSWORD`, `DB_DATABASE`, `DB_DATABASE_TEST`, `DB_USER`, `DB_PASSWORD`
    - `NGINX_PORT`, `DB_PORT` (host ports)
    - Optional: `USER_ID`, `USER_NAME` (recommended on Linux/WSL to avoid bind-mount permission issues)
    - Optional Xdebug: `XDEBUG_MODE`, `XDEBUG_CONFIG`

### Outputs

After completion, the repo root contains (at least) these new files/directories:

- Docker:
    - `docker-compose.yml`
    - `docker/yii/Dockerfile`
    - `docker/gotenberg/Dockerfile`
    - `docker/init-scripts/init-databases.sh`
    - `nginx.conf.template`
- Local env:
    - `.env.example`
    - `.env` (local only; gitignored)
    - `.gitignore` updated/created to ignore secrets and runtime artifacts
- Yii2 scaffold:
    - `yii/composer.json`
    - `yii/config/{console.php,web.php,db.php,params.php,container.php}`
    - `yii/web/index.php`
    - `yii/src/...` (taxonomy per `docs/RULES.md`)

Running `docker compose up -d --build` starts these services:

- `aimm_yii` (PHP-FPM + Composer + Yii CLI)
- `aimm_nginx` (routes web traffic to PHP-FPM)
- `aimm_mysql` (MySQL 8)
- `aimm_gotenberg` (Gotenberg PDF renderer)

### Non-goals

- Implementing collection/analysis/rendering business logic
- Adding CI/CD, production deployment, or production security hardening
- Performing destructive resets (dropping DB volumes) unless explicitly requested

### Safety / invariants

- Never commit `.env` (secrets). `.env.example` is safe to commit.
- MySQL init scripts run only on first initialization of the MySQL data directory/volume.
- DB privileges must not be global (`GRANT ... ON *.*` is forbidden here); grant only to the configured databases.

## Steps

### 1) Preflight

Run from the repo root:

```bash
test -f AGENTS.md || { echo "error: run from repo root (AGENTS.md not found)"; exit 1; }
test -f docs/RULES.md || { echo "error: docs/RULES.md not found"; exit 1; }
```

This skill writes new files in the repo root (and will overwrite if you paste over existing ones):

- `docker-compose.yml`
- `nginx.conf.template`
- `.env.example`
- `.gitignore`
- files under `docker/`, `yii/`, `data/`

### 2) Create directories

The command below uses Bash brace expansion. If you are using PowerShell, create the same directories manually.

```bash
mkdir -p docker/{gotenberg,init-scripts,yii}
mkdir -p data/db/mysql
mkdir -p yii/{config/{industries,schemas},migrations,runtime/{datapacks,reports,logs},tests/{unit,integration,fixtures},web}
mkdir -p yii/src/{Adapters,Clients,Commands,Controllers,Dto/Datapoints,enums,exceptions,Factories,Handlers/{Collection,Analysis,Rendering},Queries,Transformers,Validators}
```

### 3) Create Docker Compose

Create `docker-compose.yml`:

```yaml
services:
  aimm_yii:
    build:
      context: .
      dockerfile: ./docker/yii/Dockerfile
      args:
        USER_ID: ${USER_ID:-1000}
        USER_NAME: ${USER_NAME:-appuser}
        PHP_FPM_PORT: ${PHP_FPM_PORT:-9000}
        TIMEZONE: ${TIMEZONE:-Europe/Amsterdam}
    working_dir: /var/www/html/yii
    env_file:
      - .env
    environment:
      TZ: ${TIMEZONE:-Europe/Amsterdam}
      PHP_IDE_CONFIG: "serverName=Docker"
      XDEBUG_MODE: ${XDEBUG_MODE:-off}
      XDEBUG_CONFIG: ${XDEBUG_CONFIG:-}
    extra_hosts:
      - "host.docker.internal:host-gateway"
    volumes:
      - .:/var/www/html
    depends_on:
      - aimm_mysql
      - gotenberg

  aimm_nginx:
    image: nginx:1.27
    ports:
      - "${NGINX_PORT:-8510}:80"
    volumes:
      - .:/var/www/html:ro
      - ./nginx.conf.template:/etc/nginx/nginx.conf.template:ro
    environment:
      PHP_FPM_PORT: ${PHP_FPM_PORT:-9000}
    command: >
      sh -c "envsubst '$$PHP_FPM_PORT' < /etc/nginx/nginx.conf.template
      > /etc/nginx/nginx.conf && nginx -g 'daemon off;'"
    depends_on:
      - yii

  aimm_mysql:
    image: mysql:8.0
    environment:
      TZ: ${TIMEZONE:-Europe/Amsterdam}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      # NOTE: not a standard mysql image variable; consumed by docker/init-scripts/init-databases.sh
      MYSQL_DATABASE_TEST: ${DB_DATABASE_TEST}
    ports:
      - "${DB_PORT:-3307}:3306"
    volumes:
      - ./data/db/mysql:/var/lib/mysql
      - ./docker/init-scripts:/docker-entrypoint-initdb.d:ro
    healthcheck:
      # mysqladmin ping checks server liveness; no auth required
      test: [ "CMD-SHELL", "mysqladmin ping -h localhost --silent" ]
      interval: 5s
      timeout: 5s
      retries: 20

  gotenberg:
    container_name: aimm_gotenberg
    build:
      context: ./docker/gotenberg
      dockerfile: Dockerfile
    restart: unless-stopped
    command:
      - "gotenberg"
      - "--api-timeout=30s"
    healthcheck:
      test: [ "CMD", "curl", "-f", "http://localhost:3000/health" ]
      interval: 10s
      timeout: 3s
      retries: 5
    environment:
      LOG_LEVEL: info
```

### 4) Create the PHP (Yii) Dockerfile

Create `docker/yii/Dockerfile`:

```dockerfile
FROM php:8.5-fpm

ARG PHP_FPM_PORT=9000
ARG TIMEZONE=Europe/Amsterdam
ARG USER_ID=1000
ARG USER_NAME=appuser

RUN echo "date.timezone=${TIMEZONE}" > /usr/local/etc/php/conf.d/00-timezone.ini
RUN echo "expose_php=Off" > /usr/local/etc/php/conf.d/00-security.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg-dev libicu-dev \
    iputils-ping netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
      pdo_mysql mbstring zip exif pcntl bcmath gd intl

# Xdebug: port 9878 avoids WSL conflicts with 9003.
# Runtime toggles via env vars (XDEBUG_MODE / XDEBUG_CONFIG).
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && { \
      echo "zend_extension=xdebug.so"; \
      echo "xdebug.mode=off"; \
      echo "xdebug.start_with_request=trigger"; \
      echo "xdebug.discover_client_host=1"; \
      echo "xdebug.client_port=9878"; \
      echo "xdebug.idekey=PHPSTORM"; \
      echo "xdebug.connect_timeout_ms=200"; \
    } > /usr/local/etc/php/conf.d/99-xdebug.ini

RUN { \
      echo "max_execution_time=600"; \
      echo "register_argc_argv=On"; \
    } > /usr/local/etc/php/conf.d/99-custom.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN adduser --uid ${USER_ID} --disabled-password --gecos "" ${USER_NAME}

WORKDIR /var/www/html/yii

RUN sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/zz-docker.conf

USER ${USER_NAME}

CMD ["php-fpm"]
```

Xdebug usage:

- Web requests: set `XDEBUG_MODE=debug,develop` in `.env`, then `docker compose up -d` (or restart `aimm_yii`)
- CLI (one-off):
  `docker compose exec -e XDEBUG_MODE=debug -e XDEBUG_CONFIG="client_host=host.docker.internal client_port=9878" aimm_yii php yii <command>`

### 5) Create the Gotenberg Dockerfile

Create `docker/gotenberg/Dockerfile`:

```dockerfile
FROM gotenberg/gotenberg:8
USER root
RUN apt-get update \
    && apt-get install -y curl \
    && rm -rf /var/lib/apt/lists/*
USER gotenberg
```

### 6) Create the database init script (test DB + grants)

Create `docker/init-scripts/init-databases.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${MYSQL_DATABASE_TEST:-}" ]]; then
  echo "error: MYSQL_DATABASE_TEST is required (set DB_DATABASE_TEST in .env)" >&2
  exit 1
fi

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE_TEST}\`;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE_TEST}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
```

Notes:

- The official `mysql:8.0` image does not recognize `MYSQL_DATABASE_TEST`; it is a custom env var used only by this init
  script.
- Init scripts only run when the MySQL data directory is empty. If you change DB names/users/passwords later, you must
  wipe the data directory to re-run init (destructive).

### 7) Create the Nginx config template

Create `nginx.conf.template`:

```nginx
worker_processes auto;

events {
    worker_connections 1024;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    proxy_connect_timeout 300;
    proxy_send_timeout 300;
    proxy_read_timeout 300;
    client_max_body_size 100M;

    server {
        listen 80;
        server_name localhost;

        root /var/www/html/yii/web;
        index index.php index.html;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass yii:${PHP_FPM_PORT};
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_connect_timeout 300;
            fastcgi_send_timeout 300;
            fastcgi_read_timeout 300;
        }

        access_log /var/log/nginx/access.log;
        error_log /var/log/nginx/error.log;
    }
}
```

### 8) Create `.env.example` (and local `.env`)

Create `.env.example`:

```bash
# Linux/WSL: set USER_ID to `id -u` and USER_NAME to `id -un`.
# macOS/Windows: defaults are usually OK.
USER_ID=1000
USER_NAME=appuser

TIMEZONE=Europe/Amsterdam

# Host ports (inside Docker network: Nginx is 80, MySQL is 3306)
NGINX_PORT=8510
DB_PORT=3307

# Internal PHP-FPM port (Nginx -> PHP-FPM only)
PHP_FPM_PORT=9000

# Database (dev only)
DB_HOST=aimm_mysql
DB_ROOT_PASSWORD=root_secret
DB_DATABASE=aimm
DB_DATABASE_TEST=aimm_test
DB_USER=aimm
DB_PASSWORD=aimm_secret

# Yii web (dev only)
COOKIE_VALIDATION_KEY=dev-only-change-me

# Xdebug (optional; port 9878 avoids WSL conflicts)
XDEBUG_MODE=off
# CLI example:
# XDEBUG_CONFIG="client_host=host.docker.internal client_port=9878 idekey=PHPSTORM"
```

Create your local `.env` (do not commit it):

```bash
cp .env.example .env
```

### 9) Create the Yii2 application skeleton

Create `yii/composer.json`:

```json
{
  "name": "aimm/equity-research",
  "description": "Equity intelligence pipeline",
  "type": "project",
  "require": {
    "php": ">=8.5",
    "yiisoft/yii2": "~2.0.49",
    "opis/json-schema": "^2.3",
    "symfony/process": "^6.4",
    "yiisoft/yii2-queue": "^2.3",
    "ramsey/uuid": "^4.7"
  },
  "require-dev": {
    "codeception/codeception": "^5.0",
    "codeception/module-asserts": "^3.0",
    "codeception/module-yii2": "^1.1"
  },
  "autoload": {
    "psr-4": {
      "app\\": "src/"
    }
  },
  "config": {
    "allow-plugins": {
      "yiisoft/yii2-composer": true
    }
  }
}
```

Create `yii/yii` (console entrypoint). **Important:** keep the shebang line, so it can be run as `./yii` once
executable:

```php
#!/usr/bin/env php
<?php

use yii\console\Application;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';

$application = new Application($config);
exit($application->run());
```

Create `yii/web/index.php` (web entrypoint):

```php
<?php

use yii\web\Application;

defined('YII_DEBUG') || define('YII_DEBUG', getenv('YII_DEBUG') === '1');
defined('YII_ENV') || define('YII_ENV', getenv('YII_ENV') ?: 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new Application($config))->run();
```

Create `yii/config/console.php`:

```php
<?php

use yii\caching\FileCache;
use yii\log\FileTarget;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . '/container.php';

return [
    'id' => 'aimm-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\commands',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'components' => [
        'db' => $db,
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/app.log',
                ],
            ],
        ],
    ],
    'params' => $params,
    'container' => $container,
];
```

Create `yii/config/web.php`:

```php
<?php

use yii\caching\FileCache;
use yii\log\FileTarget;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . '/container.php';

return [
    'id' => 'aimm-web',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\controllers',
    'defaultRoute' => 'health/index',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'dev-only-change-me',
        ],
        'db' => $db,
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/web.log',
                ],
            ],
        ],
    ],
    'params' => $params,
    'container' => $container,
];
```

Create `yii/config/db.php`:

```php
<?php

use yii\db\Connection;

$host = getenv('DB_HOST') ?: 'aimm_mysql';
$database = getenv('DB_DATABASE') ?: 'aimm';

return [
    'class' => Connection::class,
    'dsn' => sprintf('mysql:host=%s;dbname=%s', $host, $database),
    'username' => getenv('DB_USER') ?: 'aimm',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'tablePrefix' => 'aimm_',
];
```

Create `yii/config/params.php`:

```php
<?php

return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
];
```

Create `yii/config/container.php`:

```php
<?php

return [
    'definitions' => [],
    'singletons' => [],
];
```

Create `yii/src/Controllers/HealthController.php`:

```php
<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

final class HealthController extends Controller
{
    public function actionIndex(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        return "OK\n";
    }
}
```

### 10) Create base enums

Follow `docs/RULES.md` conventions.

Create `yii/src/enums/CollectionMethod.php`:

```php
<?php

namespace app\enums;

enum CollectionMethod: string
{
    case WebFetch = 'web_fetch';
    case WebSearch = 'web_search';
    case Api = 'api';
    case Derived = 'derived';
    case NotFound = 'not_found';
}
```

Create `yii/src/enums/Severity.php`:

```php
<?php

namespace app\enums;

enum Severity: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';
}
```

Create `yii/src/enums/Rating.php`:

```php
<?php

namespace app\enums;

enum Rating: string
{
    case Buy = 'BUY';
    case Hold = 'HOLD';
    case Sell = 'SELL';
}
```

### 11) Create smoke-test console command

Create `yii/src/Commands/TestController.php`:

```php
<?php

namespace app\commands;

use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class TestController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout("AIMM is ready.\n");
        $this->stdout('PHP: ' . PHP_VERSION . "\n");
        return ExitCode::OK;
    }

    public function actionDb(): int
    {
        try {
            $value = (string) Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (Throwable $e) {
            $this->stderr('DB connection failed: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("DB OK: {$value}\n");
        return ExitCode::OK;
    }
}
```

### 12) Create/Update `.gitignore`

Create `.gitignore` (or merge if you already have one):

```
/yii/vendor/
/yii/runtime/
/data/
.env
/.idea/
/.vscode/
/yii/.phpstorm_helpers/
*.log
.DS_Store
```

### 13) Set executable bits (Linux/macOS only)

This avoids "permission denied" when running scripts directly. Windows users can skip this step.

```bash
chmod +x docker/init-scripts/init-databases.sh
chmod +x yii/yii
```

### 14) Build, start, and install dependencies

```bash
cp .env.example .env

# Linux/WSL: edit .env to match your host user (recommended)
# USER_ID=$(id -u)
# USER_NAME=$(id -un)

docker compose up -d --build

# Wait for MySQL to be healthy (first boot can take ~10-30s)
docker compose ps

docker compose exec -T aimm_yii composer install
```

## Verification

Run from the host (repo root):

```bash
# Services running; aimm_mysql should show "healthy"
docker compose ps

# Yii console boots
docker compose exec -T aimm_yii php yii test/index

# DB connection from Yii container
docker compose exec -T aimm_yii php yii test/db

# Gotenberg health check
docker compose exec -T aimm_gotenberg curl -f http://localhost:3000/health
```

Optional web check:

- Find the mapped port: `docker compose port aimm_nginx 80` (default is `http://localhost:8510/`)
- Open the URL in a browser (or use `curl` if available); expected body: `OK`

## Definition of Done

- [ ] `docker compose up -d --build` completes without errors
- [ ] `docker compose ps` shows all services running and `aimm_mysql` is healthy
- [ ] `docker compose exec -T aimm_yii php yii test/index` prints `AIMM is ready.`
- [ ] `docker compose exec -T aimm_yii php yii test/db` prints `DB OK: 1`
- [ ] `docker compose exec -T aimm_gotenberg curl -f http://localhost:3000/health` returns `{"status":"up",...}`
- [ ] `.env` exists locally and is ignored by `.gitignore`

## Common commands

```bash
# Start/stop
docker compose up -d
docker compose down

# Logs
docker compose logs -f aimm_yii
docker compose logs -f aimm_mysql

# Shell access
docker compose exec aimm_yii bash

# Destructive reset (wipes DB data; re-runs init scripts on next up)
docker compose down -v
rm -rf data/db/mysql
```

## Troubleshooting

| Issue                                            | Fix                                                                                                                                       |
|--------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| Permission denied writing `yii/vendor`           | On Linux/WSL set `USER_ID`/`USER_NAME` in `.env` to match `id -u` / `id -un`, then rebuild: `docker compose build --no-cache aimm_yii`         |
| MySQL init script changes not applied            | Init scripts run only on first boot; wipe DB data (destructive): `docker compose down -v` and delete `data/db/mysql`                      |
| `bash: $'\r': command not found` in init scripts | Convert `docker/init-scripts/init-databases.sh` to LF line endings (CRLF issue on Windows)                                                |
| Port already in use                              | Change `NGINX_PORT` or `DB_PORT` in `.env`                                                                                                |
| Xdebug not connecting                            | Set `XDEBUG_MODE=debug,develop`; for CLI also set `XDEBUG_CONFIG="client_host=host.docker.internal client_port=9878"`; restart `aimm_yii` |
