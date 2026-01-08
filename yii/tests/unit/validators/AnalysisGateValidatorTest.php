<?php

declare(strict_types=1);

namespace tests\unit\validators;

use app\dto\AnnualFinancials;
use app\dto\CollectionLog;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\IndustryDataPack;
use app\dto\MacroData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\validators\AnalysisGateValidator;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\validators\AnalysisGateValidator
 */
final class AnalysisGateValidatorTest extends Unit
{
    private AnalysisGateValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AnalysisGateValidator();
    }

    public function testPassesWithValidDatapack(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: 3_000_000_000_000,
            peerCount: 3,
            collectedDaysAgo: 5
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->warnings);
    }

    public function testFailsWhenFocalNotFound(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: 3_000_000_000_000,
            peerCount: 3
        );

        $result = $this->validator->validate($dataPack, 'NONEXISTENT'); // Ticker not in datapack

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('FOCAL_NOT_FOUND'));
    }

    public function testFailsWithInsufficientAnnualData(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 1, // Only 1 year, need 2
            focalMarketCap: 3_000_000_000_000,
            peerCount: 3
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('INSUFFICIENT_ANNUAL_DATA'));
    }

    public function testFailsWhenMissingMarketCap(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: null, // Missing market cap
            peerCount: 3
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('MISSING_MARKET_CAP'));
    }

    public function testFailsWithNoPeers(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: 3_000_000_000_000,
            peerCount: 0 // No peers
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('NO_PEERS'));
    }

    public function testWarnsOnLowPeerCount(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: 3_000_000_000_000,
            peerCount: 1 // Only 1 peer, recommend 2+
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertEquals('LOW_PEER_COUNT', $result->warnings[0]->code);
    }

    public function testWarnsOnStaleData(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 2,
            focalMarketCap: 3_000_000_000_000,
            peerCount: 3,
            collectedDaysAgo: 45 // Older than 30 days
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertEquals('STALE_DATA', $result->warnings[0]->code);
    }

    public function testMultipleErrors(): void
    {
        $dataPack = $this->createDataPack(
            focalTicker: 'AAPL',
            focalAnnualYears: 1,
            focalMarketCap: null,
            peerCount: 0
        );

        $result = $this->validator->validate($dataPack, 'AAPL');

        $this->assertFalse($result->passed);
        $this->assertCount(3, $result->errors);
        $this->assertTrue($result->hasErrorCode('INSUFFICIENT_ANNUAL_DATA'));
        $this->assertTrue($result->hasErrorCode('MISSING_MARKET_CAP'));
        $this->assertTrue($result->hasErrorCode('NO_PEERS'));
    }

    private function createDataPack(
        string $focalTicker,
        int $focalAnnualYears,
        ?float $focalMarketCap,
        int $peerCount,
        int $collectedDaysAgo = 0
    ): IndustryDataPack {
        $companies = [];

        // Create focal company
        $companies[$focalTicker] = $this->createCompany(
            $focalTicker,
            $focalAnnualYears,
            $focalMarketCap
        );

        // Create peer companies
        $peerTickers = ['MSFT', 'GOOGL', 'AMZN', 'META'];
        for ($i = 0; $i < $peerCount; $i++) {
            $ticker = $peerTickers[$i] ?? "PEER{$i}";
            if ($ticker !== $focalTicker) {
                $companies[$ticker] = $this->createCompany($ticker, 2, 2_000_000_000_000);
            }
        }

        $collectedAt = (new DateTimeImmutable())->modify("-{$collectedDaysAgo} days");

        return new IndustryDataPack(
            industryId: 'us-tech-giants',
            datapackId: 'test-datapack-123',
            collectedAt: $collectedAt,
            macro: new MacroData(),
            companies: $companies,
            collectionLog: new CollectionLog(
                startedAt: $collectedAt,
                completedAt: $collectedAt,
                durationSeconds: 60,
                companyStatuses: array_fill_keys(array_keys($companies), CollectionStatus::Complete),
                macroStatus: CollectionStatus::Complete,
                totalAttempts: count($companies),
            ),
        );
    }

    private function createCompany(
        string $ticker,
        int $annualYears,
        ?float $marketCap
    ): CompanyData {
        $annualData = [];
        for ($i = 0; $i < $annualYears; $i++) {
            $annualData[] = new AnnualFinancials(
                fiscalYear: 2024 - $i,
                revenue: $this->createMoney(100_000_000_000),
                ebitda: $this->createMoney(20_000_000_000),
            );
        }

        return new CompanyData(
            ticker: $ticker,
            name: "{$ticker} Inc",
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $marketCap !== null
                    ? $this->createMoney($marketCap)
                    : $this->createMoneyWithNullValue(),
            ),
            financials: new FinancialsData(historyYears: $annualYears, annualData: $annualData),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createMoney(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.value', (string) $value),
        );
    }

    private function createMoneyWithNullValue(): DataPointMoney
    {
        return new DataPointMoney(
            value: null,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.value', 'null'),
        );
    }
}
