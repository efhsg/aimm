<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\TogglePeerGroupRequest;
use app\handlers\peergroup\TogglePeerGroupHandler;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\TogglePeerGroupHandler
 */
final class TogglePeerGroupHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private TogglePeerGroupHandler $handler;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->handler = new TogglePeerGroupHandler($this->peerGroupQuery, $this->memberQuery, $this->logger);

        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    public function testToggleActivatesToTrue(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test Group',
            'sector' => 'Energy',
            'is_active' => 0,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new TogglePeerGroupRequest(
            id: $id,
            isActive: true,
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertTrue($result->group->isActive);
        $this->assertEmpty($result->errors);
    }

    public function testToggleDeactivatesToFalse(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test Group',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new TogglePeerGroupRequest(
            id: $id,
            isActive: false,
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertFalse($result->group->isActive);
    }

    public function testToggleFailsWhenGroupNotFound(): void
    {
        $request = new TogglePeerGroupRequest(
            id: 99999,
            isActive: true,
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->group);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testToggleUpdatesAuditFields(): void
    {
        $id = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test',
            'sector' => 'Energy',
            'is_active' => 0,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new TogglePeerGroupRequest(
            id: $id,
            isActive: true,
            actorUsername: 'toggler',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertSame('toggler', $result->group->updatedBy);
    }
}
