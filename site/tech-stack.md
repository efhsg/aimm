# Tech Stack

AIMM uses PHP for orchestration and Python for PDF rendering.

## Core Technologies

| Component | Technology | Version |
|-----------|------------|---------|
| Orchestration | Yii 2 Framework | PHP 8.2+ |
| PDF Rendering | ReportLab + matplotlib | Python 3.11+ |
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
    "symfony/process": "^6.4",
    "yiisoft/yii2-queue": "^2.3",
    "ramsey/uuid": "^4.7"
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
| `symfony/process` | Subprocess execution for Python renderer |
| `yiisoft/yii2-queue` | Background job processing |
| `ramsey/uuid` | UUID generation for artifact folders |
| `codeception/codeception` | Unit and integration testing |

## Python Dependencies

```
reportlab>=4.0
matplotlib>=3.8
pillow>=10.0
```

### Key Libraries

| Package | Purpose |
|---------|---------|
| `reportlab` | PDF generation |
| `matplotlib` | Chart rendering |
| `pillow` | Image handling |

## Architecture Decisions

### Why Yii 2?

- Mature console application support
- Built-in DI container
- Established conventions for commands and components
- Queue support via yii2-queue

### Why Python for Rendering?

- ReportLab is the industry standard for PDF generation
- matplotlib excels at financial charts
- Keeps PHP focused on business logic
- Clear boundary: Python receives JSON, outputs PDF

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
- Python 3.11+ (for renderer)
- MySQL 8.0+ (for dossier storage)
