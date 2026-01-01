<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\RemoveMemberRequest;
use app\handlers\peergroup\RemoveMemberHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\RemoveMemberHandler
 */
final class RemoveMemberHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private RemoveMemberHandler $handler;
    private int $groupId;
    private int $companyId;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new RemoveMemberHandler(
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

        $this->companyId = $this->companyQuery->findOrCreate('AAPL');
        $this->memberQuery->addMember($this->groupId, $this->companyId, false, 0, 'admin');
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testRemoveSucceeds(): void
    {
        $request = new RemoveMemberRequest(
            groupId: $this->groupId,
            companyId: $this->companyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->remove($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify member was removed
        $this->assertFalse($this->memberQuery->isMember($this->groupId, $this->companyId));
    }

    public function testRemoveFailsWhenGroupNotFound(): void
    {
        $request = new RemoveMemberRequest(
            groupId: 99999,
            companyId: $this->companyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->remove($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testRemoveFailsWhenCompanyNotMember(): void
    {
        $otherCompanyId = $this->companyQuery->findOrCreate('MSFT');

        $request = new RemoveMemberRequest(
            groupId: $this->groupId,
            companyId: $otherCompanyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->remove($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not a member', strtolower(implode(' ', $result->errors)));
    }
}
