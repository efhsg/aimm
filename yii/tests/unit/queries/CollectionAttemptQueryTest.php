<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\CollectionAttemptQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

class CollectionAttemptQueryTest extends Unit
{
    public function testInsertReturnsId()
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('100');

        $query = new CollectionAttemptQuery($db);
        $this->assertEquals(100, $query->insert([]));
    }

    public function testFindRecentByEntityReturnsList()
    {
        $expected = [['id' => 1]];

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionAttemptQuery($db);
        $this->assertEquals($expected, $query->findRecentByEntity('company', 1));
    }
}
