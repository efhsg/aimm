<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\ClearFocalsRequest;
use app\handlers\peergroup\ClearFocalsHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\ClearFocalsHandler
 */
final class ClearFocalsHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private ClearFocalsHandler $handler;
    private int $groupId;
    private int $companyId1;
    private int $companyId2;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new ClearFocalsHandler(
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

    public function testClearFocalsSucceeds(): void
    {
        $request = new ClearFocalsRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->clearFocals($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify all focals were cleared
        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(0, $focals);
    }

    public function testClearFocalsPreservesMembership(): void
    {
        $request = new ClearFocalsRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->clearFocals($request);

        $this->assertTrue($result->success);

        // Both companies should still be members
        $this->assertTrue($this->memberQuery->isMember($this->groupId, $this->companyId1));
        $this->assertTrue($this->memberQuery->isMember($this->groupId, $this->companyId2));
    }

    public function testClearFocalsSucceedsWhenNoFocals(): void
    {
        // Clear focals first
        $this->memberQuery->clearFocals($this->groupId);

        $request = new ClearFocalsRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->clearFocals($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);
    }

    public function testClearFocalsFailsWhenGroupNotFound(): void
    {
        $request = new ClearFocalsRequest(
            groupId: 99999,
            actorUsername: 'admin',
        );

        $result = $this->handler->clearFocals($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testClearFocalsClearsManyFocals(): void
    {
        // Add more focals
        $companyId3 = $this->companyQuery->findOrCreate('GOOGL');
        $companyId4 = $this->companyQuery->findOrCreate('AMZN');
        $this->memberQuery->addMember($this->groupId, $companyId3, true, 0, 'admin');
        $this->memberQuery->addMember($this->groupId, $companyId4, true, 0, 'admin');

        // Verify we have 4 focals
        $this->assertCount(4, $this->memberQuery->findFocalsByGroup($this->groupId));

        $request = new ClearFocalsRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->clearFocals($request);

        $this->assertTrue($result->success);

        // All focals should be cleared
        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(0, $focals);

        // All members should still be present
        $this->assertSame(4, $this->memberQuery->countByGroup($this->groupId));
    }
}
