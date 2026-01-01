<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\SetFocalRequest;
use app\handlers\peergroup\SetFocalHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\SetFocalHandler
 */
final class SetFocalHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private SetFocalHandler $handler;
    private int $groupId;
    private int $companyId1;
    private int $companyId2;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new SetFocalHandler(
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
        $this->memberQuery->addMember($this->groupId, $this->companyId1, false, 0, 'admin');
        $this->memberQuery->addMember($this->groupId, $this->companyId2, false, 0, 'admin');
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testSetFocalSucceeds(): void
    {
        $request = new SetFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->setFocal($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        // Verify focal was set
        $focal = $this->memberQuery->findFocalByGroup($this->groupId);
        $this->assertNotNull($focal);
        $this->assertSame($this->companyId1, (int) $focal['company_id']);
    }

    public function testSetFocalClearsPreviousFocal(): void
    {
        // Set first focal
        $this->memberQuery->setFocal($this->groupId, $this->companyId1);

        // Set second focal
        $request = new SetFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId2,
            actorUsername: 'admin',
        );

        $result = $this->handler->setFocal($request);

        $this->assertTrue($result->success);

        // Verify only second is focal
        $focal = $this->memberQuery->findFocalByGroup($this->groupId);
        $this->assertSame($this->companyId2, (int) $focal['company_id']);

        // Verify first is not focal
        $members = $this->memberQuery->findByGroup($this->groupId);
        $member1 = array_values(array_filter($members, fn ($m) => (int)$m['company_id'] === $this->companyId1))[0];
        $this->assertFalse((bool) $member1['is_focal']);
    }

    public function testSetFocalFailsWhenGroupNotFound(): void
    {
        $request = new SetFocalRequest(
            groupId: 99999,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->setFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testSetFocalFailsWhenCompanyNotMember(): void
    {
        $otherCompanyId = $this->companyQuery->findOrCreate('GOOGL');

        $request = new SetFocalRequest(
            groupId: $this->groupId,
            companyId: $otherCompanyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->setFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not a member', strtolower(implode(' ', $result->errors)));
    }
}
