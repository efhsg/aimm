<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\IndustryAnalysisContext;
use app\dto\analysis\RatingDeterminationResult;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\GateError;
use app\dto\GateResult;
use app\dto\MacroData;
use app\dto\QuartersData;
use app\dto\report\CompanyAnalysis;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\Fundamentals;
use app\enums\GapDirection;
use app\enums\Rating;
use app\enums\RatingRulePath;
use app\enums\Risk;
use app\handlers\analysis\AnalyzeReportHandler;
use app\handlers\analysis\AssessFundamentalsInterface;
use app\handlers\analysis\AssessRiskInterface;
use app\handlers\analysis\CalculateGapsInterface;
use app\handlers\analysis\DetermineRatingInterface;
use app\handlers\analysis\RankCompaniesInterface;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidatorInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\handlers\analysis\AnalyzeReportHandler
 */
final class AnalyzeReportHandlerTest extends Unit
{
    private AnalyzeReportHandler $handler;

    // Mocks
    private AnalysisGateValidatorInterface $gateValidator;
    private PeerAverageTransformer $peerAverageTransformer;
    private CalculateGapsInterface $calculateGaps;
    private AssessFundamentalsInterface $assessFundamentals;
    private AssessRiskInterface $assessRisk;
    private DetermineRatingInterface $determineRating;
    private RankCompaniesInterface $rankCompanies;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->gateValidator = $this->createMock(AnalysisGateValidatorInterface::class);
        $this->peerAverageTransformer = new PeerAverageTransformer();
        $this->calculateGaps = $this->createMock(CalculateGapsInterface::class);
        $this->assessFundamentals = $this->createMock(AssessFundamentalsInterface::class);
        $this->assessRisk = $this->createMock(AssessRiskInterface::class);
        $this->determineRating = $this->createMock(DetermineRatingInterface::class);
        $this->rankCompanies = $this->createMock(RankCompaniesInterface::class);

        $this->handler = new AnalyzeReportHandler(
            $this->gateValidator,
            $this->peerAverageTransformer,
            $this->calculateGaps,
            $this->assessFundamentals,
            $this->assessRisk,
            $this->determineRating,
            $this->rankCompanies,
        );
    }

    public function testReturnsFailureWhenGateFails(): void
    {
        $context = $this->createContext();
        $request = new AnalyzeReportRequest($context, 'us-tech-giants', 'US Tech Giants');

        $gateResult = new GateResult(
            passed: false,
            errors: [new GateError('TEST_ERROR', 'Test error')],
            warnings: [],
        );

        $this->gateValidator->method('validate')->willReturn($gateResult);

        $result = $this->handler->handle($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->report);
        $this->assertNotNull($result->gateResult);
        $this->assertEquals('Gate validation failed', $result->errorMessage);
    }

    public function testBuildsCompleteReportOnSuccess(): void
    {
        $context = $this->createContext();
        $request = new AnalyzeReportRequest($context, 'us-tech-giants', 'US Tech Giants');

        $this->setupSuccessMocks();

        $result = $this->handler->handle($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->report);
        $this->assertNull($result->errorMessage);
    }

    public function testReportContainsAllCompanies(): void
    {
        $context = $this->createContext();
        $request = new AnalyzeReportRequest($context, 'us-tech-giants', 'US Tech Giants');

        $this->setupSuccessMocks();

        $result = $this->handler->handle($request);

        $report = $result->report;
        $this->assertNotNull($report);

        // Check metadata
        $this->assertEquals('us-tech-giants', $report->metadata->industryId);
        $this->assertEquals('us-tech-giants', $report->metadata->industrySlug);
        $this->assertEquals('US Tech Giants', $report->metadata->industryName);

        // All companies should be analyzed (3 companies)
        $this->assertCount(3, $report->companyAnalyses);
    }

    public function testReportUsesCustomThresholds(): void
    {
        $context = $this->createContext();
        $thresholds = new AnalysisThresholds(buyGapThreshold: 25.0);
        $request = new AnalyzeReportRequest($context, 'us-tech-giants', 'US Tech Giants', $thresholds);

        $this->setupSuccessMocks();

        $result = $this->handler->handle($request);

        $this->assertTrue($result->success);
    }

    public function testReturnsErrorWhenNoCompaniesHaveSufficientData(): void
    {
        $context = $this->createContextWithInsufficientData();
        $request = new AnalyzeReportRequest($context, 'us-tech-giants', 'US Tech Giants');

        $gateResult = new GateResult(passed: true, errors: [], warnings: []);
        $this->gateValidator->method('validate')->willReturn($gateResult);

        $result = $this->handler->handle($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->report);
        $this->assertStringContainsString('No companies', $result->errorMessage);
    }

    private function setupSuccessMocks(): void
    {
        $gateResult = new GateResult(passed: true, errors: [], warnings: []);
        $this->gateValidator->method('validate')->willReturn($gateResult);

        $valuationGap = new ValuationGapSummary(
            compositeGap: 20.0,
            direction: GapDirection::Undervalued,
            individualGaps: [],
            metricsUsed: 3,
        );
        $this->calculateGaps->method('handle')->willReturn($valuationGap);

        $fundamentals = new FundamentalsBreakdown(
            assessment: Fundamentals::Improving,
            compositeScore: 0.5,
            components: [],
        );
        $this->assessFundamentals->method('handle')->willReturn($fundamentals);

        $risk = new RiskBreakdown(
            assessment: Risk::Acceptable,
            compositeScore: 0.8,
            factors: [],
        );
        $this->assessRisk->method('handle')->willReturn($risk);

        $ratingResult = new RatingDeterminationResult(
            rating: Rating::Buy,
            rulePath: RatingRulePath::BuyAllConditions,
        );
        $this->determineRating->method('handle')->willReturn($ratingResult);

        // Rank companies - return input with ranks assigned
        $this->rankCompanies->method('handle')
            ->willReturnCallback(function (array $analyses): array {
                $ranked = [];
                $rank = 1;
                /** @var CompanyAnalysis $analysis */
                foreach ($analyses as $analysis) {
                    $ranked[] = new CompanyAnalysis(
                        ticker: $analysis->ticker,
                        name: $analysis->name,
                        rating: $analysis->rating,
                        rulePath: $analysis->rulePath,
                        valuation: $analysis->valuation,
                        valuationGap: $analysis->valuationGap,
                        fundamentals: $analysis->fundamentals,
                        risk: $analysis->risk,
                        rank: $rank++,
                    );
                }
                return $ranked;
            });
    }

    private function createContext(): IndustryAnalysisContext
    {
        $companies = [
            'AAPL' => $this->createCompany('AAPL', 'Apple Inc', 3_000_000_000_000),
            'MSFT' => $this->createCompany('MSFT', 'Microsoft Corp', 2_800_000_000_000),
            'GOOGL' => $this->createCompany('GOOGL', 'Alphabet Inc', 1_800_000_000_000),
        ];

        $collectedAt = new DateTimeImmutable();

        return new IndustryAnalysisContext(
            industryId: 1,
            industrySlug: 'us-tech-giants',
            collectedAt: $collectedAt,
            macro: new MacroData(),
            companies: $companies,
        );
    }

    private function createContextWithInsufficientData(): IndustryAnalysisContext
    {
        $companies = [
            'AAPL' => $this->createCompanyWithInsufficientData('AAPL', 'Apple Inc'),
        ];

        $collectedAt = new DateTimeImmutable();

        return new IndustryAnalysisContext(
            industryId: 1,
            industrySlug: 'us-tech-giants',
            collectedAt: $collectedAt,
            macro: new MacroData(),
            companies: $companies,
        );
    }

    private function createCompany(string $ticker, string $name, float $marketCap): CompanyData
    {
        $annualData = [
            new AnnualFinancials(
                fiscalYear: 2024,
                revenue: $this->createMoney(100_000_000_000),
                ebitda: $this->createMoney(30_000_000_000),
                netIncome: $this->createMoney(20_000_000_000),
                freeCashFlow: $this->createMoney(25_000_000_000),
                netDebt: $this->createMoney(50_000_000_000),
            ),
            new AnnualFinancials(
                fiscalYear: 2023,
                revenue: $this->createMoney(90_000_000_000),
                ebitda: $this->createMoney(27_000_000_000),
                netIncome: $this->createMoney(18_000_000_000),
                freeCashFlow: $this->createMoney(22_000_000_000),
                netDebt: $this->createMoney(55_000_000_000),
            ),
        ];

        return new CompanyData(
            ticker: $ticker,
            name: $name,
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoney($marketCap),
                fwdPe: $this->createRatio(25.0),
                evEbitda: $this->createRatio(18.0),
                fcfYield: $this->createPercent(3.5),
                divYield: $this->createPercent(0.5),
            ),
            financials: new FinancialsData(historyYears: 2, annualData: $annualData),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createCompanyWithInsufficientData(string $ticker, string $name): CompanyData
    {
        // Only 1 year of annual data (minimum is 2)
        $annualData = [
            new AnnualFinancials(
                fiscalYear: 2024,
                revenue: $this->createMoney(100_000_000_000),
            ),
        ];

        return new CompanyData(
            ticker: $ticker,
            name: $name,
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoney(3_000_000_000_000),
            ),
            financials: new FinancialsData(historyYears: 1, annualData: $annualData),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createMoney(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.value', (string) $value),
        );
    }

    private function createRatio(float $value): DataPointRatio
    {
        return new DataPointRatio(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.ratio', (string) $value),
        );
    }

    private function createPercent(float $value): DataPointPercent
    {
        return new DataPointPercent(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.percent', (string) $value),
        );
    }
}
