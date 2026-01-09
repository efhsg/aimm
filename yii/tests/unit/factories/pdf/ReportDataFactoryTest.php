<?php

declare(strict_types=1);

namespace tests\unit\factories\pdf;

use app\dto\pdf\ReportData;
use app\factories\pdf\ReportDataFactory;
use app\queries\AnalysisReportReader;
use Codeception\Test\Unit;
use RuntimeException;

/**
 * @covers \app\factories\pdf\ReportDataFactory
 */
final class ReportDataFactoryTest extends Unit
{
    private AnalysisReportReader $repository;
    private ReportDataFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(AnalysisReportReader::class);
        $this->factory = new ReportDataFactory($this->repository);
    }

    public function testCreatesReportDataFromValidReport(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createSampleReportData();

        $this->repository->method('findByReportId')
            ->with('rpt_123')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->with($reportRow)
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_123', 'trace_abc');

        $this->assertInstanceOf(ReportData::class, $result);
        $this->assertSame('rpt_123', $result->reportId);
        $this->assertSame('trace_abc', $result->traceId);
        $this->assertSame('ACME', $result->company->ticker);
        $this->assertSame('Acme Corp', $result->company->name);
        $this->assertSame('Technology', $result->company->industry);
    }

    public function testThrowsExceptionWhenReportNotFound(): void
    {
        $this->repository->method('findByReportId')
            ->with('rpt_missing')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Report not found: rpt_missing');

        $this->factory->create('rpt_missing', 'trace_xyz');
    }

    public function testThrowsExceptionWhenNoCompanyInReport(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = [
            'metadata' => ['report_id' => 'rpt_empty', 'industry_name' => 'Tech'],
            'company_analyses' => [],
            'group_averages' => [],
        ];

        $this->repository->method('findByReportId')
            ->with('rpt_empty')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No company found in report');

        $this->factory->create('rpt_empty', 'trace_xyz');
    }

    public function testSelectsSpecificCompanyByTicker(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createMultiCompanyReportData();

        $this->repository->method('findByReportId')
            ->with('rpt_multi')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_multi', 'trace_abc', 'BETA');

        $this->assertSame('BETA', $result->company->ticker);
        $this->assertSame('Beta Inc', $result->company->name);
    }

    public function testUsesFirstCompanyWhenTickerNotSpecified(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createMultiCompanyReportData();

        $this->repository->method('findByReportId')
            ->with('rpt_multi')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_multi', 'trace_abc');

        $this->assertSame('ACME', $result->company->ticker);
    }

    public function testBuildsFinancialMetrics(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createSampleReportData();

        $this->repository->method('findByReportId')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_123', 'trace_abc');

        $this->assertNotEmpty($result->financials->metrics);

        $labels = array_map(
            fn ($metric): string => $metric->label,
            $result->financials->metrics,
        );

        $this->assertContains('Market Cap', $labels);
        $this->assertContains('Forward P/E', $labels);
    }

    public function testBuildsPeerGroup(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createMultiCompanyReportData();

        $this->repository->method('findByReportId')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_multi', 'trace_abc');

        $this->assertSame('Technology', $result->peerGroup->name);
        $this->assertCount(2, $result->peerGroup->companies);
        $this->assertContains('Acme Corp', $result->peerGroup->companies);
        $this->assertContains('Beta Inc', $result->peerGroup->companies);
    }

    public function testCreatesChartPlaceholders(): void
    {
        $reportRow = ['report_json' => '{}'];
        $decodedReport = $this->createSampleReportData();

        $this->repository->method('findByReportId')
            ->willReturn($reportRow);

        $this->repository->method('decodeReport')
            ->willReturn($decodedReport);

        $result = $this->factory->create('rpt_123', 'trace_abc');

        $this->assertCount(2, $result->charts);
        $this->assertFalse($result->charts[0]->available);
        $this->assertNotNull($result->charts[0]->placeholderMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function createSampleReportData(): array
    {
        return [
            'metadata' => [
                'report_id' => 'rpt_123',
                'industry_name' => 'Technology',
            ],
            'company_analyses' => [
                [
                    'ticker' => 'ACME',
                    'name' => 'Acme Corp',
                    'valuation' => [
                        'market_cap_billions' => 50.0,
                        'fwd_pe' => 15.0,
                        'ev_ebitda' => 10.0,
                    ],
                    'valuation_gap' => [
                        'gaps' => [
                            'fwd_pe' => ['value' => 15.0, 'peer_average' => 18.0, 'gap_percent' => -16.7],
                        ],
                    ],
                    'fundamentals' => ['composite_score' => 75.0],
                ],
            ],
            'group_averages' => [
                'market_cap_billions' => 45.0,
                'fwd_pe' => 18.0,
            ],
            'macro' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createMultiCompanyReportData(): array
    {
        return [
            'metadata' => [
                'report_id' => 'rpt_multi',
                'industry_name' => 'Technology',
            ],
            'company_analyses' => [
                [
                    'ticker' => 'ACME',
                    'name' => 'Acme Corp',
                    'valuation' => ['market_cap_billions' => 50.0],
                    'fundamentals' => ['composite_score' => 75.0],
                ],
                [
                    'ticker' => 'BETA',
                    'name' => 'Beta Inc',
                    'valuation' => ['market_cap_billions' => 30.0],
                    'fundamentals' => ['composite_score' => 65.0],
                ],
            ],
            'group_averages' => [],
            'macro' => [],
        ];
    }
}
