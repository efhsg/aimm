<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\RemoveFocalRequest;
use app\handlers\peergroup\RemoveFocalHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\RemoveFocalHandler
 */
final class RemoveFocalHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private RemoveFocalHandler $handler;
    private int $groupId;
    private int $companyId1;
    private int $companyId2;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new RemoveFocalHandler(
            $this->peerGroupQuery,
            $this->memberQuery,
            $this->logger
        );

        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();

        $this->groupId = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test Group',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->companyId1 = $this->companyQuery->findOrCreate('AAPL');
        $this->companyId2 = $this->companyQuery->findOrCreate('MSFT');
        $this->memberQuery->addMember($this->groupId, $this->companyId1, true, 0, 'admin');
        $this->memberQuery->addMember($this->groupId, $this->companyId2, true, 0, 'admin');
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testRemoveFocalSucceeds(): void
    {
        $request = new RemoveFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->removeFocal($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify focal was removed
        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(1, $focals);
        $this->assertSame($this->companyId2, (int) $focals[0]['company_id']);
    }

    public function testRemoveFocalPreservesOtherFocals(): void
    {
        $request = new RemoveFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->removeFocal($request);

        $this->assertTrue($result->success);

        // Second company should still be focal
        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(1, $focals);
        $this->assertSame($this->companyId2, (int) $focals[0]['company_id']);
    }

    public function testRemoveFocalPreservesMembership(): void
    {
        $request = new RemoveFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->removeFocal($request);

        $this->assertTrue($result->success);

        // Company should still be a member
        $this->assertTrue($this->memberQuery->isMember($this->groupId, $this->companyId1));
    }

    public function testRemoveFocalFailsWhenGroupNotFound(): void
    {
        $request = new RemoveFocalRequest(
            groupId: 99999,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->removeFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testRemoveFocalFailsWhenCompanyNotMember(): void
    {
        $otherCompanyId = $this->companyQuery->findOrCreate('GOOGL');

        $request = new RemoveFocalRequest(
            groupId: $this->groupId,
            companyId: $otherCompanyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->removeFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not a member', strtolower(implode(' ', $result->errors)));
    }
}
