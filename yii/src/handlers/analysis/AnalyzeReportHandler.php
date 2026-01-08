<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalyzeReportResult;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\IndustryDataPack;
use app\dto\QuarterFinancials;
use app\dto\report\AnnualFinancialRow;
use app\dto\report\FinancialsSummary;
use app\dto\report\FocalAnalysis;
use app\dto\report\MacroContext;
use app\dto\report\PeerAverages;
use app\dto\report\PeerComparison;
use app\dto\report\PeerSummary;
use app\dto\report\QuarterlyFinancialRow;
use app\dto\report\ReportDTO;
use app\dto\report\ReportMetadata;
use app\dto\report\ValuationSnapshot;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidatorInterface;
use DateTimeImmutable;

/**
 * Orchestrates the complete analysis pipeline.
 *
 * Pipeline:
 * 1. Validate datapack (gate)
 * 2. Calculate peer averages
 * 3. Calculate valuation gaps
 * 4. Assess fundamentals
 * 5. Assess risk
 * 6. Determine rating
 * 7. Assemble report
 */
final class AnalyzeReportHandler implements AnalyzeReportInterface
{
    private const BILLIONS = 1_000_000_000;

    public function __construct(
        private readonly AnalysisGateValidatorInterface $gateValidator,
        private readonly PeerAverageTransformer $peerAverageTransformer,
        private readonly CalculateGapsInterface $calculateGaps,
        private readonly AssessFundamentalsInterface $assessFundamentals,
        private readonly AssessRiskInterface $assessRisk,
        private readonly DetermineRatingInterface $determineRating,
    ) {
    }

    public function handle(AnalyzeReportRequest $request): AnalyzeReportResult
    {
        $dataPack = $request->dataPack;
        $focalTicker = $request->focalTicker;
        $thresholds = $request->thresholds;

        // 1. Validate datapack
        $gateResult = $this->gateValidator->validate($dataPack, $focalTicker);
        if (!$gateResult->passed) {
            return AnalyzeReportResult::gateFailed($gateResult);
        }

        // Get focal company
        $focal = $dataPack->getCompany($focalTicker);
        if ($focal === null) {
            return AnalyzeReportResult::error("Focal company {$focalTicker} not found");
        }

        // 2. Calculate peer averages
        $peerAverages = $this->peerAverageTransformer->transform(
            $dataPack->companies,
            $focalTicker
        );

        // 3. Calculate valuation gaps
        $valuationGap = $this->calculateGaps->handle($focal, $peerAverages, $thresholds);

        // 4. Assess fundamentals
        $fundamentals = $this->assessFundamentals->handle($focal, $thresholds->fundamentalsWeights);

        // 5. Assess risk
        $risk = $this->assessRisk->handle($focal, $thresholds->riskThresholds);

        // 6. Determine rating
        $ratingResult = $this->determineRating->handle(
            $fundamentals,
            $risk,
            $valuationGap,
            $thresholds
        );

        // 7. Assemble report
        $report = new ReportDTO(
            metadata: $this->buildMetadata($dataPack, $focal),
            focalAnalysis: new FocalAnalysis(
                rating: $ratingResult->rating,
                rulePath: $ratingResult->rulePath,
                valuation: $this->buildValuationSnapshot($focal),
                valuationGap: $valuationGap,
                fundamentals: $fundamentals,
                risk: $risk,
            ),
            financials: $this->buildFinancialsSummary($focal),
            peerComparison: $this->buildPeerComparison($dataPack->companies, $focalTicker, $peerAverages),
            macro: $this->buildMacroContext($dataPack),
        );

        return AnalyzeReportResult::success($report);
    }

    private function buildMetadata(
        IndustryDataPack $dataPack,
        CompanyData $focal
    ): ReportMetadata {
        return new ReportMetadata(
            reportId: uniqid('rpt_', true),
            industryId: $dataPack->industryId,
            focalTicker: $focal->ticker,
            focalName: $focal->name,
            generatedAt: new DateTimeImmutable(),
            dataAsOf: $dataPack->collectedAt,
            peerCount: count($dataPack->companies) - 1,
        );
    }

    private function buildValuationSnapshot(CompanyData $focal): ValuationSnapshot
    {
        $marketCap = $focal->valuation->marketCap->getBaseValue();

        return new ValuationSnapshot(
            marketCapBillions: $marketCap !== null ? $marketCap / self::BILLIONS : null,
            fwdPe: $focal->valuation->fwdPe?->value,
            trailingPe: $focal->valuation->trailingPe?->value,
            evEbitda: $focal->valuation->evEbitda?->value,
            fcfYieldPercent: $focal->valuation->fcfYield?->value,
            divYieldPercent: $focal->valuation->divYield?->value,
            priceToBook: $focal->valuation->priceToBook?->value,
        );
    }

    private function buildFinancialsSummary(CompanyData $focal): FinancialsSummary
    {
        $annualRows = [];
        foreach ($focal->financials->annualData as $annual) {
            $annualRows[] = $this->buildAnnualRow($annual);
        }

        // Sort by fiscal year descending
        usort($annualRows, static fn (AnnualFinancialRow $a, AnnualFinancialRow $b): int =>
            $b->fiscalYear <=> $a->fiscalYear);

        $quarterlyRows = [];
        foreach ($focal->quarters->quarters as $quarter) {
            $quarterlyRows[] = $this->buildQuarterlyRow($quarter);
        }

        // Sort by quarter key descending
        usort($quarterlyRows, static fn (QuarterlyFinancialRow $a, QuarterlyFinancialRow $b): int =>
            $b->quarterKey <=> $a->quarterKey);

        return new FinancialsSummary(
            annualData: $annualRows,
            quarterlyData: $quarterlyRows,
        );
    }

    private function buildAnnualRow(AnnualFinancials $annual): AnnualFinancialRow
    {
        $revenue = $annual->revenue?->getBaseValue();
        $ebitda = $annual->ebitda?->getBaseValue();

        $ebitdaMargin = null;
        if ($revenue !== null && $ebitda !== null && $revenue > 0) {
            $ebitdaMargin = ($ebitda / $revenue) * 100;
        }

        return new AnnualFinancialRow(
            fiscalYear: $annual->fiscalYear,
            revenueBillions: $revenue !== null ? $revenue / self::BILLIONS : null,
            ebitdaBillions: $ebitda !== null ? $ebitda / self::BILLIONS : null,
            netIncomeBillions: $annual->netIncome?->getBaseValue() !== null
                ? $annual->netIncome->getBaseValue() / self::BILLIONS
                : null,
            fcfBillions: $annual->freeCashFlow?->getBaseValue() !== null
                ? $annual->freeCashFlow->getBaseValue() / self::BILLIONS
                : null,
            ebitdaMarginPercent: $ebitdaMargin,
            netDebtBillions: $annual->netDebt?->getBaseValue() !== null
                ? $annual->netDebt->getBaseValue() / self::BILLIONS
                : null,
        );
    }

    private function buildQuarterlyRow(QuarterFinancials $quarter): QuarterlyFinancialRow
    {
        return new QuarterlyFinancialRow(
            quarterKey: $quarter->getQuarterKey(),
            fiscalYear: $quarter->fiscalYear,
            fiscalQuarter: $quarter->fiscalQuarter,
            revenueBillions: $quarter->revenue?->getBaseValue() !== null
                ? $quarter->revenue->getBaseValue() / self::BILLIONS
                : null,
            ebitdaBillions: $quarter->ebitda?->getBaseValue() !== null
                ? $quarter->ebitda->getBaseValue() / self::BILLIONS
                : null,
            netIncomeBillions: $quarter->netIncome?->getBaseValue() !== null
                ? $quarter->netIncome->getBaseValue() / self::BILLIONS
                : null,
            fcfBillions: $quarter->freeCashFlow?->getBaseValue() !== null
                ? $quarter->freeCashFlow->getBaseValue() / self::BILLIONS
                : null,
        );
    }

    /**
     * @param array<string, CompanyData> $companies
     */
    private function buildPeerComparison(
        array $companies,
        string $focalTicker,
        PeerAverages $averages
    ): PeerComparison {
        $peers = [];
        foreach ($companies as $ticker => $company) {
            if ($ticker === $focalTicker) {
                continue;
            }
            $peers[] = $this->buildPeerSummary($company);
        }

        return new PeerComparison(
            peerCount: count($peers),
            averages: $averages,
            peers: $peers,
        );
    }

    private function buildPeerSummary(CompanyData $company): PeerSummary
    {
        $marketCap = $company->valuation->marketCap->getBaseValue();

        return new PeerSummary(
            ticker: $company->ticker,
            name: $company->name,
            marketCapBillions: $marketCap !== null ? $marketCap / self::BILLIONS : null,
            fwdPe: $company->valuation->fwdPe?->value,
            evEbitda: $company->valuation->evEbitda?->value,
            fcfYieldPercent: $company->valuation->fcfYield?->value,
            divYieldPercent: $company->valuation->divYield?->value,
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
