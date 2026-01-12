<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\CollectionRunRepository;
use app\queries\IndustryAnalysisEligibilityQuery;
use Codeception\Test\Unit;

/**
 * @covers \app\queries\IndustryAnalysisEligibilityQuery
 * @covers \app\dto\industry\AnalysisEligibility
 */
final class IndustryAnalysisEligibilityQueryTest extends Unit
{
    public function testReturnsNotEligibleWhenNoCompletedRunExists(): void
    {
        $repository = $this->createMock(CollectionRunRepository::class);
        $repository->method('getLatestCompleted')->willReturn(null);

        $query = new IndustryAnalysisEligibilityQuery($repository);
        $result = $query->getEligibility(1);

        $this->assertFalse($result->hasCollectedData);
        $this->assertFalse($result->allDossiersGatePassed);
        $this->assertSame('No data collected', $result->disabledReason);
        $this->assertFalse($result->isEligible());
    }

    public function testReturnsEligibleWhenCompletedRunWithGatePassed(): void
    {
        $repository = $this->createMock(CollectionRunRepository::class);
        $repository->method('getLatestCompleted')->willReturn([
            'id' => 42,
            'gate_passed' => 1,
        ]);

        $query = new IndustryAnalysisEligibilityQuery($repository);
        $result = $query->getEligibility(1);

        $this->assertTrue($result->hasCollectedData);
        $this->assertTrue($result->allDossiersGatePassed);
        $this->assertNull($result->disabledReason);
        $this->assertTrue($result->isEligible());
    }

    public function testReturnsNotEligibleWhenCompletedRunWithGateFailed(): void
    {
        $repository = $this->createMock(CollectionRunRepository::class);
        $repository->method('getLatestCompleted')->willReturn([
            'id' => 42,
            'gate_passed' => 0,
        ]);

        $query = new IndustryAnalysisEligibilityQuery($repository);
        $result = $query->getEligibility(1);

        $this->assertTrue($result->hasCollectedData);
        $this->assertFalse($result->allDossiersGatePassed);
        $this->assertSame('Data not complete', $result->disabledReason);
        $this->assertFalse($result->isEligible());
    }

    public function testQueriesCorrectIndustryId(): void
    {
        $repository = $this->createMock(CollectionRunRepository::class);
        $repository->expects($this->once())
            ->method('getLatestCompleted')
            ->with(123)
            ->willReturn(null);

        $query = new IndustryAnalysisEligibilityQuery($repository);
        $query->getEligibility(123);
    }

    public function testHandlesNullGatePassed(): void
    {
        $repository = $this->createMock(CollectionRunRepository::class);
        $repository->method('getLatestCompleted')->willReturn([
            'id' => 42,
            'gate_passed' => null,
        ]);

        $query = new IndustryAnalysisEligibilityQuery($repository);
        $result = $query->getEligibility(1);

        $this->assertTrue($result->hasCollectedData);
        $this->assertFalse($result->allDossiersGatePassed);
        $this->assertSame('Data not complete', $result->disabledReason);
        $this->assertFalse($result->isEligible());
    }
}
