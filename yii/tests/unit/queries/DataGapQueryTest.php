<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\DataGapQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

class DataGapQueryTest extends Unit
{
    public function testFindByCompanyAndTypeReturnsArray()
    {
        $expected = ['id' => 1];

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new DataGapQuery($db);
        $this->assertEquals($expected, $query->findByCompanyAndType(1, 'annual_2023'));
    }

    public function testUpsertExecutesCommand()
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())->method('bindValues')->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new DataGapQuery($db);
        $query->upsert([
            'company_id' => 1,
            'data_type' => 'annual_2023',
            'gap_reason' => 'not_found',
            'first_detected' => '2025-01-01 00:00:00',
            'last_checked' => '2025-01-01 00:00:00'
        ]);
    }

    public function testDeleteExecutesCommand()
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())->method('bindValues')->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new DataGapQuery($db);
        $query->delete(1, 'annual_2023');
    }
}
