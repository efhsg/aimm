<?php

declare(strict_types=1);

namespace app\factories\pdf;

use app\dto\pdf\ChartDto;
use app\dto\pdf\CompanyDto;
use app\dto\pdf\FinancialsDto;
use app\dto\pdf\MetricRowDto;
use app\dto\pdf\PeerGroupDto;
use app\dto\pdf\ReportData;
use app\queries\AnalysisReportReader;
use DateTimeImmutable;
use RuntimeException;

/**
 * Factory to transform analysis report data into PDF-ready DTOs.
 */
class ReportDataFactory
{
    public function __construct(
        private readonly AnalysisReportReader $reportRepository,
    ) {
    }

    /**
     * Create ReportData for a specific company in a report.
     *
     * @param string $reportId The analysis report ID
     * @param string $traceId The trace ID for this PDF generation
     * @param string|null $ticker If null, uses the top-ranked company
     */
    public function create(string $reportId, string $traceId, ?string $ticker = null): ReportData
    {
        $row = $this->reportRepository->findByReportId($reportId);

        if ($row === null) {
            throw new RuntimeException("Report not found: {$reportId}");
        }

        $data = $this->reportRepository->decodeReport($row);

        return $this->buildReportData($data, $traceId, $ticker);
    }

    /**
     * @param array<string, mixed> $data Decoded report JSON
     */
    private function buildReportData(array $data, string $traceId, ?string $ticker): ReportData
    {
        $metadata = $data['metadata'] ?? [];
        $companyAnalyses = $data['company_analyses'] ?? [];
        $groupAverages = $data['group_averages'] ?? [];

        // Select company: specific ticker or first (top-ranked)
        $companyData = $ticker !== null
            ? $this->findCompanyByTicker($companyAnalyses, $ticker)
            : ($companyAnalyses[0] ?? null);

        if ($companyData === null) {
            throw new RuntimeException("No company found in report");
        }

        return new ReportData(
            reportId: $metadata['report_id'] ?? 'unknown',
            traceId: $traceId,
            company: $this->buildCompanyDto($companyData, $metadata),
            financials: $this->buildFinancialsDto($companyData, $groupAverages),
            peerGroup: $this->buildPeerGroupDto($metadata, $companyAnalyses),
            charts: $this->buildChartPlaceholders(),
            generatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $companyAnalyses
     * @return array<string, mixed>|null
     */
    private function findCompanyByTicker(array $companyAnalyses, string $ticker): ?array
    {
        foreach ($companyAnalyses as $analysis) {
            if (($analysis['ticker'] ?? '') === $ticker) {
                return $analysis;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $companyData
     * @param array<string, mixed> $metadata
     */
    private function buildCompanyDto(array $companyData, array $metadata): CompanyDto
    {
        return new CompanyDto(
            id: $companyData['ticker'] ?? 'unknown',
            name: $companyData['name'] ?? 'Unknown Company',
            ticker: $companyData['ticker'] ?? 'N/A',
            industry: $metadata['industry_name'] ?? 'Unknown Industry',
        );
    }

    /**
     * @param array<string, mixed> $companyData
     * @param array<string, mixed> $groupAverages
     */
    private function buildFinancialsDto(array $companyData, array $groupAverages): FinancialsDto
    {
        $valuation = $companyData['valuation'] ?? [];
        $valuationGap = $companyData['valuation_gap'] ?? [];
        $fundamentals = $companyData['fundamentals'] ?? [];

        $metrics = [];

        // Market Cap
        if (isset($valuation['market_cap_billions'])) {
            $metrics[] = new MetricRowDto(
                label: 'Market Cap',
                value: $valuation['market_cap_billions'] * 1_000_000_000,
                change: null,
                peerAverage: isset($groupAverages['market_cap_billions'])
                    ? $groupAverages['market_cap_billions'] * 1_000_000_000
                    : null,
                format: MetricRowDto::FORMAT_CURRENCY,
            );
        }

        // Forward P/E
        $metrics[] = $this->buildValuationMetric(
            'Forward P/E',
            $valuation['fwd_pe'] ?? null,
            $valuationGap['gaps']['fwd_pe'] ?? null,
            $groupAverages['fwd_pe'] ?? null,
        );

        // EV/EBITDA
        $metrics[] = $this->buildValuationMetric(
            'EV/EBITDA',
            $valuation['ev_ebitda'] ?? null,
            $valuationGap['gaps']['ev_ebitda'] ?? null,
            $groupAverages['ev_ebitda'] ?? null,
        );

        // FCF Yield
        if (isset($valuation['fcf_yield_percent']) || isset($groupAverages['fcf_yield_percent'])) {
            $metrics[] = new MetricRowDto(
                label: 'FCF Yield',
                value: isset($valuation['fcf_yield_percent'])
                    ? $valuation['fcf_yield_percent'] / 100
                    : null,
                change: null,
                peerAverage: isset($groupAverages['fcf_yield_percent'])
                    ? $groupAverages['fcf_yield_percent'] / 100
                    : null,
                format: MetricRowDto::FORMAT_PERCENT,
            );
        }

        // Dividend Yield
        if (isset($valuation['div_yield_percent']) || isset($groupAverages['div_yield_percent'])) {
            $metrics[] = new MetricRowDto(
                label: 'Dividend Yield',
                value: isset($valuation['div_yield_percent'])
                    ? $valuation['div_yield_percent'] / 100
                    : null,
                change: null,
                peerAverage: isset($groupAverages['div_yield_percent'])
                    ? $groupAverages['div_yield_percent'] / 100
                    : null,
                format: MetricRowDto::FORMAT_PERCENT,
            );
        }

        // Fundamentals composite score
        if (isset($fundamentals['composite_score'])) {
            $metrics[] = new MetricRowDto(
                label: 'Fundamentals Score',
                value: $fundamentals['composite_score'],
                change: null,
                peerAverage: null,
                format: MetricRowDto::FORMAT_NUMBER,
            );
        }

        return new FinancialsDto($metrics);
    }

    private function buildValuationMetric(
        string $label,
        ?float $value,
        ?array $gap,
        ?float $peerAverage,
    ): MetricRowDto {
        // Gap contains 'value', 'peer_average', 'gap_percent'
        $gapPercent = null;
        if ($gap !== null && isset($gap['gap_percent'])) {
            // gap_percent is already in percentage form, convert to decimal for change
            $gapPercent = $gap['gap_percent'] / 100;
        }

        return new MetricRowDto(
            label: $label,
            value: $value,
            change: $gapPercent,
            peerAverage: $peerAverage,
            format: MetricRowDto::FORMAT_NUMBER,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $companyAnalyses
     */
    private function buildPeerGroupDto(array $metadata, array $companyAnalyses): PeerGroupDto
    {
        $peerNames = array_map(
            static fn (array $analysis): string => $analysis['name'] ?? $analysis['ticker'] ?? 'Unknown',
            $companyAnalyses
        );

        return new PeerGroupDto(
            name: $metadata['industry_name'] ?? 'Industry Peers',
            companies: $peerNames,
        );
    }

    /**
     * Create placeholder charts (actual chart generation is Phase 4).
     *
     * @return ChartDto[]
     */
    private function buildChartPlaceholders(): array
    {
        return [
            ChartDto::placeholder('valuation-comparison', 'bar', 'Valuation comparison chart coming in Phase 4'),
            ChartDto::placeholder('fundamentals-trend', 'line', 'Fundamentals trend chart coming in Phase 4'),
        ];
    }
}
