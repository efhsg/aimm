<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\UpdatePeerGroupRequest;
use app\handlers\peergroup\UpdatePeerGroupHandler;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\UpdatePeerGroupHandler
 */
final class UpdatePeerGroupHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private UpdatePeerGroupHandler $handler;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->handler = new UpdatePeerGroupHandler($this->peerGroupQuery, $this->memberQuery, $this->logger);

        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    public function testUpdateSucceedsWithValidData(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Original Name',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new UpdatePeerGroupRequest(
            id: $id,
            name: 'Updated Name',
            actorUsername: 'editor',
            description: 'New description',
            policyId: null,
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->group);
        $this->assertSame('Updated Name', $result->group->name);
        $this->assertSame('New description', $result->group->description);
        $this->assertNull($result->group->policyId);
        $this->assertSame('editor', $result->group->updatedBy);
        $this->assertEmpty($result->errors);
    }

    public function testUpdateFailsWhenGroupNotFound(): void
    {
        $request = new UpdatePeerGroupRequest(
            id: 99999,
            name: 'Test',
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->group);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdateFailsWithEmptyName(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Original',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new UpdatePeerGroupRequest(
            id: $id,
            name: '',
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    public function testUpdateCanClearPolicyId(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new UpdatePeerGroupRequest(
            id: $id,
            name: 'Test',
            actorUsername: 'admin',
            policyId: null,
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertNull($result->group->policyId);
    }
}
