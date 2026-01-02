<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\AddFocalRequest;
use app\handlers\peergroup\AddFocalHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\AddFocalHandler
 */
final class AddFocalHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private AddFocalHandler $handler;
    private int $groupId;
    private int $companyId1;
    private int $companyId2;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new AddFocalHandler(
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

    public function testAddFocalSucceeds(): void
    {
        $request = new AddFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->addFocal($request);

        $this->assertTrue($result->success);
        $this->assertEmpty($result->errors);

        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(1, $focals);
        $this->assertSame($this->companyId1, (int) $focals[0]['company_id']);
    }

    public function testAddFocalPreservesExistingFocals(): void
    {
        // Set first company as focal
        $this->memberQuery->addFocal($this->groupId, $this->companyId1);

        // Add second company as focal
        $request = new AddFocalRequest(
            groupId: $this->groupId,
            companyId: $this->companyId2,
            actorUsername: 'admin',
        );

        $result = $this->handler->addFocal($request);

        $this->assertTrue($result->success);

        // Both should be focals
        $focals = $this->memberQuery->findFocalsByGroup($this->groupId);
        $this->assertCount(2, $focals);
        $focalIds = array_map(fn ($f) => (int) $f['company_id'], $focals);
        $this->assertContains($this->companyId1, $focalIds);
        $this->assertContains($this->companyId2, $focalIds);
    }

    public function testAddFocalFailsWhenGroupNotFound(): void
    {
        $request = new AddFocalRequest(
            groupId: 99999,
            companyId: $this->companyId1,
            actorUsername: 'admin',
        );

        $result = $this->handler->addFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testAddFocalFailsWhenCompanyNotMember(): void
    {
        $otherCompanyId = $this->companyQuery->findOrCreate('GOOGL');

        $request = new AddFocalRequest(
            groupId: $this->groupId,
            companyId: $otherCompanyId,
            actorUsername: 'admin',
        );

        $result = $this->handler->addFocal($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not a member', strtolower(implode(' ', $result->errors)));
    }
}
