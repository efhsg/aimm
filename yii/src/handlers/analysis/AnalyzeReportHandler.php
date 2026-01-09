<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalyzeReportResult;
use app\dto\CompanyData;
use app\dto\IndustryDataPack;
use app\dto\report\CompanyAnalysis;
use app\dto\report\MacroContext;
use app\dto\report\RankedReportDTO;
use app\dto\report\RankedReportMetadata;
use app\dto\report\ValuationSnapshot;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidatorInterface;
use DateTimeImmutable;

/**
 * Orchestrates the complete analysis pipeline for all companies.
 *
 * Pipeline:
 * 1. Validate datapack (gate)
 * 2. Calculate group averages
 * 3. Analyze each company (gaps, fundamentals, risk, rating)
 * 4. Rank companies
 * 5. Assemble report
 */
final class AnalyzeReportHandler implements AnalyzeReportInterface
{
    private const BILLIONS = 1_000_000_000;
    private const MIN_ANNUAL_YEARS = 2;

    public function __construct(
        private readonly AnalysisGateValidatorInterface $gateValidator,
        private readonly PeerAverageTransformer $peerAverageTransformer,
        private readonly CalculateGapsInterface $calculateGaps,
        private readonly AssessFundamentalsInterface $assessFundamentals,
        private readonly AssessRiskInterface $assessRisk,
        private readonly DetermineRatingInterface $determineRating,
        private readonly RankCompaniesInterface $rankCompanies,
    ) {
    }

    public function handle(AnalyzeReportRequest $request): AnalyzeReportResult
    {
        $dataPack = $request->dataPack;
        $thresholds = $request->thresholds;

        // 1. Validate datapack
        $gateResult = $this->gateValidator->validate($dataPack);
        if (!$gateResult->passed) {
            return AnalyzeReportResult::gateFailed($gateResult);
        }

        // 2. Calculate group averages (all companies)
        $groupAverages = $this->peerAverageTransformer->transform($dataPack->companies);

        // 3. Analyze each company
        $companyAnalyses = [];
        foreach ($dataPack->companies as $company) {
            // Skip companies with insufficient data
            if (!$this->hasMinimumData($company)) {
                continue;
            }

            // 3a. Calculate valuation gaps (company vs group average)
            $valuationGap = $this->calculateGaps->handle($company, $groupAverages, $thresholds);

            // 3b. Assess fundamentals
            $fundamentals = $this->assessFundamentals->handle($company, $thresholds->fundamentalsWeights);

            // 3c. Assess risk
            $risk = $this->assessRisk->handle($company, $thresholds->riskThresholds);

            // 3d. Determine rating
            $ratingResult = $this->determineRating->handle(
                $fundamentals,
                $risk,
                $valuationGap,
                $thresholds
            );

            $companyAnalyses[] = new CompanyAnalysis(
                ticker: $company->ticker,
                name: $company->name,
                rating: $ratingResult->rating,
                rulePath: $ratingResult->rulePath,
                valuation: $this->buildValuationSnapshot($company),
                valuationGap: $valuationGap,
                fundamentals: $fundamentals,
                risk: $risk,
                rank: 0, // Placeholder - will be assigned by ranking handler
            );
        }

        if ($companyAnalyses === []) {
            return AnalyzeReportResult::error('No companies with sufficient data to analyze');
        }

        // 4. Rank companies
        $rankedAnalyses = $this->rankCompanies->handle($companyAnalyses);

        // 5. Assemble report
        $report = new RankedReportDTO(
            metadata: $this->buildMetadata($request, $dataPack, count($rankedAnalyses)),
            companyAnalyses: $rankedAnalyses,
            groupAverages: $groupAverages,
            macro: $this->buildMacroContext($dataPack),
        );

        return AnalyzeReportResult::success($report);
    }

    private function hasMinimumData(CompanyData $company): bool
    {
        $annualCount = count($company->financials->annualData);
        $hasMarketCap = $company->valuation->marketCap->getBaseValue() !== null;

        return $annualCount >= self::MIN_ANNUAL_YEARS && $hasMarketCap;
    }

    private function buildMetadata(
        AnalyzeReportRequest $request,
        IndustryDataPack $dataPack,
        int $companyCount
    ): RankedReportMetadata {
        return new RankedReportMetadata(
            reportId: uniqid('rpt_', true),
            industryId: $dataPack->industryId,
            industrySlug: $request->industrySlug,
            industryName: $request->industryName,
            generatedAt: new DateTimeImmutable(),
            dataAsOf: $dataPack->collectedAt,
            companyCount: $companyCount,
        );
    }

    private function buildValuationSnapshot(CompanyData $company): ValuationSnapshot
    {
        $marketCap = $company->valuation->marketCap->getBaseValue();

        return new ValuationSnapshot(
            marketCapBillions: $marketCap !== null ? $marketCap / self::BILLIONS : null,
            fwdPe: $company->valuation->fwdPe?->value,
            trailingPe: $company->valuation->trailingPe?->value,
            evEbitda: $company->valuation->evEbitda?->value,
            fcfYieldPercent: $company->valuation->fcfYield?->value,
            divYieldPercent: $company->valuation->divYield?->value,
            priceToBook: $company->valuation->priceToBook?->value,
        );
    }

    private function buildMacroContext(IndustryDataPack $dataPack): MacroContext
    {
        return new MacroContext(
            commodityBenchmark: $dataPack->macro->commodityBenchmark?->getBaseValue(),
            marginProxy: $dataPack->macro->marginProxy?->getBaseValue(),
            sectorIndex: $dataPack->macro->sectorIndex?->value,
        );
    }
}
