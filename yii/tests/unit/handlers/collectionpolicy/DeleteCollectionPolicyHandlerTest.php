<?php

declare(strict_types=1);

namespace tests\unit\handlers\collectionpolicy;

use app\dto\collectionpolicy\DeleteCollectionPolicyRequest;
use app\handlers\collectionpolicy\DeleteCollectionPolicyHandler;
use app\queries\CollectionPolicyQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\collectionpolicy\DeleteCollectionPolicyHandler
 */
final class DeleteCollectionPolicyHandlerTest extends Unit
{
    private Logger $logger;
    private CollectionPolicyQuery $policyQuery;
    private PeerGroupQuery $peerGroupQuery;
    private DeleteCollectionPolicyHandler $handler;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->policyQuery = new CollectionPolicyQuery(Yii::$app->db);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->handler = new DeleteCollectionPolicyHandler(
            $this->policyQuery,
            Yii::$app->db,
            $this->logger
        );

        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
    }

    public function testDeleteSucceeds(): void
    {
        $policyId = $this->policyQuery->insert([
            'slug' => 'test-policy',
            'name' => 'Test Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new DeleteCollectionPolicyRequest(
            id: $policyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->delete($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify policy was deleted
        $this->assertNull($this->policyQuery->findById($policyId));
    }

    public function testDeleteFailsWhenPolicyNotFound(): void
    {
        $request = new DeleteCollectionPolicyRequest(
            id: 99999,
            actorUsername: 'admin',
        );

        $result = $this->handler->delete($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testDeleteFailsWhenPolicyInUse(): void
    {
        $policyId = $this->policyQuery->insert([
            'slug' => 'test-policy',
            'name' => 'Test Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create peer group using this policy
        $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test Group',
            'sector' => 'Energy',
            'policy_id' => $policyId,
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new DeleteCollectionPolicyRequest(
            id: $policyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->delete($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('cannot delete', strtolower(implode(' ', $result->errors)));
        $this->assertStringContainsString('peer group', strtolower(implode(' ', $result->errors)));
    }

    public function testDeleteReturnsDeletedResult(): void
    {
        $policyId = $this->policyQuery->insert([
            'slug' => 'test-policy',
            'name' => 'Test Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new DeleteCollectionPolicyRequest(
            id: $policyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->delete($request);

        $this->assertTrue($result->success);
        $this->assertNull($result->policy);
    }
}
