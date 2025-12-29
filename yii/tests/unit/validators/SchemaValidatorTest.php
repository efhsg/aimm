<?php

declare(strict_types=1);

namespace tests\unit\validators;

use app\dto\ValidationResult;
use app\validators\SchemaValidator;
use Codeception\Test\Unit;
use stdClass;

/**
 * @covers \app\validators\SchemaValidator
 */
final class SchemaValidatorTest extends Unit
{
    public function testSchemaRejectsWebFetchWithoutSourceUrlOrSourceLocator(): void
    {
        $marketCap = $this->baseMoneyDatapoint('web_fetch');
        unset($marketCap['source_url'], $marketCap['source_locator']);

        $result = $this->validateMarketCap($marketCap);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testSchemaRejectsNotFoundWithNullAttemptedSources(): void
    {
        $marketCap = $this->baseMoneyDatapoint('not_found');
        $marketCap['value'] = null;
        $marketCap['attempted_sources'] = null;

        $result = $this->validateMarketCap($marketCap);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testSchemaRejectsDerivedWithoutFormulaOrDerivedFrom(): void
    {
        $marketCap = $this->baseMoneyDatapoint('derived');
        unset($marketCap['derived_from'], $marketCap['formula']);

        $result = $this->validateMarketCap($marketCap);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    private function validateMarketCap(array $marketCap): ValidationResult
    {
        $payload = $this->baseDataPack($marketCap);
        $json = json_encode($payload);

        $validator = new SchemaValidator($this->schemaPath());

        return $validator->validate($json ?: '', 'industry-datapack.schema.json');
    }

    private function schemaPath(): string
    {
        return dirname(__DIR__, 3) . '/config/schemas';
    }

    private function baseMoneyDatapoint(string $method): array
    {
        return [
            'value' => 100,
            'unit' => 'currency',
            'currency' => 'USD',
            'scale' => 'units',
            'as_of' => '2024-01-01',
            'source_url' => 'https://finance.yahoo.com/quote/AAPL',
            'retrieved_at' => '2024-01-01T00:00:00Z',
            'method' => $method,
            'source_locator' => [
                'type' => 'html',
                'selector' => 'td[data-test="MARKET_CAP-value"]',
                'snippet' => '1.00B',
            ],
            'attempted_sources' => ['https://finance.yahoo.com/quote/AAPL'],
            'derived_from' => ['/companies/AAPL/valuation/market_cap'],
            'formula' => 'value',
            'fx_conversion' => null,
            'cache_source' => null,
            'cache_age_days' => null,
        ];
    }

    private function baseDataPack(array $marketCap): array
    {
        return [
            'industry_id' => 'energy',
            'datapack_id' => 'dp-123',
            'collected_at' => '2024-01-01T00:00:00Z',
            'macro' => [
                'commodity_benchmark' => null,
                'margin_proxy' => null,
                'sector_index' => null,
                'additional_indicators' => new stdClass(),
            ],
            'companies' => [
                'AAPL' => [
                    'ticker' => 'AAPL',
                    'name' => 'Apple Inc',
                    'listing_exchange' => 'NASDAQ',
                    'listing_currency' => 'USD',
                    'reporting_currency' => 'USD',
                    'valuation' => [
                        'market_cap' => $marketCap,
                        'fwd_pe' => null,
                        'trailing_pe' => null,
                        'ev_ebitda' => null,
                        'free_cash_flow_ttm' => null,
                        'fcf_yield' => null,
                        'div_yield' => null,
                        'net_debt_ebitda' => null,
                        'price_to_book' => null,
                    ],
                    'financials' => [
                        'history_years' => 0,
                        'annual_data' => [],
                    ],
                    'quarters' => [
                        'quarters' => new stdClass(),
                    ],
                    'operational' => null,
                ],
            ],
            'collection_log' => [
                'started_at' => '2024-01-01T00:00:00Z',
                'completed_at' => '2024-01-01T00:01:00Z',
                'duration_seconds' => 60,
                'company_statuses' => ['AAPL' => 'complete'],
                'macro_status' => 'complete',
                'total_attempts' => 1,
            ],
        ];
    }
}
