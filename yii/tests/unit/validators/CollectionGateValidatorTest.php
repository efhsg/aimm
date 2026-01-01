<?php

declare(strict_types=1);

namespace tests\unit\validators;

use app\dto\CollectionLog;
use app\dto\CompanyConfig;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\DataRequirements;
use app\dto\FinancialsData;
use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\dto\IndustryDataPack;
use app\dto\MacroData;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\dto\QuartersData;
use app\dto\ValidationResult;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\validators\CollectionGateValidator;
use app\validators\SchemaValidatorInterface;
use app\validators\SemanticValidatorInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionProperty;

/**
 * @covers \app\validators\CollectionGateValidator
 */
final class CollectionGateValidatorTest extends Unit
{
    public function testFailsGateWhenRequiredMetricValueNull(): void
    {
        $marketCap = new DataPointMoney(
            value: null,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-01'),
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="MARKET_CAP-value"]', 'N/A'),
        );

        $validator = $this->createValidator();
        $result = $validator->validate(
            $this->createDataPack($marketCap),
            $this->createConfig()
        );

        $this->assertFalse($result->passed);
        $this->assertContains('MISSING_REQUIRED', $this->getErrorCodes($result));
    }

    public function testFailsGateWhenWebFetchMetricMissingSourceLocator(): void
    {
        $marketCap = $this->buildInvalidMoney(
            method: CollectionMethod::WebFetch,
            value: 100,
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            sourceLocator: null
        );

        $validator = $this->createValidator();
        $result = $validator->validate(
            $this->createDataPack($marketCap),
            $this->createConfig()
        );

        $this->assertFalse($result->passed);
        $this->assertContains('MISSING_PROVENANCE', $this->getErrorCodes($result));
    }

    public function testFailsGateWhenNotFoundMetricMissingAttemptedSources(): void
    {
        $marketCap = $this->buildInvalidMoney(
            method: CollectionMethod::NotFound,
            value: null,
            attemptedSources: null
        );

        $validator = $this->createValidator();
        $result = $validator->validate(
            $this->createDataPack($marketCap),
            $this->createConfig()
        );

        $this->assertFalse($result->passed);
        $this->assertContains('UNDOCUMENTED_MISSING', $this->getErrorCodes($result));
    }

    public function testFailsGateWhenDerivedMetricMissingFormulaOrDerivedFrom(): void
    {
        $marketCap = $this->buildInvalidMoney(
            method: CollectionMethod::Derived,
            value: 123.0,
            derivedFrom: null,
            formula: null
        );

        $validator = $this->createValidator();
        $result = $validator->validate(
            $this->createDataPack($marketCap),
            $this->createConfig()
        );

        $this->assertFalse($result->passed);
        $this->assertContains('MISSING_PROVENANCE', $this->getErrorCodes($result));
    }

    public function testIgnoresRequiredMetricsForPeersWhenRequiredScopeIsFocal(): void
    {
        $focalMarketCap = new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-01'),
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="MARKET_CAP-value"]', '100'),
        );

        $peerMarketCap = new DataPointMoney(
            value: null,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-01'),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            method: CollectionMethod::NotFound,
            attemptedSources: ['https://example.com (not found)'],
        );

        $validator = $this->createValidator();
        // Use required_scope=focal so peers are not validated for this metric
        $result = $validator->validate(
            $this->createDataPackForCompanies([
                'AAPL' => $focalMarketCap,
                'MSFT' => $peerMarketCap,
            ]),
            $this->createConfigForCompanies(['AAPL', 'MSFT'], MetricDefinition::SCOPE_FOCAL),
            'AAPL'
        );

        $this->assertTrue(
            $result->passed,
            'Unexpected errors: ' . implode(', ', $this->getErrorCodes($result))
        );
    }

    public function testFailsGateWhenPeerMissesRequiredScopeAllMetric(): void
    {
        $focalMarketCap = new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-01'),
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="MARKET_CAP-value"]', '100'),
        );

        $peerMarketCap = new DataPointMoney(
            value: null,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-01'),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            method: CollectionMethod::NotFound,
            attemptedSources: ['https://example.com (not found)'],
        );

        $validator = $this->createValidator();
        // Use required_scope=all (default) so peers ARE validated
        $result = $validator->validate(
            $this->createDataPackForCompanies([
                'AAPL' => $focalMarketCap,
                'MSFT' => $peerMarketCap,
            ]),
            $this->createConfigForCompanies(['AAPL', 'MSFT'], MetricDefinition::SCOPE_ALL),
            'AAPL'
        );

        $this->assertFalse($result->passed);
        $this->assertContains('MISSING_REQUIRED', $this->getErrorCodes($result));
    }

    private function createValidator(): CollectionGateValidator
    {
        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(new ValidationResult(true, []));

        $semanticValidator = $this->createMock(SemanticValidatorInterface::class);
        $semanticValidator->method('validate')->willReturn(new GateResult(true, [], []));

        return new CollectionGateValidator(
            schemaValidator: $schemaValidator,
            semanticValidator: $semanticValidator,
            macroStalenessThresholdDays: 10
        );
    }

    private function createDataPack(DataPointMoney $marketCap): IndustryDataPack
    {
        $company = new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(marketCap: $marketCap),
            financials: new FinancialsData(historyYears: 0, annualData: []),
            quarters: new QuartersData(quarters: []),
        );

        $log = new CollectionLog(
            startedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            completedAt: new DateTimeImmutable('2024-01-01T00:01:00Z'),
            durationSeconds: 60,
            companyStatuses: ['AAPL' => CollectionStatus::Complete],
            macroStatus: CollectionStatus::Complete,
            totalAttempts: 1,
        );

        return new IndustryDataPack(
            industryId: 'energy',
            datapackId: 'dp-123',
            collectedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            macro: new MacroData(),
            companies: ['AAPL' => $company],
            collectionLog: $log,
        );
    }

    /**
     * @param array<string, DataPointMoney> $marketCapsByTicker
     */
    private function createDataPackForCompanies(array $marketCapsByTicker): IndustryDataPack
    {
        $companies = [];
        foreach ($marketCapsByTicker as $ticker => $marketCap) {
            $companies[$ticker] = new CompanyData(
                ticker: $ticker,
                name: $ticker . ' Inc',
                listingExchange: 'NASDAQ',
                listingCurrency: 'USD',
                reportingCurrency: 'USD',
                valuation: new ValuationData(marketCap: $marketCap),
                financials: new FinancialsData(historyYears: 0, annualData: []),
                quarters: new QuartersData(quarters: []),
            );
        }

        $log = new CollectionLog(
            startedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            completedAt: new DateTimeImmutable('2024-01-01T00:01:00Z'),
            durationSeconds: 60,
            companyStatuses: array_fill_keys(array_keys($companies), CollectionStatus::Complete),
            macroStatus: CollectionStatus::Complete,
            totalAttempts: 1,
        );

        return new IndustryDataPack(
            industryId: 'energy',
            datapackId: 'dp-123',
            collectedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            macro: new MacroData(),
            companies: $companies,
            collectionLog: $log,
        );
    }

    private function createConfig(): IndustryConfig
    {
        $requirements = new DataRequirements(
            historyYears: 0,
            quartersToFetch: 0,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $company = new CompanyConfig(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            fyEndMonth: 9,
            alternativeTickers: null,
        );

        return new IndustryConfig(
            id: 'energy',
            name: 'Energy',
            sector: 'Energy',
            companies: [$company],
            macroRequirements: new MacroRequirements(
                commodityBenchmark: null,
                marginProxy: null,
                sectorIndex: null,
                requiredIndicators: [],
                optionalIndicators: [],
            ),
            dataRequirements: $requirements,
        );
    }

    /**
     * @param list<string> $tickers
     * @param string $requiredScope Scope for required metrics ('all' or 'focal')
     */
    private function createConfigForCompanies(array $tickers, string $requiredScope = MetricDefinition::SCOPE_ALL): IndustryConfig
    {
        $requirements = new DataRequirements(
            historyYears: 0,
            quartersToFetch: 0,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true, $requiredScope),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $companies = array_map(
            static fn (string $ticker): CompanyConfig => new CompanyConfig(
                ticker: $ticker,
                name: $ticker . ' Inc',
                listingExchange: 'NASDAQ',
                listingCurrency: 'USD',
                reportingCurrency: 'USD',
                fyEndMonth: 9,
                alternativeTickers: null,
            ),
            $tickers
        );

        return new IndustryConfig(
            id: 'energy',
            name: 'Energy',
            sector: 'Energy',
            companies: $companies,
            macroRequirements: new MacroRequirements(
                commodityBenchmark: null,
                marginProxy: null,
                sectorIndex: null,
                requiredIndicators: [],
                optionalIndicators: [],
            ),
            dataRequirements: $requirements,
        );
    }

    /**
     * @return list<string>
     */
    private function getErrorCodes(GateResult $result): array
    {
        return array_map(static fn ($error) => $error->code, $result->errors);
    }

    private function buildInvalidMoney(
        CollectionMethod $method,
        ?float $value,
        ?string $sourceUrl = null,
        ?SourceLocator $sourceLocator = null,
        ?array $attemptedSources = null,
        ?array $derivedFrom = null,
        ?string $formula = null,
    ): DataPointMoney {
        $reflection = new ReflectionClass(DataPointMoney::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($instance, 'value', $value);
        $this->setProperty($instance, 'currency', 'USD');
        $this->setProperty($instance, 'scale', DataScale::Units);
        $this->setProperty($instance, 'asOf', new DateTimeImmutable('2024-01-01'));
        $this->setProperty($instance, 'sourceUrl', $sourceUrl);
        $this->setProperty($instance, 'retrievedAt', new DateTimeImmutable('2024-01-01T00:00:00Z'));
        $this->setProperty($instance, 'method', $method);
        $this->setProperty($instance, 'sourceLocator', $sourceLocator);
        $this->setProperty($instance, 'attemptedSources', $attemptedSources);
        $this->setProperty($instance, 'derivedFrom', $derivedFrom);
        $this->setProperty($instance, 'formula', $formula);
        $this->setProperty($instance, 'fxConversion', null);
        $this->setProperty($instance, 'cacheSource', null);
        $this->setProperty($instance, 'cacheAgeDays', null);

        return $instance;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $property = new ReflectionProperty($object, $name);
        $property->setValue($object, $value);
    }
}
