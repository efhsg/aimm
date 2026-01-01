<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\QuarterlyFinancialQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

final class QuarterlyFinancialQueryTest extends Unit
{
    public function testFindLastFourQuartersReturnsList()
    {
        $expected = [['id' => 1], ['id' => 2]];
        $date = new DateTimeImmutable('2025-01-01');

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new QuarterlyFinancialQuery($db);
        $this->assertEquals($expected, $query->findLastFourQuarters(1, $date));
    }

    public function testFindCurrentByCompanyAndQuarterReturnsArray()
    {
        $expected = ['id' => 1];

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new QuarterlyFinancialQuery($db);
        $this->assertEquals($expected, $query->findCurrentByCompanyAndQuarter(1, 2023, 1));
    }

    public function testInsertReturnsId()
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('100');

        $query = new QuarterlyFinancialQuery($db);
        $this->assertEquals(100, $query->insert([]));
    }
}
