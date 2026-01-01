<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\AnnualFinancialQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

final class AnnualFinancialQueryTest extends Unit
{
    public function testFindCurrentByCompanyAndYearReturnsArray()
    {
        $expected = ['id' => 1, 'fiscal_year' => 2023];

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new AnnualFinancialQuery($db);
        $this->assertEquals($expected, $query->findCurrentByCompanyAndYear(1, 2023));
    }

    public function testFindAllCurrentByCompanyReturnsList()
    {
        $expected = [['id' => 1], ['id' => 2]];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new AnnualFinancialQuery($db);
        $this->assertEquals($expected, $query->findAllCurrentByCompany(1));
    }

    public function testExistsReturnsTrue()
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryScalar')->willReturn('1');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new AnnualFinancialQuery($db);
        $this->assertTrue($query->exists(1, 2023));
    }

    public function testInsertReturnsId()
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('100');

        $query = new AnnualFinancialQuery($db);
        $this->assertEquals(100, $query->insert(['fiscal_year' => 2023]));
    }

    public function testMarkNotCurrentUpdatesRows()
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                'annual_financial',
                ['is_current' => 0],
                ['company_id' => 1, 'fiscal_year' => 2023, 'is_current' => 1]
            )
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new AnnualFinancialQuery($db);
        $query->markNotCurrent(1, 2023);
    }
}
