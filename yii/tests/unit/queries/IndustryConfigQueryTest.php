<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\IndustryConfig as IndustryConfigDto;
use app\models\IndustryConfig as IndustryConfigRecord;
use app\queries\IndustryConfigQuery;
use app\validators\SchemaValidator;
use Codeception\Test\Unit;
use JsonException;
use RuntimeException;
use Yii;

/**
 * @covers \app\queries\IndustryConfigQuery
 */
final class IndustryConfigQueryTest extends Unit
{
    protected function _before(): void
    {
        IndustryConfigRecord::deleteAll();
    }

    public function testFindByIdReturnsDtoWhenConfigIsValid(): void
    {
        $record = $this->createRecord(
            industryId: 'oil-majors',
            name: 'Oil Majors',
            configJson: $this->buildConfigJson('oil-majors', 'Oil Majors')
        );

        $query = $this->createQuery();

        $config = $query->findById('oil-majors');

        $this->assertNotNull($config);
        $this->assertInstanceOf(IndustryConfigDto::class, $config);
        $this->assertSame('oil-majors', $config->id);
        $this->assertSame('Oil Majors', $config->name);
        $this->assertSame('Energy', $config->sector);
        $this->assertCount(1, $config->companies);
        $this->assertSame('NYSE', $config->companies[0]->listingExchange);
        $this->assertSame('USD', $config->companies[0]->reportingCurrency);
        $this->assertSame(12, $config->companies[0]->fyEndMonth);
        $this->assertSame(['SHEL.A', 'SHEL.B'], $config->companies[0]->alternativeTickers);
        $this->assertSame('BRENT', $config->macroRequirements->commodityBenchmark);
        $this->assertSame(['rig_count'], $config->macroRequirements->requiredIndicators);
        $this->assertSame(5, $config->dataRequirements->historyYears);
        $this->assertCount(2, $config->dataRequirements->valuationMetrics);
        $this->assertSame('market_cap', $config->dataRequirements->valuationMetrics[0]->key);
        $this->assertTrue($config->dataRequirements->valuationMetrics[0]->required);
        $this->assertSame('fwd_pe', $config->dataRequirements->valuationMetrics[1]->key);
        $this->assertFalse($config->dataRequirements->valuationMetrics[1]->required);

        $record->delete();
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $query = $this->createQuery();

        $this->assertNull($query->findById('missing-industry'));
    }

    public function testFindByIdThrowsRuntimeExceptionWhenConfigInvalid(): void
    {
        $this->createRecord(
            industryId: 'invalid-industry',
            name: 'Invalid Industry',
            configJson: '{"id":"invalid-industry"}'
        );

        $query = $this->createQuery();

        $this->expectException(RuntimeException::class);

        $query->findById('invalid-industry');
    }

    public function testFindAllActiveReturnsOnlyActiveConfigs(): void
    {
        $this->createRecord(
            industryId: 'active-industry',
            name: 'Active Industry',
            configJson: $this->buildConfigJson('active-industry', 'Active Industry'),
            isActive: true
        );

        $this->createRecord(
            industryId: 'inactive-industry',
            name: 'Inactive Industry',
            configJson: $this->buildConfigJson('inactive-industry', 'Inactive Industry'),
            isActive: false
        );

        $query = $this->createQuery();

        $configs = $query->findAllActive();

        $this->assertCount(1, $configs);
        $this->assertSame('active-industry', $configs[0]->id);
    }

    private function createRecord(
        string $industryId,
        string $name,
        string $configJson,
        bool $isActive = true
    ): IndustryConfigRecord {
        $record = new IndustryConfigRecord();
        $record->industry_id = $industryId;
        $record->name = $name;
        $record->config_json = $configJson;
        $record->is_active = $isActive;
        $record->save();

        return $record;
    }

    private function createQuery(): IndustryConfigQuery
    {
        return new IndustryConfigQuery(
            new SchemaValidator(Yii::$app->basePath . '/config/schemas')
        );
    }

    private function buildConfigJson(string $id, string $name): string
    {
        $config = [
            'id' => $id,
            'name' => $name,
            'sector' => 'Energy',
            'companies' => [
                [
                    'ticker' => 'SHEL',
                    'name' => 'Shell',
                    'listing_exchange' => 'NYSE',
                    'listing_currency' => 'USD',
                    'reporting_currency' => 'USD',
                    'fy_end_month' => 12,
                    'alternative_tickers' => ['SHEL.A', 'SHEL.B'],
                ],
            ],
            'macro_requirements' => [
                'commodity_benchmark' => 'BRENT',
                'margin_proxy' => null,
                'sector_index' => 'XLE',
                'required_indicators' => ['rig_count'],
                'optional_indicators' => ['inventory'],
            ],
            'data_requirements' => [
                'history_years' => 5,
                'quarters_to_fetch' => 8,
                'valuation_metrics' => [
                    ['key' => 'market_cap', 'unit' => 'currency', 'required' => true],
                    ['key' => 'fwd_pe', 'unit' => 'ratio', 'required' => false],
                ],
                'annual_financial_metrics' => [],
                'quarter_metrics' => [],
                'operational_metrics' => [],
            ],
        ];

        try {
            return json_encode($config, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }
}
