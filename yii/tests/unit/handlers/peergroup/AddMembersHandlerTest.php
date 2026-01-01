<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\peergroup\AddMembersRequest;
use app\handlers\peergroup\AddMembersHandler;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\AddMembersHandler
 */
final class AddMembersHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CompanyQuery $companyQuery;
    private AddMembersHandler $handler;
    private int $groupId;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->handler = new AddMembersHandler(
            $this->peerGroupQuery,
            $this->memberQuery,
            $this->companyQuery,
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
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testAddSingleTickerCreatesCompanyAndMember(): void
    {
        $request = new AddMembersRequest(
            groupId: $this->groupId,
            tickers: ['AAPL'],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->added);
        $this->assertSame('AAPL', $result->added[0]);
        $this->assertEmpty($result->skipped);
        $this->assertEmpty($result->errors);
    }

    public function testAddMultipleTickersInBulk(): void
    {
        $request = new AddMembersRequest(
            groupId: $this->groupId,
            tickers: ['AAPL', 'MSFT', 'GOOGL'],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertTrue($result->success);
        $this->assertCount(3, $result->added);
        $this->assertEmpty($result->skipped);
    }

    public function testAddSkipsDuplicateMembers(): void
    {
        // Add AAPL first
        $companyId = $this->companyQuery->findOrCreate('AAPL');
        $this->memberQuery->addMember($this->groupId, $companyId, false, 0, 'admin');

        // Try to add AAPL and MSFT
        $request = new AddMembersRequest(
            groupId: $this->groupId,
            tickers: ['AAPL', 'MSFT'],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->added); // Only MSFT added
        $this->assertCount(1, $result->skipped); // AAPL skipped
        $this->assertSame('AAPL', $result->skipped[0]);
    }

    public function testAddFailsWhenGroupNotFound(): void
    {
        $request = new AddMembersRequest(
            groupId: 99999,
            tickers: ['AAPL'],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertFalse($result->success);
        $this->assertEmpty($result->added);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testAddFailsWithEmptyTickers(): void
    {
        $request = new AddMembersRequest(
            groupId: $this->groupId,
            tickers: [],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('ticker', strtolower(implode(' ', $result->errors)));
    }

    public function testAddNormalizesTickersToUppercase(): void
    {
        $request = new AddMembersRequest(
            groupId: $this->groupId,
            tickers: ['aapl', 'msft'],
            actorUsername: 'admin',
        );

        $result = $this->handler->add($request);

        $this->assertTrue($result->success);
        $this->assertContains('AAPL', $result->added);
        $this->assertContains('MSFT', $result->added);
    }
}
