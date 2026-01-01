<?php

declare(strict_types=1);

namespace tests\unit\handlers\collectionpolicy;

use app\dto\collectionpolicy\UpdateCollectionPolicyRequest;
use app\handlers\collectionpolicy\UpdateCollectionPolicyHandler;
use app\queries\CollectionPolicyQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\collectionpolicy\UpdateCollectionPolicyHandler
 */
final class UpdateCollectionPolicyHandlerTest extends Unit
{
    private Logger $logger;
    private CollectionPolicyQuery $policyQuery;
    private UpdateCollectionPolicyHandler $handler;
    private int $policyId;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->policyQuery = new CollectionPolicyQuery(Yii::$app->db);
        $this->handler = new UpdateCollectionPolicyHandler(
            $this->policyQuery,
            $this->logger
        );

        Yii::$app->db->createCommand()->delete('collection_policy')->execute();

        $this->policyId = $this->policyQuery->insert([
            'slug' => 'test-policy',
            'name' => 'Test Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
    }

    public function testUpdateSucceeds(): void
    {
        $request = new UpdateCollectionPolicyRequest(
            id: $this->policyId,
            name: 'Updated Policy',
            description: 'Updated description',
            historyYears: 7,
            quartersToFetch: 12,
            valuationMetricsJson: '["PE"]',
            annualFinancialMetricsJson: '["Revenue"]',
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: 'Brent',
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->policy);
        $this->assertSame('Updated Policy', $result->policy['name']);
        $this->assertSame(7, (int) $result->policy['history_years']);
        $this->assertEmpty($result->errors);
    }

    public function testUpdateFailsWhenPolicyNotFound(): void
    {
        $request = new UpdateCollectionPolicyRequest(
            id: 99999,
            name: 'Updated Policy',
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

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdateFailsWithEmptyName(): void
    {
        $request = new UpdateCollectionPolicyRequest(
            id: $this->policyId,
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

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('name', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdateFailsWithInvalidJson(): void
    {
        $request = new UpdateCollectionPolicyRequest(
            id: $this->policyId,
            name: 'Updated Policy',
            description: null,
            historyYears: 5,
            quartersToFetch: 8,
            valuationMetricsJson: null,
            annualFinancialMetricsJson: '{invalid',
            quarterlyFinancialMetricsJson: null,
            operationalMetricsJson: null,
            commodityBenchmark: null,
            marginProxy: null,
            sectorIndex: null,
            requiredIndicatorsJson: null,
            optionalIndicatorsJson: null,
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('json', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdateHandlesNullableFields(): void
    {
        $request = new UpdateCollectionPolicyRequest(
            id: $this->policyId,
            name: 'Updated Policy',
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

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertSame('Updated Policy', $result->policy['name']);
    }
}
