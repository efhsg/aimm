<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use app\queries\CollectionRunRepository;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

/**
 * @covers \app\queries\CollectionRunRepository
 */
final class CollectionRunRepositoryTest extends Unit
{
    public function testCreateInsertsRunAndReturnsId(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('insert')
            ->with(
                '{{%collection_run}}',
                $this->callback(function (array $data): bool {
                    return $data['industry_id'] === 1
                        && $data['datapack_id'] === 'dp-123'
                        && $data['status'] === 'running'
                        && isset($data['started_at']);
                }),
            )
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('42');

        $repository = new CollectionRunRepository($db);
        $id = $repository->create(1, 'dp-123');

        $this->assertSame(42, $id);
    }

    public function testUpdateProgressUpdatesCompanyCounts(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                '{{%collection_run}}',
                [
                    'companies_total' => 10,
                    'companies_success' => 8,
                    'companies_failed' => 2,
                ],
                ['id' => 42],
            )
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->updateProgress(42, 10, 8, 2);

        $this->assertTrue(true);
    }

    public function testCompleteUpdatesAllFields(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                '{{%collection_run}}',
                $this->callback(function (array $data): bool {
                    return $data['status'] === 'complete'
                        && isset($data['completed_at'])
                        && $data['gate_passed'] === 1
                        && $data['error_count'] === 0
                        && $data['warning_count'] === 2
                        && $data['file_path'] === '/data/pack.json'
                        && $data['file_size_bytes'] === 1024
                        && $data['duration_seconds'] === 120;
                }),
                ['id' => 42],
            )
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->complete(
            runId: 42,
            status: 'complete',
            gatePassed: true,
            errorCount: 0,
            warningCount: 2,
            filePath: '/data/pack.json',
            fileSizeBytes: 1024,
            durationSeconds: 120,
        );

        $this->assertTrue(true);
    }

    public function testCompleteStoresGatePassedAsFalse(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                '{{%collection_run}}',
                $this->callback(function (array $data): bool {
                    return $data['gate_passed'] === 0;
                }),
                $this->anything(),
            )
            ->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->complete(42, 'failed', false, 3, 0, '/data/pack.json', 0, 60);

        $this->assertTrue(true);
    }

    public function testRecordErrorsInsertsErrorsAndWarnings(): void
    {
        $gateResult = new GateResult(
            passed: false,
            errors: [
                new GateError('E001', 'Missing revenue', 'companies.SHEL.financials'),
                new GateError('E002', 'Invalid value', 'companies.XOM.valuation'),
            ],
            warnings: [
                new GateWarning('W001', 'Optional field missing', 'companies.CVX.operational'),
            ],
        );

        $insertCalls = [];
        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(3))
            ->method('insert')
            ->with(
                '{{%collection_error}}',
                $this->callback(function (array $data) use (&$insertCalls): bool {
                    $insertCalls[] = $data;
                    return true;
                }),
            )
            ->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->recordErrors(42, $gateResult);

        $this->assertCount(3, $insertCalls);

        // First error
        $this->assertSame(42, $insertCalls[0]['collection_run_id']);
        $this->assertSame('error', $insertCalls[0]['severity']);
        $this->assertSame('E001', $insertCalls[0]['error_code']);
        $this->assertSame('Missing revenue', $insertCalls[0]['error_message']);
        $this->assertSame('companies.SHEL.financials', $insertCalls[0]['error_path']);
        $this->assertSame('SHEL', $insertCalls[0]['ticker']);

        // Second error
        $this->assertSame('error', $insertCalls[1]['severity']);
        $this->assertSame('XOM', $insertCalls[1]['ticker']);

        // Warning
        $this->assertSame('warning', $insertCalls[2]['severity']);
        $this->assertSame('W001', $insertCalls[2]['error_code']);
        $this->assertSame('CVX', $insertCalls[2]['ticker']);
    }

    public function testRecordErrorsHandlesEmptyGateResult(): void
    {
        $gateResult = new GateResult(
            passed: true,
            errors: [],
            warnings: [],
        );

        $command = $this->createMock(Command::class);
        $command->expects($this->never())->method('insert');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->recordErrors(42, $gateResult);

        $this->assertTrue(true);
    }

    public function testFindByDatapackIdReturnsRow(): void
    {
        $expectedRow = [
            'id' => 42,
            'industry_id' => 1,
            'datapack_id' => 'dp-123',
            'status' => 'complete',
        ];

        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('bindValue')
            ->with(':id', 'dp-123')
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('queryOne')
            ->willReturn($expectedRow);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('WHERE datapack_id = :id'))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->findByDatapackId('dp-123');

        $this->assertSame($expectedRow, $result);
    }

    public function testFindByDatapackIdReturnsNullWhenNotFound(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->findByDatapackId('nonexistent');

        $this->assertNull($result);
    }

    public function testListByIndustryReturnsRows(): void
    {
        $expectedRows = [
            ['id' => 2, 'industry_id' => 1, 'datapack_id' => 'dp-002'],
            ['id' => 1, 'industry_id' => 1, 'datapack_id' => 'dp-001'],
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expectedRows);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('ORDER BY started_at DESC'))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->listByIndustry(1);

        $this->assertSame($expectedRows, $result);
    }

    public function testListByIndustryRespectsLimit(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command) {
                if ($name === ':limit') {
                    $this->assertSame(5, $value);
                }
                return $command;
            });
        $command->method('queryAll')->willReturn([]);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->listByIndustry(1, 5);

        $this->assertTrue(true);
    }

    public function testGetLatestSuccessfulReturnsRow(): void
    {
        $expectedRow = [
            'id' => 42,
            'industry_id' => 1,
            'status' => 'complete',
            'gate_passed' => 1,
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expectedRow);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'gate_passed = 1')
                    && str_contains($sql, 'ORDER BY completed_at DESC')
                    && str_contains($sql, 'LIMIT 1');
            }))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->getLatestSuccessful(1);

        $this->assertSame($expectedRow, $result);
    }

    public function testGetLatestSuccessfulReturnsNullWhenNotFound(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->getLatestSuccessful(1);

        $this->assertNull($result);
    }

    public function testGetLatestCompletedReturnsRow(): void
    {
        $expectedRow = [
            'id' => 42,
            'industry_id' => 1,
            'status' => 'complete',
            'gate_passed' => 0,
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expectedRow);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'status = :status')
                    && str_contains($sql, 'ORDER BY completed_at DESC')
                    && str_contains($sql, 'LIMIT 1')
                    && !str_contains($sql, 'gate_passed');
            }))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->getLatestCompleted(1);

        $this->assertSame($expectedRow, $result);
    }

    public function testGetLatestCompletedReturnsNullWhenNotFound(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->getLatestCompleted(1);

        $this->assertNull($result);
    }

    public function testExtractTickerExtractsFromCompanyPath(): void
    {
        $db = $this->createMock(Connection::class);
        $repository = new CollectionRunRepository($db);

        $this->assertSame('SHEL', $repository->extractTicker('companies.SHEL.financials'));
        $this->assertSame('XOM', $repository->extractTicker('companies.XOM.valuation.marketCap'));
        $this->assertSame('BRK.B', $repository->extractTicker('companies.BRK.B.financials'));
    }

    public function testExtractTickerReturnsNullForNonCompanyPath(): void
    {
        $db = $this->createMock(Connection::class);
        $repository = new CollectionRunRepository($db);

        $this->assertNull($repository->extractTicker('macro.commodityBenchmark'));
        $this->assertNull($repository->extractTicker('collection_log.duration'));
        $this->assertNull($repository->extractTicker('invalid'));
    }

    public function testExtractTickerReturnsNullForNullPath(): void
    {
        $db = $this->createMock(Connection::class);
        $repository = new CollectionRunRepository($db);

        $this->assertNull($repository->extractTicker(null));
    }

    public function testListRecentReturnsAllRunsWithoutFilters(): void
    {
        $expectedRows = [
            ['id' => 3, 'status' => 'complete'],
            ['id' => 2, 'status' => 'running'],
            ['id' => 1, 'status' => 'failed'],
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expectedRows);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'JOIN {{%industry}}')
                    && str_contains($sql, 'ORDER BY')
                    && str_contains($sql, 'started_at DESC')
                    && !str_contains($sql, 'WHERE');
            }))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->listRecent();

        $this->assertSame($expectedRows, $result);
    }

    public function testListRecentFiltersbyStatus(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryAll')->willReturn([]);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('status = :status'))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->listRecent('running');

        $this->assertSame('running', $boundParams[':status']);
    }

    public function testListRecentFiltersBySearchWithEscaping(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryAll')->willReturn([]);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('i.slug LIKE :search'))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->listRecent(null, 'oil%majors');

        $this->assertSame('%oil\%majors%', $boundParams[':search']);
    }

    public function testListRecentCombinesStatusAndSearch(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryAll')->willReturn([]);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'status = :status')
                    && str_contains($sql, 'i.slug LIKE :search');
            }))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->listRecent('complete', 'oil');

        $this->assertSame('complete', $boundParams[':status']);
        $this->assertSame('%oil%', $boundParams[':search']);
    }

    public function testListRecentRespectsLimitAndOffset(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryAll')->willReturn([]);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->listRecent(null, null, 25, 50);

        $this->assertSame(25, $boundParams[':limit']);
        $this->assertSame(50, $boundParams[':offset']);
    }

    public function testCountRecentReturnsCountWithoutFilters(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryScalar')->willReturn('42');

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'SELECT COUNT(*)')
                    && !str_contains($sql, 'WHERE');
            }))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->countRecent();

        $this->assertSame(42, $result);
    }

    public function testCountRecentFiltersByStatus(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryScalar')->willReturn('5');

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('status = :status'))
            ->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $result = $repository->countRecent('failed');

        $this->assertSame(5, $result);
        $this->assertSame('failed', $boundParams[':status']);
    }

    public function testCountRecentFiltersBySearchWithEscaping(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryScalar')->willReturn('3');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new CollectionRunRepository($db);
        $repository->countRecent(null, 'test_value');

        $this->assertSame('%test\_value%', $boundParams[':search']);
    }
}
