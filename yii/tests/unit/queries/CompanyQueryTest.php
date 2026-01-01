<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\CompanyQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

final class CompanyQueryTest extends Unit
{
    public function testFindByIdReturnsArrayWhenFound()
    {
        $id = 1;
        $expected = ['id' => 1, 'ticker' => 'TEST'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->with(':id', $id)->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with('SELECT * FROM company WHERE id = :id')
            ->willReturn($command);

        $query = new CompanyQuery($db);
        $result = $query->findById($id);

        $this->assertEquals($expected, $result);
    }

    public function testFindByIdReturnsNullWhenNotFound()
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CompanyQuery($db);
        $this->assertNull($query->findById(999));
    }

    public function testFindByTickerReturnsArrayWhenFound()
    {
        $ticker = 'TEST';
        $expected = ['id' => 1, 'ticker' => 'TEST'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->with(':ticker', $ticker)->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with('SELECT * FROM company WHERE ticker = :ticker')
            ->willReturn($command);

        $query = new CompanyQuery($db);
        $result = $query->findByTicker($ticker);

        $this->assertEquals($expected, $result);
    }

    public function testFindOrCreateReturnsIdWhenExists()
    {
        $ticker = 'EXISTING';
        $existing = ['id' => 123, 'ticker' => 'EXISTING'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->with(':ticker', $ticker)->willReturnSelf();
        $command->method('queryOne')->willReturn($existing);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CompanyQuery($db);
        $this->assertEquals(123, $query->findOrCreate($ticker));
    }

    public function testFindOrCreateInsertsWhenNew()
    {
        $ticker = 'NEW';

        // Mock for findByTicker (returns null)
        $findCommand = $this->createMock(Command::class);
        $findCommand->method('bindValue')->willReturnSelf();
        $findCommand->method('queryOne')->willReturn(false);

        // Mock for insert
        $insertCommand = $this->createMock(Command::class);
        $insertCommand->expects($this->once())
            ->method('insert')
            ->with('company', $this->callback(function ($arr) use ($ticker) {
                return $arr['ticker'] === $ticker && isset($arr['created_at']);
            }))
            ->willReturnSelf();
        $insertCommand->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        // Expect sequences of calls
        $db->expects($this->exactly(2))
            ->method('createCommand')
            ->willReturnOnConsecutiveCalls($findCommand, $insertCommand);

        $db->method('getLastInsertID')->willReturn('456');

        $query = new CompanyQuery($db);
        $this->assertEquals(456, $query->findOrCreate($ticker));
    }

    public function testUpdateStalenessUpdatesField()
    {
        $id = 1;
        $field = 'financials_collected_at';
        $date = new DateTimeImmutable('2025-01-01');

        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                'company',
                [$field => '2025-01-01 00:00:00'],
                ['id' => $id]
            )
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CompanyQuery($db);
        $query->updateStaleness($id, $field, $date);
    }

    public function testUpdateStalenessThrowsOnInvalidField()
    {
        $db = $this->createMock(Connection::class);
        $query = new CompanyQuery($db);

        $this->expectException(\InvalidArgumentException::class);
        $query->updateStaleness(1, 'invalid_field', new DateTimeImmutable());
    }
}
