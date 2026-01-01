<?php

declare(strict_types=1);

namespace tests\unit\handlers\collectionpolicy;

use app\dto\collectionpolicy\SetDefaultPolicyRequest;
use app\handlers\collectionpolicy\SetDefaultPolicyHandler;
use app\queries\CollectionPolicyQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\collectionpolicy\SetDefaultPolicyHandler
 */
final class SetDefaultPolicyHandlerTest extends Unit
{
    private Logger $logger;
    private CollectionPolicyQuery $policyQuery;
    private SetDefaultPolicyHandler $handler;
    private int $policyId;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->policyQuery = new CollectionPolicyQuery(Yii::$app->db);
        $this->handler = new SetDefaultPolicyHandler(
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

    public function testSetDefaultSucceeds(): void
    {
        $request = new SetDefaultPolicyRequest(
            id: $this->policyId,
            sector: 'Energy',
            clear: false,
            actorUsername: 'admin',
        );

        $result = $this->handler->setDefault($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->policy);
        $this->assertEmpty($result->errors);

        // Verify default was set
        $default = $this->policyQuery->findDefaultForSector('Energy');
        $this->assertNotNull($default);
        $this->assertSame($this->policyId, (int) $default['id']);
    }

    public function testClearDefaultSucceeds(): void
    {
        // Set default first
        $this->policyQuery->setDefaultForSector($this->policyId, 'Energy');

        $request = new SetDefaultPolicyRequest(
            id: $this->policyId,
            sector: 'Energy',
            clear: true,
            actorUsername: 'admin',
        );

        $result = $this->handler->setDefault($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify default was cleared
        $default = $this->policyQuery->findDefaultForSector('Energy');
        $this->assertNull($default);
    }

    public function testSetDefaultFailsWhenPolicyNotFound(): void
    {
        $request = new SetDefaultPolicyRequest(
            id: 99999,
            sector: 'Energy',
            clear: false,
            actorUsername: 'admin',
        );

        $result = $this->handler->setDefault($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testSetDefaultFailsWithEmptySector(): void
    {
        $request = new SetDefaultPolicyRequest(
            id: $this->policyId,
            sector: '',
            clear: false,
            actorUsername: 'admin',
        );

        $result = $this->handler->setDefault($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('sector', strtolower(implode(' ', $result->errors)));
    }

    public function testSetDefaultReplacesExistingDefault(): void
    {
        // Create second policy
        $policy2Id = $this->policyQuery->insert([
            'slug' => 'test-policy-2',
            'name' => 'Test Policy 2',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Set first as default
        $this->policyQuery->setDefaultForSector($this->policyId, 'Energy');

        // Set second as default
        $request = new SetDefaultPolicyRequest(
            id: $policy2Id,
            sector: 'Energy',
            clear: false,
            actorUsername: 'admin',
        );

        $result = $this->handler->setDefault($request);

        $this->assertTrue($result->success);

        // Verify only second is default
        $default = $this->policyQuery->findDefaultForSector('Energy');
        $this->assertSame($policy2Id, (int) $default['id']);
    }
}
