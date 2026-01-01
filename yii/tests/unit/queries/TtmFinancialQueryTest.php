<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\TtmFinancialRecord;
use app\queries\TtmFinancialQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

class TtmFinancialQueryTest extends Unit
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

        $query = new TtmFinancialQuery($db);
        $this->assertEquals($expected, $query->findByCompanyAndDate(1, $date));
    }

    public function testUpsertExecutesCommand()
    {
        $record = new TtmFinancialRecord(
            companyId: 1,
            asOfDate: new DateTimeImmutable('2025-01-01'),
            revenue: 100.0,
            grossProfit: 50.0,
            operatingIncome: 20.0,
            ebitda: 30.0,
            netIncome: 10.0,
            operatingCashFlow: 40.0,
            capex: 10.0,
            freeCashFlow: 30.0,
            latestQuarterEnd: new DateTimeImmutable('2024-12-31'),
            previousQuarterEnd: new DateTimeImmutable('2024-09-30'),
            twoQuartersAgoEnd: new DateTimeImmutable('2024-06-30'),
            oldestQuarterEnd: new DateTimeImmutable('2024-03-31'),
            currency: 'USD',
            calculatedAt: new DateTimeImmutable('2025-01-01 12:00:00')
        );

        $command = $this->createMock(Command::class);
        $command->expects($this->once())->method('bindValues')->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('createCommand')->willReturn($command);

        $query = new TtmFinancialQuery($db);
        $query->upsert($record);
    }
}
