<?php

declare(strict_types=1);

namespace tests\unit\handlers\dossier;

use app\handlers\dossier\TtmCalculator;
use app\queries\QuarterlyFinancialQuery;
use app\queries\TtmFinancialQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class TtmCalculatorTest extends Unit
{
    public function testCalculatesTtmWhenFourQuartersPresent()
    {
        $qQuery = $this->createMock(QuarterlyFinancialQuery::class);
        $tQuery = $this->createMock(TtmFinancialQuery::class);

        $quarters = [
            ['period_end_date' => '2023-12-31', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-09-30', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-06-30', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-03-31', 'revenue' => 100, 'currency' => 'USD'],
        ];

        $qQuery->method('findLastFourQuarters')->willReturn($quarters);
        $tQuery->expects($this->once())->method('upsert');

        $calculator = new TtmCalculator($qQuery, $tQuery);
        $result = $calculator->calculate(1, new DateTimeImmutable('2024-01-01'));

        $this->assertNotNull($result);
        $this->assertEquals(400.0, $result->revenue);
    }

    public function testReturnsNullWhenNotEnoughQuarters()
    {
        $qQuery = $this->createMock(QuarterlyFinancialQuery::class);
        $tQuery = $this->createMock(TtmFinancialQuery::class);

        // 3 quarters
        $quarters = [
            ['period_end_date' => '2023-12-31', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-09-30', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-06-30', 'revenue' => 100, 'currency' => 'USD'],
        ];

        $qQuery->method('findLastFourQuarters')->willReturn($quarters);

        $calculator = new TtmCalculator($qQuery, $tQuery);
        $this->assertNull($calculator->calculate(1, new DateTimeImmutable()));
    }

    public function testReturnsNullWhenNotConsecutive()
    {
        $qQuery = $this->createMock(QuarterlyFinancialQuery::class);
        $tQuery = $this->createMock(TtmFinancialQuery::class);

        $quarters = [
            ['period_end_date' => '2023-12-31', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-09-30', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2023-06-30', 'revenue' => 100, 'currency' => 'USD'],
            ['period_end_date' => '2022-03-31', 'revenue' => 100, 'currency' => 'USD'], // Gap
        ];

        $qQuery->method('findLastFourQuarters')->willReturn($quarters);

        $calculator = new TtmCalculator($qQuery, $tQuery);
        $this->assertNull($calculator->calculate(1, new DateTimeImmutable()));
    }
}
