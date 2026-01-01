<?php

declare(strict_types=1);

namespace tests\unit\handlers\peergroup;

use app\dto\CollectIndustryResult;
use app\dto\GateResult;
use app\dto\peergroup\CollectPeerGroupRequest;
use app\enums\CollectionStatus;
use app\handlers\collection\CollectIndustryInterface;
use app\handlers\peergroup\CollectPeerGroupHandler;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * @covers \app\handlers\peergroup\CollectPeerGroupHandler
 */
final class CollectPeerGroupHandlerTest extends Unit
{
    private Logger $logger;
    private PeerGroupQuery $peerGroupQuery;
    private PeerGroupMemberQuery $memberQuery;
    private CollectionPolicyQuery $policyQuery;
    private CompanyQuery $companyQuery;
    private CollectIndustryInterface $industryCollector;
    private CollectionRunRepository $runRepository;
    private CollectPeerGroupHandler $handler;
    private int $groupId;
    private int $policyId;

    protected function _before(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);
        $this->memberQuery = new PeerGroupMemberQuery(Yii::$app->db);
        $this->policyQuery = new CollectionPolicyQuery(Yii::$app->db);
        $this->companyQuery = new CompanyQuery(Yii::$app->db);
        $this->industryCollector = $this->createMock(CollectIndustryInterface::class);
        $this->runRepository = new CollectionRunRepository(Yii::$app->db);
        $this->handler = new CollectPeerGroupHandler(
            $this->peerGroupQuery,
            $this->memberQuery,
            $this->policyQuery,
            $this->companyQuery,
            $this->industryCollector,
            $this->runRepository,
            $this->logger
        );

        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();

        // Create policy
        $this->policyId = $this->policyQuery->insert([
            'slug' => 'test-policy',
            'name' => 'Test Policy',
            'history_years' => 5,
            'quarters_to_fetch' => 8,
            'valuation_metrics' => '[]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create group
        $this->groupId = $this->peerGroupQuery->insert([
            'slug' => 'test-group',
            'name' => 'Test Group',
            'sector' => 'Energy',
            'policy_id' => $this->policyId,
            'is_active' => 1,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add member
        $companyId = $this->companyQuery->findOrCreate('AAPL');
        $this->memberQuery->addMember($this->groupId, $companyId, true, 0, 'admin');
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('collection_run')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group_member')->execute();
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        Yii::$app->db->createCommand()->delete('collection_policy')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testCollectFailsWhenGroupNotFound(): void
    {
        $request = new CollectPeerGroupRequest(
            groupId: 99999,
            actorUsername: 'admin',
        );

        $result = $this->handler->collect($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testCollectFailsWhenGroupInactive(): void
    {
        $this->peerGroupQuery->update($this->groupId, ['is_active' => 0]);

        $request = new CollectPeerGroupRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->collect($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not active', strtolower(implode(' ', $result->errors)));
    }

    public function testCollectFailsWhenNoPolicyConfigured(): void
    {
        $this->peerGroupQuery->update($this->groupId, ['policy_id' => null]);

        $request = new CollectPeerGroupRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->collect($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('policy', strtolower(implode(' ', $result->errors)));
    }

    public function testCollectFailsWhenNoMembers(): void
    {
        $this->memberQuery->removeAllFromGroup($this->groupId);

        $request = new CollectPeerGroupRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->collect($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('no members', strtolower(implode(' ', $result->errors)));
    }

    public function testCollectSucceedsWithValidGroup(): void
    {
        // Mock successful collection
        $mockResult = new CollectIndustryResult(
            industryId: 'test-group',
            datapackId: 'test-datapack-123',
            dataPackPath: '/tmp/test.json',
            gateResult: new GateResult(true, [], []),
            overallStatus: CollectionStatus::Complete,
            companyStatuses: ['AAPL' => CollectionStatus::Complete],
        );

        // Mock should create the run record as a side effect
        $this->industryCollector
            ->expects($this->once())
            ->method('collect')
            ->willReturnCallback(function () use ($mockResult) {
                // Create the run record when collect is called
                $runId = $this->runRepository->create(
                    'test-group',
                    'test-datapack-123'
                );
                // Complete it immediately so hasRunningCollection doesn't find it
                $this->runRepository->complete(
                    $runId,
                    'complete',
                    true,
                    0,
                    0,
                    '/tmp/test.json',
                    1024,
                    10
                );
                return $mockResult;
            });

        $request = new CollectPeerGroupRequest(
            groupId: $this->groupId,
            actorUsername: 'admin',
        );

        $result = $this->handler->collect($request);

        $this->assertTrue($result->success);
        $this->assertSame('test-datapack-123', $result->datapackId);
        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNotNull($result->gateResult);
        $this->assertGreaterThan(0, $result->runId);
    }
}
