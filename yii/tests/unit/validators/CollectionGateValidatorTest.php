<?php

declare(strict_types=1);

namespace tests\unit\validators;

use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\enums\CollectionStatus;
use app\validators\CollectionGateValidator;
use Codeception\Test\Unit;

/**
 * @covers \app\validators\CollectionGateValidator
 */
final class CollectionGateValidatorTest extends Unit
{
    public function testCreatePassingResultReturnsPassedGate(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->createPassingResult();

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->warnings);
    }

    public function testValidateResultsPassesWhenAllComplete(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: [
                'AAPL' => CollectionStatus::Complete,
                'MSFT' => CollectionStatus::Complete,
            ],
            macroStatus: CollectionStatus::Complete,
            config: $this->createConfigForCompanies(['AAPL', 'MSFT'])
        );

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->warnings);
    }

    public function testValidateResultsFailsWhenAnyCompanyFailed(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: [
                'AAPL' => CollectionStatus::Failed,
                'MSFT' => CollectionStatus::Complete,
            ],
            macroStatus: CollectionStatus::Complete,
            config: $this->createConfigForCompanies(['AAPL', 'MSFT'])
        );

        $this->assertFalse($result->passed);
        $this->assertContains('COMPANY_FAILED', $this->getErrorCodes($result));
    }

    public function testValidateResultsWarnsWhenAnyCompanyPartial(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: [
                'AAPL' => CollectionStatus::Partial,
                'MSFT' => CollectionStatus::Complete,
            ],
            macroStatus: CollectionStatus::Complete,
            config: $this->createConfigForCompanies(['AAPL', 'MSFT'])
        );

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertContains('COMPANY_PARTIAL', $this->getWarningCodes($result));
    }

    public function testValidateResultsFailsWhenMacroFailed(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: ['AAPL' => CollectionStatus::Complete],
            macroStatus: CollectionStatus::Failed,
            config: $this->createConfigForCompanies(['AAPL'])
        );

        $this->assertFalse($result->passed);
        $this->assertContains('MACRO_FAILED', $this->getErrorCodes($result));
    }

    public function testValidateResultsWarnsWhenMacroPartial(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: ['AAPL' => CollectionStatus::Complete],
            macroStatus: CollectionStatus::Partial,
            config: $this->createConfigForCompanies(['AAPL'])
        );

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertContains('MACRO_PARTIAL', $this->getWarningCodes($result));
    }

    public function testValidateResultsWarnsWhenConfiguredCompanyMissing(): void
    {
        $validator = new CollectionGateValidator();
        $result = $validator->validateResults(
            companyStatuses: [
                'AAPL' => CollectionStatus::Complete,
                // MSFT is configured but not collected
            ],
            macroStatus: CollectionStatus::Complete,
            config: $this->createConfigForCompanies(['AAPL', 'MSFT'])
        );

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->errors);
        $this->assertContains('MISSING_COMPANY', $this->getWarningCodes($result));
    }

    /**
     * @param list<string> $tickers
     */
    private function createConfigForCompanies(array $tickers): IndustryConfig
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
            industryId: 1,
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

    /**
     * @return list<string>
     */
    private function getWarningCodes(GateResult $result): array
    {
        return array_map(static fn ($warning) => $warning->code, $result->warnings);
    }
}
