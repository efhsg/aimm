<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\ValuationSnapshotQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

class ValuationSnapshotQueryTest extends Unit
{
    public function testFindByCompanyAndDateReturnsArray()
    {
        $expected = ['id' => 1];
        $date = new DateTimeImmutable('2025-01-01');

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new ValuationSnapshotQuery($db);
        $this->assertEquals($expected, $query->findByCompanyAndDate(1, $date));
    }

    public function testFindLatestByCompanyReturnsArray()
    {
        $expected = ['id' => 1];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new ValuationSnapshotQuery($db);
        $this->assertEquals($expected, $query->findLatestByCompany(1));
    }

    public function testInsertReturnsId()
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('100');

        $query = new ValuationSnapshotQuery($db);
        $this->assertEquals(100, $query->insert([]));
    }
}
