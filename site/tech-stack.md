# Tech Stack

AIMM uses PHP for orchestration and Gotenberg for PDF rendering.

## Core Technologies

| Component | Technology | Version |
|-----------|------------|---------|
| Orchestration | Yii 2 Framework | PHP 8.2+ |
| PDF Rendering | Gotenberg (Chromium) | 8.x |
| Schema Validation | opis/json-schema | JSON Schema draft-07 |
| Process Management | Symfony Process | ^6.4 |
| Queue (optional) | yii2-queue | ^2.3 |

## PHP Dependencies

```json
{
  "require": {
    "php": ">=8.2",
    "yiisoft/yii2": "~2.0.49",
    "opis/json-schema": "^2.3",
    "symfony/process": "^5.4 || ^6.4 || ^7.0",
    "yiisoft/yii2-queue": "^2.3",
    "ramsey/uuid": "^4.7",
    "guzzlehttp/guzzle": "^7.10"
  },
  "require-dev": {
    "codeception/codeception": "^5.0",
    "codeception/module-asserts": "^3.0"
  }
}
```

### Key Libraries

| Package | Purpose |
|---------|---------|
| `yiisoft/yii2` | Framework for CLI and DI |
| `opis/json-schema` | JSON Schema validation |
| `symfony/process` | Subprocess execution for external tools |
| `yiisoft/yii2-queue` | Background job processing |
| `ramsey/uuid` | UUID generation for artifact folders |
| `guzzlehttp/guzzle` | HTTP client for external services |
| `codeception/codeception` | Unit and integration testing |

## Gotenberg

- Image: `gotenberg/gotenberg:8`

## Architecture Decisions

### Why Yii 2?

- Mature console application support
- Built-in DI container
- Established conventions for commands and components
- Queue support via yii2-queue

### Why Gotenberg for Rendering?

- Chromium-grade HTML/CSS layout fidelity
- Template-driven layouts are easier to maintain
- Keeps PHP focused on orchestration and data integrity

### Why JSON Schema?

- Language-agnostic validation
- Self-documenting contracts
- Supports complex nested structures
- opis/json-schema has excellent PHP support

## Development Environment

### Docker

The project runs in Docker containers:

```bash
# Run unit tests
docker exec aimm_yii vendor/bin/codecept run unit

# Run linter
docker exec aimm_yii vendor/bin/php-cs-fixer fix
```

### Requirements

- Docker with PHP 8.2+ image
- Gotenberg 8.x container
- MySQL 8.0+ (for dossier storage)

## Documentation

This documentation site is built with [VitePress](https://vitepress.dev/) and served via nginx.

### Viewing Documentation

Documentation is served by the nginx container at `/docs`:

```
http://localhost:8510/docs/
```

### Editing Documentation

For hot reload during editing:

```bash
npm run docs:dev
```

This starts a dev server at `http://localhost:5173/docs/` with instant updates.

### Building Documentation

To update the production build served by nginx:

```bash
npm run docs:build
```

The build output goes to `site/.vitepress/dist/` and is immediately available at `/docs`.
