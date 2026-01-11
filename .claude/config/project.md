# Project Configuration

Single source of truth for project-specific operations.

## Environment

| Setting | Value |
|---------|-------|
| Container | `aimm_yii` |
| PHP | 8.x |
| Framework | Yii 2 |
| Test Framework | Codeception |

## Commands

**CRITICAL: Never use local PHP. All PHP commands MUST run inside Docker via `docker exec aimm_yii`.**

### Linter

```bash
# Fix all files
docker exec aimm_yii vendor/bin/php-cs-fixer fix

# Fix staged files only
docker exec aimm_yii vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php $(git diff --cached --name-only --diff-filter=ACMR | grep '\.php$' | xargs)
```

### Tests

Codeception requires `register_argc_argv=1` (not set in container's php.ini).

```bash
# Run all unit tests
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit

# Run single test file
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit tests/unit/path/FooTest.php

# Run single test method
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit tests/unit/path/FooTest.php:testMethodName

# Run test directory
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit tests/unit/handlers/foo/
```

**Note:** Codeception accepts only one test path per command. To run multiple paths, execute separate commands.

### Database

```bash
# Run migrations
docker exec aimm_yii vendor/bin/yii migrate/up

# Create new migration
docker exec aimm_yii vendor/bin/yii migrate/create migration_name

# Migration status
docker exec aimm_yii vendor/bin/yii migrate/history
```

### Other

```bash
# Shell into container
docker exec -it aimm_yii bash

# View logs
docker logs aimm_yii

# Restart container
docker restart aimm_yii
```

## File Structure

| Type | Location |
|------|----------|
| Handlers | `yii/src/handlers/` |
| Queries | `yii/src/queries/` |
| Validators | `yii/src/validators/` |
| DTOs | `yii/src/dto/` |
| Adapters | `yii/src/adapters/` |
| Clients | `yii/src/clients/` |
| Factories | `yii/src/factories/` |
| Transformers | `yii/src/transformers/` |
| Enums | `yii/src/enums/` |
| Exceptions | `yii/src/exceptions/` |
| Alerts | `yii/src/alerts/` |
| Events | `yii/src/events/` |
| Models | `yii/src/models/` |
| Controllers | `yii/src/controllers/` |
| Commands | `yii/src/commands/` |
| Views | `yii/src/views/` |
| Tests | `yii/tests/unit/` |
| Fixtures | `yii/tests/fixtures/` |
| Migrations | `yii/migrations/` |
| Config | `yii/config/` |

## Test Path Mapping

Source files map to test files by replacing `src` with `tests/unit`:

| Source | Test |
|--------|------|
| `yii/src/handlers/FooHandler.php` | `yii/tests/unit/handlers/FooHandlerTest.php` |
| `yii/src/queries/FooQuery.php` | `yii/tests/unit/queries/FooQueryTest.php` |
| `yii/src/validators/FooValidator.php` | `yii/tests/unit/validators/FooValidatorTest.php` |
| `yii/src/transformers/FooTransformer.php` | `yii/tests/unit/transformers/FooTransformerTest.php` |
| `yii/src/factories/FooFactory.php` | `yii/tests/unit/factories/FooFactoryTest.php` |
| `yii/src/adapters/FooAdapter.php` | `yii/tests/unit/adapters/FooAdapterTest.php` |
| `yii/src/dto/FooData.php` | `yii/tests/unit/dto/FooDataTest.php` |
| `yii/src/enums/FooEnum.php` | `yii/tests/unit/enums/FooEnumTest.php` |
| `yii/src/clients/FooClient.php` | `yii/tests/unit/clients/FooClientTest.php` |
| `yii/src/alerts/FooAlert.php` | `yii/tests/unit/alerts/FooAlertTest.php` |
| `yii/src/models/Foo.php` | `yii/tests/unit/models/FooTest.php` |
| `yii/src/exceptions/FooException.php` | *(no tests required)* |

## External Integrations

| Service | Purpose | Adapter |
|---------|---------|---------|
| FMP | Financial data API | `FmpAdapter` |
| Yahoo Finance | Stock data | `YahooFinanceAdapter` |
| Bloomberg | Financial news | `BloombergAdapter` |
| Reuters | Market data | `ReutersAdapter` |
| ECB | Exchange rates | `EcbAdapter` |
| EIA | Energy data | `EiaAdapter` |
| Baker Hughes | Rig counts | `BakerHughesAdapter` |

## Key Domain Concepts

| Concept | Description |
|---------|-------------|
| Industry | A group of peer companies for analysis |
| Company | A publicly traded company with ticker |
| Dossier | Collection of financial data for a company |
| DataPoint | Single financial metric with source attribution |
| Collection Run | Execution of data collection for an industry |
| Gate | Validation checkpoint for data completeness |
