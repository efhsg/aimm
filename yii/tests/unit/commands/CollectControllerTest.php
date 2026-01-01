<?php

declare(strict_types=1);

namespace tests\unit\commands;

use app\commands\CollectController;
use app\dto\GateResult;
use app\dto\peergroup\CollectPeerGroupRequest;
use app\dto\peergroup\CollectPeerGroupResult;
use app\enums\CollectionStatus;
use app\handlers\peergroup\CollectPeerGroupInterface;
use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use RuntimeException;
use Yii;
use yii\base\Module;
use yii\console\ExitCode;
use yii\log\Logger;

final class CollectControllerTest extends Unit
{
    private Module $module;
    private PeerGroupQuery $peerGroupQuery;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = Yii::$app;
        $this->peerGroupQuery = new PeerGroupQuery(Yii::$app->db);

        // Clean up
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();

        // Create test peer group
        $this->groupId = $this->peerGroupQuery->insert([
            'slug' => 'energy',
            'name' => 'Energy',
            'sector' => 'Energy',
            'is_active' => 1,
            'created_by' => 'test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        Yii::$app->db->createCommand()->delete('industry_peer_group')->execute();
        parent::tearDown();
    }

    public function testActionPeerGroupReturnsOkOnSuccess(): void
    {
        $collector = $this->createMock(CollectPeerGroupInterface::class);
        $collector->expects($this->once())
            ->method('collect')
            ->with($this->callback(
                fn (CollectPeerGroupRequest $request): bool => $request->groupId === $this->groupId
            ))
            ->willReturn($this->createResult(CollectionStatus::Complete));

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->peerGroupQuery,
            $logger
        );

        $exitCode = $controller->actionPeerGroup('energy');

        $this->assertSame(ExitCode::OK, $exitCode);
    }

    public function testActionPeerGroupReturnsDataErrWhenGroupMissing(): void
    {
        $collector = $this->createMock(CollectPeerGroupInterface::class);
        $collector->expects($this->never())->method('collect');

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->peerGroupQuery,
            $logger
        );

        $exitCode = $controller->actionPeerGroup('missing');

        $this->assertSame(ExitCode::DATAERR, $exitCode);
    }

    public function testActionPeerGroupReturnsErrorWhenCollectorFails(): void
    {
        $collector = $this->createMock(CollectPeerGroupInterface::class);
        $collector->method('collect')->willThrowException(new RuntimeException('boom'));

        $logger = $this->createMock(Logger::class);
        $logger->method('log');

        $controller = new CollectController(
            'collect',
            $this->module,
            $collector,
            $this->peerGroupQuery,
            $logger
        );

        $exitCode = $controller->actionPeerGroup('energy');

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    private function createResult(CollectionStatus $status): CollectPeerGroupResult
    {
        return CollectPeerGroupResult::success(
            runId: 1,
            datapackId: 'dp-123',
            status: $status,
            gateResult: new GateResult(true, [], []),
        );
    }
}
