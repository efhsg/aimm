# Project Configuration

Single source of truth for project-specific operations.

## Environment

| Setting | Value |
|---------|-------|
| Container | `aimm_yii` |
| PHP | `>=8.5` |
| Framework | Yii 2 |
| Test Framework | Codeception 5 |
| Host root | `/opt/dev/aim/aimm` |
| PromptManager runner root | `/projects/aim/aimm` |
| Host/runner mapping | `/opt/dev` → `/projects` |

## Execution Environments

### AIMM host agent

The host agent may use the AIMM Docker services after confirming that the active
worktree and container names match this project. Run application PHP inside
`aimm_yii`; use the root PHP CS Fixer wrapper so it can select a compatible local
PHP >=8.5 runtime or the container and fail closed otherwise.

### PromptManager runner

PromptManager project 33 resolves AIMM at `/projects/aim/aimm`. The runner does
not provide Docker and its local PHP runtime is below AIMM's PHP >=8.5 boundary.
It must not run local AIMM PHP commands or report skipped checks as successful.
Use runtime-independent read-only checks where possible and provide the exact
host maintainer commands for all remaining validation.

## Commands

Commands below are host commands unless explicitly labelled runner-safe.

### Linter

```bash
# From the AIMM yii/ directory: check source without changing it
../php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php src

# Apply formatting to source
../php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php src
```

The wrapper refuses incompatible local PHP and falls back to the `aimm_yii`
container only when Docker and that container are available.

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

Run tests sequentially. Read the full output and exit code before starting the
next test command.

### Database

```bash
# Capture both histories before mutation
docker exec aimm_yii vendor/bin/yii migrate/history
docker exec -e YII_ENV=test aimm_yii vendor/bin/yii migrate/history

# Validate migrations on the isolated test schema first
docker exec -e YII_ENV=test aimm_yii vendor/bin/yii migrate/up --interactive=0
docker exec -e YII_ENV=test aimm_yii vendor/bin/yii migrate/history

# Apply only after test validation succeeds and the application target is approved
docker exec aimm_yii vendor/bin/yii migrate/up --interactive=0
docker exec aimm_yii vendor/bin/yii migrate/history

# Create new migration
docker exec aimm_yii vendor/bin/yii migrate/create migration_name
```

Before applying a migration, record the resolved application and test database
names through the approved environment-management channel, verify that they are
distinct, and capture a recovery anchor for each. Never apply to the application
schema when test migration or readback fails.

### Other

```bash
# Shell into container
docker exec -it aimm_yii bash

# View logs
docker logs aimm_yii

# Restart container
docker restart aimm_yii

# Build the documentation site from the host
npm run docs:build
```

### PromptManager runner-safe checks

```bash
git status --short
git diff --check
jq empty .claude/settings.json
```

Do not use local PHP for AIMM in the PromptManager runner. Hand off the linter,
Codeception, migrations, and documentation build to an AIMM maintainer with the
host commands above.

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
