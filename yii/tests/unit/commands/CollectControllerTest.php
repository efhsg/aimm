<?php

declare(strict_types=1);

namespace tests\unit\commands;

use app\commands\CollectController;
use app\dto\CollectIndustryRequest;
use app\dto\CollectIndustryResult;
use app\dto\GateResult;
use app\enums\CollectionStatus;
use app\handlers\collection\CollectIndustryInterface;
use app\models\IndustryConfig as IndustryConfigRecord;
use app\queries\IndustryConfigQuery;
use app\validators\SchemaValidator;
use Codeception\Test\Unit;
use JsonException;
use RuntimeException;
use Yii;
use yii\base\Module;
use yii\console\ExitCode;
use yii\log\Logger;

final class CollectControllerTest extends Unit
{
    private Module $module;
    private IndustryConfigQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        IndustryConfigRecord::deleteAll();
        $this->module = Yii::$app;
        $this->query = $this->createQuery();
    }

    protected function tearDown(): void
    {
        IndustryConfigRecord::deleteAll();
        parent::tearDown();
    }

    public function testActionIndustryReturnsOkOnSuccess(): void
    {
        $this->createRecord(
            industryId: 'energy',
            name: 'Energy',
            configJson: $this->buildConfigJson('energy', 'Energy')
        );

        $collector = $this->createMock(CollectIndustryInterface::class);
        $collector->expects($this->once())
            ->method('collect')
            ->with($this->callback(
                static fn (CollectIndustryRequest $request): bool => $request->config->id === 'energy'
            ))
            ->willReturn($this->createResult('energy', CollectionStatus::Complete));

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->query,
            $logger
        );

        $exitCode = $controller->actionIndustry('energy');

        $this->assertSame(ExitCode::OK, $exitCode);
    }

    public function testActionIndustryReturnsDataErrWhenConfigMissing(): void
    {
        $collector = $this->createMock(CollectIndustryInterface::class);
        $collector->expects($this->never())->method('collect');

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->query,
            $logger
        );

        $exitCode = $controller->actionIndustry('missing');

        $this->assertSame(ExitCode::DATAERR, $exitCode);
    }

    public function testActionIndustryReturnsErrorWhenCollectorFails(): void
    {
        $this->createRecord(
            industryId: 'energy',
            name: 'Energy',
            configJson: $this->buildConfigJson('energy', 'Energy')
        );

        $collector = $this->createMock(CollectIndustryInterface::class);
        $collector->method('collect')->willThrowException(new RuntimeException('boom'));

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->query,
            $logger
        );

        $exitCode = $controller->actionIndustry('energy');

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    private function createRecord(
        string $industryId,
        string $name,
        string $configJson
    ): IndustryConfigRecord
    {
        $record = new IndustryConfigRecord();
        $record->industry_id = $industryId;
        $record->name = $name;
        $record->config_yaml = $configJson;
        $record->is_active = true;
        $record->save();

        return $record;
    }

    private function createResult(string $industryId, CollectionStatus $status): CollectIndustryResult
    {
        return new CollectIndustryResult(
            industryId: $industryId,
            datapackId: 'dp-123',
            dataPackPath: '/tmp/datapack.json',
            gateResult: new GateResult(true, [], []),
            overallStatus: $status,
            companyStatuses: ['AAA' => $status],
        );
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
                    'ticker' => 'AAA',
                    'name' => 'AAA Corp',
                    'listing_exchange' => 'NYSE',
                    'listing_currency' => 'USD',
                    'reporting_currency' => 'USD',
                    'fy_end_month' => 12,
                    'alternative_tickers' => null,
                ],
            ],
            'macro_requirements' => [
                'commodity_benchmark' => 'BRENT',
                'margin_proxy' => null,
                'sector_index' => 'XLE',
                'required_indicators' => [],
                'optional_indicators' => [],
            ],
            'data_requirements' => [
                'history_years' => 1,
                'quarters_to_fetch' => 4,
                'required_valuation_metrics' => ['market_cap'],
                'optional_valuation_metrics' => [],
            ],
        ];

        try {
            return json_encode($config, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }
}
