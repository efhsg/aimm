<?php

declare(strict_types=1);

namespace tests\unit\handlers\collectionpolicy;

use app\dto\collectionpolicy\CreateCollectionPolicyRequest;
use app\handlers\collectionpolicy\CreateCollectionPolicyHandler;
use app\queries\CollectionPolicyQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\collectionpolicy\CreateCollectionPolicyHandler
 */
final class CreateCollectionPolicyHandlerTest extends Unit
{
    private Logger $logger;
    private CollectionPolicyQuery $policyQuery;
    private CreateCollectionPolicyHandler $handler;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->policyQuery = new CollectionPolicyQuery(Yii::$app->db);
        $this->handler = new CreateCollectionPolicyHandler(
            $this->policyQuery,
            $this->logger
        );

        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
    }

    public function testCreateSucceeds(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'test-policy',
            name: 'Test Policy',
            description: 'Test description',
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: '["PE", "EV/EBITDA"]',
            annualFinancialMetricsJson: '["Revenue", "Net Income"]',
            quarterlyFinancialMetricsJson: '["Revenue"]',
            operationalMetricsJson: '[]',
            commodityBenchmark: 'WTI',
            marginProxy: 'EBITDA Margin',
            sectorIndex: 'XLE',
            requiredIndicatorsJson: '["WTI"]',
            optionalIndicatorsJson: '[]',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->policy);
        $this->assertSame('test-policy', $result->policy['slug']);
        $this->assertSame('Test Policy', $result->policy['name']);
        $this->assertEmpty($result->errors);
    }

    public function testCreateFailsWithEmptySlug(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: '',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('slug', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithInvalidSlugPattern(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'Test Policy!',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('lowercase', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithDuplicateSlug(): void
    {
        // Create first policy
        $this->policyQuery->insert([
            'slug' => 'existing-policy',
            'name' => 'Existing Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new CreateCollectionPolicyRequest(
            slug: 'existing-policy',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('already exists', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithEmptyName(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'test-policy',
            name: '',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('name', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithInvalidJson(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'test-policy',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: '{invalid json',
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('json', strtolower(implode(' ', $result->errors)));
    }

    public function testCreatePopulatesAuditFields(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'test-policy',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'test-user',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertSame('test-user', $result->policy['created_by']);
        $this->assertNotNull($result->policy['created_at']);
        $this->assertNotNull($result->policy['updated_at']);
    }

    public function testCreateHandlesNullableJsonFields(): void
    {
        $request = new CreateCollectionPolicyRequest(
            slug: 'test-policy',
            name: 'Test Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: null,
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->policy);
    }
}
