# Testing Requirements

## Minimum Coverage

- **Unit tests** for: calculators, validators, transformers, factories
- **Integration tests** for: gates, handlers (happy path + key failures)
- **Fixture tests** for: adapters (real HTML snapshots)

## Test Naming

```php
public function testCalculatesGapWhenBothValuesPresent(): void
public function testReturnsNullWhenPeerAverageIsZero(): void
public function testFailsGateOnMissingRequiredMetric(): void
```

Pattern: `test{Action}{Condition}` or `test{Action}When{Scenario}`

## No Tests For

- Simple getters/setters
- Framework code
- Third-party libraries
