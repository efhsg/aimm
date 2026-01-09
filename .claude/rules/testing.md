# Testing Requirements

## Minimum Coverage

- **Unit tests** for: calculators, validators, transformers, factories, queries, enums
- **Integration tests** for: gates, handlers (happy path + key failures)
- **Fixture tests** for: adapters (real HTML snapshots from external sources)

## Test Location

Tests mirror source structure:
- `yii/src/handlers/` → `yii/tests/unit/handlers/`
- `yii/src/queries/` → `yii/tests/unit/queries/`
- etc.

## Test Naming

```php
public function testCalculatesGapWhenBothValuesPresent(): void
public function testReturnsNullWhenPeerAverageIsZero(): void
public function testFailsGateOnMissingRequiredMetric(): void
```

Pattern: `test{Action}{Condition}` or `test{Action}When{Scenario}`

## Test Structure

- All tests extend `\Codeception\Test\Unit`
- Full type hints on parameters, returns (`: void` for tests), properties
- Prefer `MockBuilder` (PHPUnit) over handmade fakes

## No Tests For

- Simple getters/setters
- Framework code
- Third-party libraries
- Simple exception classes
