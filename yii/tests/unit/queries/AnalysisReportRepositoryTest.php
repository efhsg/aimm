<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\AnalysisReportRepository;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

/**
 * @covers \app\queries\AnalysisReportRepository
 */
final class AnalysisReportRepositoryTest extends Unit
{
    public function testHasRankingsReturnsTrueWhenReportsExist(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryScalar')->willReturn('3');

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('SELECT COUNT(*)'))
            ->willReturn($command);

        $repository = new AnalysisReportRepository($db);
        $result = $repository->hasRankings(1);

        $this->assertTrue($result);
    }

    public function testHasRankingsReturnsFalseWhenNoReportsExist(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryScalar')->willReturn('0');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new AnalysisReportRepository($db);
        $result = $repository->hasRankings(1);

        $this->assertFalse($result);
    }

    public function testHasRankingsQueriesCorrectIndustryId(): void
    {
        $boundParams = [];
        $command = $this->createMock(Command::class);
        $command->method('bindValue')
            ->willReturnCallback(function (string $name, $value) use ($command, &$boundParams) {
                $boundParams[$name] = $value;
                return $command;
            });
        $command->method('queryScalar')->willReturn('0');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new AnalysisReportRepository($db);
        $repository->hasRankings(123);

        $this->assertSame(123, $boundParams[':industry_id']);
    }
}
