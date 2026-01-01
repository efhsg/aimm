<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\FxRateQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

class FxRateQueryTest extends Unit
{
    public function testFindClosestRateReturnsFloat()
    {
        $expected = ['rate' => 1.05];
        $date = new DateTimeImmutable('2025-01-01');

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new FxRateQuery($db);
        $this->assertEquals(1.05, $query->findClosestRate('USD', $date));
    }

    public function testFindRatesInRangeReturnsList()
    {
        $expected = [['rate' => 1.0]];
        $currencies = ['USD', 'GBP'];
        $min = new DateTimeImmutable('2025-01-01');
        $max = new DateTimeImmutable('2025-01-31');

        $command = $this->createMock(Command::class);
        $command->method('bindValues')->with($this->callback(function ($params) {
            return isset($params[':c0']) && isset($params[':c1']);
        }))->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->with($this->stringContains('IN (:c0,:c1)'))->willReturn($command);

        $query = new FxRateQuery($db);
        $this->assertEquals($expected, $query->findRatesInRange($currencies, $min, $max));
    }
}
