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
            companyCount: 3,
            annualYears: 2,
            marketCap: 3_000_000_000_000,
            collectedDaysAgo: 5
        );

        $result = $this->validator->validate($dataPack);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
    }

    public function testFailsWithInsufficientCompanies(): void
    {
        $dataPack = $this->createDataPack(
            companyCount: 1, // Only 1 company, need minimum 2
            annualYears: 2,
            marketCap: 3_000_000_000_000
        );

        $result = $this->validator->validate($dataPack);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('INSUFFICIENT_COMPANIES'));
    }

    public function testFailsWithInsufficientAnalyzableCompanies(): void
    {
        // 2 companies but both have insufficient data
        $dataPack = $this->createDataPack(
            companyCount: 2,
            annualYears: 1, // Only 1 year, need 2
            marketCap: 3_000_000_000_000
        );

        $result = $this->validator->validate($dataPack);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('NO_ANALYZABLE_COMPANIES'));
    }

    public function testFailsWhenNoCompaniesHaveMarketCap(): void
    {
        $dataPack = $this->createDataPack(
            companyCount: 3,
            annualYears: 2,
            marketCap: null // Missing market cap
        );

        $result = $this->validator->validate($dataPack);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasErrorCode('NO_ANALYZABLE_COMPANIES'));
    }

    public function testWarnsOnLowCompanyCount(): void
    {
        $dataPack = $this->createDataPack(
            companyCount: 2, // Minimum but below recommended
            annualYears: 2,
            marketCap: 3_000_000_000_000
        );

        $result = $this->validator->validate($dataPack);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertEquals('LOW_COMPANY_COUNT', $result->warnings[0]->code);
    }

    public function testWarnsOnStaleData(): void
    {
        $dataPack = $this->createDataPack(
            companyCount: 5, // Above recommended
            annualYears: 2,
            marketCap: 3_000_000_000_000,
            collectedDaysAgo: 45 // Older than 30 days
        );

        $result = $this->validator->validate($dataPack);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertEquals('STALE_DATA', $result->warnings[0]->code);
    }

    public function testWarnsWhenSomeCompaniesHaveInsufficientData(): void
    {
        // Create a datapack where some companies have good data and some don't
        $dataPack = $this->createMixedDataPack();

        $result = $this->validator->validate($dataPack);

        $this->assertTrue($result->passed); // Still passes because 2+ are analyzable
        $hasInsufficientDataWarning = false;
        foreach ($result->warnings as $warning) {
            if ($warning->code === 'COMPANY_INSUFFICIENT_DATA') {
                $hasInsufficientDataWarning = true;
                break;
            }
        }
        $this->assertTrue($hasInsufficientDataWarning, 'Expected COMPANY_INSUFFICIENT_DATA warning');
    }

    private function createDataPack(
        int $companyCount,
        int $annualYears,
        ?float $marketCap,
        int $collectedDaysAgo = 0
    ): IndustryDataPack {
        $companies = [];
        $tickers = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META'];

        for ($i = 0; $i < $companyCount; $i++) {
            $ticker = $tickers[$i] ?? "CO{$i}";
            $companies[$ticker] = $this->createCompany($ticker, $annualYears, $marketCap);
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

    private function createMixedDataPack(): IndustryDataPack
    {
        $collectedAt = new DateTimeImmutable();

        $companies = [
            'AAPL' => $this->createCompany('AAPL', 2, 3_000_000_000_000), // Good
            'MSFT' => $this->createCompany('MSFT', 2, 2_800_000_000_000), // Good
            'GOOGL' => $this->createCompany('GOOGL', 1, 1_800_000_000_000), // Insufficient annual data
        ];

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
