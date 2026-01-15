<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\AnalysisReportReader;
use app\queries\IndustryPdfEligibilityQuery;
use Codeception\Test\Unit;

/**
 * @covers \app\queries\IndustryPdfEligibilityQuery
 * @covers \app\dto\industry\PdfEligibility
 */
final class IndustryPdfEligibilityQueryTest extends Unit
{
    public function testReturnsNotEligibleWhenNoReportExists(): void
    {
        $reportReader = $this->createMock(AnalysisReportReader::class);
        $reportReader->method('getLatestRanking')->willReturn(null);

        $query = new IndustryPdfEligibilityQuery($reportReader);
        $result = $query->getEligibility(1);

        $this->assertFalse($result->hasReport);
        $this->assertSame('No analysis report exists', $result->disabledReason);
        $this->assertFalse($result->canView());
    }

    public function testReturnsEligibleWhenReportExists(): void
    {
        $reportReader = $this->createMock(AnalysisReportReader::class);
        $reportReader->method('getLatestRanking')->willReturn([
            'id' => 1,
            'report_id' => 'test-report-123',
        ]);

        $query = new IndustryPdfEligibilityQuery($reportReader);
        $result = $query->getEligibility(1);

        $this->assertTrue($result->hasReport);
        $this->assertNull($result->disabledReason);
        $this->assertTrue($result->canView());
    }

    public function testQueriesCorrectIndustryId(): void
    {
        $reportReader = $this->createMock(AnalysisReportReader::class);
        $reportReader->expects($this->once())
            ->method('getLatestRanking')
            ->with(123)
            ->willReturn(null);

        $query = new IndustryPdfEligibilityQuery($reportReader);
        $query->getEligibility(123);
    }
}
