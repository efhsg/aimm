<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\CreatePeerGroupRequest;
use app\handlers\peergroup\CreatePeerGroupHandler;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\CreatePeerGroupHandler
 */
final class CreatePeerGroupHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CreatePeerGroupHandler $handler;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->handler = new CreatePeerGroupHandler($this->peerGroupQuery, $this->memberQuery, $this->logger);

        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
    }

    public function testCreateSucceedsWithValidData(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Test Group',
            slug: 'test-group',
            sector: 'Energy',
            actorUsername: 'admin',
            description: 'Test description',
            policyId: null,
            isActive: true,
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->group);
        $this->assertSame('test-group', $result->group->slug);
        $this->assertSame('Test Group', $result->group->name);
        $this->assertSame('Energy', $result->group->sector);
        $this->assertSame('admin', $result->group->createdBy);
        $this->assertEmpty($result->errors);
    }

    public function testCreateFailsWithEmptyName(): void
    {
        $request = new CreatePeerGroupRequest(
            name: '',
            slug: 'test-slug',
            sector: 'Energy',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->group);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('name', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithEmptySlug(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Test Group',
            slug: '',
            sector: 'Energy',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('slug', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithInvalidSlugPattern(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Test Group',
            slug: 'Invalid Slug!',
            sector: 'Energy',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    public function testCreateFailsWithDuplicateSlug(): void
    {
        // Create first group
        $this->peerGroupQuery->insert([
            'slug' => 'duplicate-slug',
            'name' => 'First Group',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Try to create second with same slug
        $request = new CreatePeerGroupRequest(
            name: 'Second Group',
            slug: 'duplicate-slug',
            sector: 'Tech',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('exists', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithEmptySector(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Test Group',
            slug: 'test-group',
            sector: '',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('sector', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateSetsAuditFields(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Audit Test',
            slug: 'audit-test',
            sector: 'Finance',
            actorUsername: 'test_user',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertSame('test_user', $result->group->createdBy);
        $this->assertNull($result->group->updatedBy); // Not set on creation
    }

    public function testCreateWithPolicyId(): void
    {
        $request = new CreatePeerGroupRequest(
            name: 'Policy Test',
            slug: 'policy-test',
            sector: 'Energy',
            actorUsername: 'admin',
            policyId: null,
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertNull($result->group->policyId);
    }
}
