<?php

declare(strict_types=1);

namespace tests\unit\dto;

use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\IndustryConfig;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use Codeception\Test\Unit;

/**
 * @covers \app\dto\IndustryConfig
 * @covers \app\dto\CompanyConfig
 * @covers \app\dto\DataRequirements
 * @covers \app\dto\MacroRequirements
 */
final class IndustryConfigTest extends Unit
{
    public function testIndustryConfigIsReadonly(): void
    {
        $config = $this->createIndustryConfig();

        $this->assertSame('oil-majors', $config->id);
        $this->assertSame('Oil Majors', $config->name);
        $this->assertSame('Energy', $config->sector);
    }

    public function testIndustryConfigContainsCompanies(): void
    {
        $config = $this->createIndustryConfig();

        $this->assertCount(2, $config->companies);
        $this->assertInstanceOf(CompanyConfig::class, $config->companies[0]);
        $this->assertSame('SHEL', $config->companies[0]->ticker);
    }

    public function testCompanyConfigProperties(): void
    {
        $company = new CompanyConfig(
            ticker: 'AAPL',
            name: 'Apple Inc.',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            fyEndMonth: 9,
            alternativeTickers: ['AAPL.O'],
        );

        $this->assertSame('AAPL', $company->ticker);
        $this->assertSame('Apple Inc.', $company->name);
        $this->assertSame('NASDAQ', $company->listingExchange);
        $this->assertSame('USD', $company->listingCurrency);
        $this->assertSame('USD', $company->reportingCurrency);
        $this->assertSame(9, $company->fyEndMonth);
        $this->assertSame(['AAPL.O'], $company->alternativeTickers);
    }

    public function testDataRequirementsProperties(): void
    {
        $requirements = new DataRequirements(
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true),
                new MetricDefinition('fwd_pe', MetricDefinition::UNIT_RATIO, true),
                new MetricDefinition('div_yield', MetricDefinition::UNIT_PERCENT, false),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $this->assertSame(5, $requirements->historyYears);
        $this->assertSame(8, $requirements->quartersToFetch);
        $this->assertCount(3, $requirements->valuationMetrics);
        $this->assertSame('market_cap', $requirements->valuationMetrics[0]->key);
        $this->assertSame('div_yield', $requirements->valuationMetrics[2]->key);
    }

    public function testMacroRequirementsProperties(): void
    {
        $requirements = new MacroRequirements(
            commodityBenchmark: 'brent_crude',
            marginProxy: 'crack_spread_321',
            sectorIndex: 'XLE',
            requiredIndicators: ['brent_crude'],
            optionalIndicators: ['wti_crude'],
        );

        $this->assertSame('brent_crude', $requirements->commodityBenchmark);
        $this->assertSame('crack_spread_321', $requirements->marginProxy);
        $this->assertSame('XLE', $requirements->sectorIndex);
        $this->assertSame(['brent_crude'], $requirements->requiredIndicators);
        $this->assertSame(['wti_crude'], $requirements->optionalIndicators);
    }

    public function testIndustryConfigToArraySerializesCorrectly(): void
    {
        $config = $this->createIndustryConfig();
        $array = $config->toArray();

        $this->assertSame('oil-majors', $array['id']);
        $this->assertSame('Oil Majors', $array['name']);
        $this->assertSame('Energy', $array['sector']);
        $this->assertIsArray($array['companies']);
        $this->assertCount(2, $array['companies']);
        $this->assertIsArray($array['macro_requirements']);
        $this->assertIsArray($array['data_requirements']);
    }

    public function testCompanyConfigToArraySerializesCorrectly(): void
    {
        $company = new CompanyConfig(
            ticker: 'SHEL',
            name: 'Shell plc',
            listingExchange: 'LSE',
            listingCurrency: 'GBP',
            reportingCurrency: 'USD',
            fyEndMonth: 12,
        );

        $array = $company->toArray();

        $this->assertSame('SHEL', $array['ticker']);
        $this->assertSame('Shell plc', $array['name']);
        $this->assertSame('LSE', $array['listing_exchange']);
        $this->assertSame('GBP', $array['listing_currency']);
        $this->assertSame('USD', $array['reporting_currency']);
        $this->assertSame(12, $array['fy_end_month']);
        $this->assertNull($array['alternative_tickers']);
    }

    public function testFocalTickersIncludedInToArrayWhenNotEmpty(): void
    {
        $config = $this->createIndustryConfigWithFocalTickers(['SHEL', 'XOM']);
        $array = $config->toArray();

        $this->assertArrayHasKey('focal_tickers', $array);
        $this->assertSame(['SHEL', 'XOM'], $array['focal_tickers']);
    }

    public function testFocalTickersExcludedFromToArrayWhenEmpty(): void
    {
        $config = $this->createIndustryConfig();
        $array = $config->toArray();

        $this->assertArrayNotHasKey('focal_tickers', $array);
    }

    public function testFocalTickersDefaultsToEmptyArray(): void
    {
        $config = $this->createIndustryConfig();

        $this->assertSame([], $config->focalTickers);
    }

    public function testFocalTickersCanContainMultipleTickers(): void
    {
        $config = $this->createIndustryConfigWithFocalTickers(['SHEL', 'XOM']);

        $this->assertCount(2, $config->focalTickers);
        $this->assertContains('SHEL', $config->focalTickers);
        $this->assertContains('XOM', $config->focalTickers);
    }

    /**
     * @param list<string> $focalTickers
     */
    private function createIndustryConfigWithFocalTickers(array $focalTickers): IndustryConfig
    {
        return new IndustryConfig(
            id: 'oil-majors',
            name: 'Oil Majors',
            sector: 'Energy',
            companies: [
                new CompanyConfig(
                    ticker: 'SHEL',
                    name: 'Shell plc',
                    listingExchange: 'LSE',
                    listingCurrency: 'GBP',
                    reportingCurrency: 'USD',
                    fyEndMonth: 12,
                ),
                new CompanyConfig(
                    ticker: 'XOM',
                    name: 'Exxon Mobil',
                    listingExchange: 'NYSE',
                    listingCurrency: 'USD',
                    reportingCurrency: 'USD',
                    fyEndMonth: 12,
                ),
            ],
            macroRequirements: new MacroRequirements(
                commodityBenchmark: 'brent_crude',
            ),
            dataRequirements: new DataRequirements(
                historyYears: 5,
                quartersToFetch: 8,
                valuationMetrics: [
                    new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true),
                ],
                annualFinancialMetrics: [],
                quarterMetrics: [],
                operationalMetrics: [],
            ),
            focalTickers: $focalTickers,
        );
    }

    private function createIndustryConfig(): IndustryConfig
    {
        return new IndustryConfig(
            id: 'oil-majors',
            name: 'Oil Majors',
            sector: 'Energy',
            companies: [
                new CompanyConfig(
                    ticker: 'SHEL',
                    name: 'Shell plc',
                    listingExchange: 'LSE',
                    listingCurrency: 'GBP',
                    reportingCurrency: 'USD',
                    fyEndMonth: 12,
                ),
                new CompanyConfig(
                    ticker: 'XOM',
                    name: 'Exxon Mobil',
                    listingExchange: 'NYSE',
                    listingCurrency: 'USD',
                    reportingCurrency: 'USD',
                    fyEndMonth: 12,
                ),
            ],
            macroRequirements: new MacroRequirements(
                commodityBenchmark: 'brent_crude',
            ),
            dataRequirements: new DataRequirements(
                historyYears: 5,
                quartersToFetch: 8,
                valuationMetrics: [
                    new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true),
                    new MetricDefinition('fwd_pe', MetricDefinition::UNIT_RATIO, true),
                    new MetricDefinition('div_yield', MetricDefinition::UNIT_PERCENT, false),
                ],
                annualFinancialMetrics: [],
                quarterMetrics: [],
                operationalMetrics: [],
            ),
        );
    }
}
