<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\report\PeerAverages;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\GapDirection;
use app\handlers\analysis\CalculateGapsHandler;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\handlers\analysis\CalculateGapsHandler
 */
final class CalculateGapsHandlerTest extends Unit
{
    private CalculateGapsHandler $handler;
    private AnalysisThresholds $thresholds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new CalculateGapsHandler();
        $this->thresholds = new AnalysisThresholds();
    }

    public function testCalculatesGapForLowerBetterMetric(): void
    {
        // Focal P/E = 20, Peer avg P/E = 25
        // Gap = (25 - 20) / 25 * 100 = 20%
        $focal = $this->createCompany(fwdPe: 20.0, evEbitda: null, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: null,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $fwdPeGap = $result->individualGaps[0];
        $this->assertEquals('fwd_pe', $fwdPeGap->key);
        $this->assertEquals(20.0, $fwdPeGap->focalValue);
        $this->assertEquals(25.0, $fwdPeGap->peerAverage);
        $this->assertEquals(20.0, $fwdPeGap->gapPercent);
        $this->assertEquals('lower_better', $fwdPeGap->interpretation);
    }

    public function testCalculatesGapForHigherBetterMetric(): void
    {
        // Focal FCF yield = 5%, Peer avg = 4%
        // Gap = (5 - 4) / 4 * 100 = 25%
        $focal = $this->createCompany(fwdPe: null, evEbitda: null, fcfYield: 5.0, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: null,
            evEbitda: null,
            fcfYieldPercent: 4.0,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $fcfGap = $result->individualGaps[2];
        $this->assertEquals('fcf_yield', $fcfGap->key);
        $this->assertEquals(5.0, $fcfGap->focalValue);
        $this->assertEquals(4.0, $fcfGap->peerAverage);
        $this->assertEquals(25.0, $fcfGap->gapPercent);
        $this->assertEquals('higher_better', $fcfGap->interpretation);
    }

    public function testReturnsNullWhenFocalMissing(): void
    {
        $focal = $this->createCompany(fwdPe: null, evEbitda: null, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: 15.0,
            fcfYieldPercent: 4.0,
            divYieldPercent: 2.0,
            marketCapBillions: 100.0,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        foreach ($result->individualGaps as $gap) {
            $this->assertNull($gap->gapPercent);
            $this->assertNull($gap->direction);
        }
        $this->assertNull($result->compositeGap);
        $this->assertEquals(0, $result->metricsUsed);
    }

    public function testReturnsNullWhenPeerAverageZero(): void
    {
        $focal = $this->createCompany(fwdPe: 20.0, evEbitda: null, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 0.0,
            evEbitda: null,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 0,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertNull($result->individualGaps[0]->gapPercent);
        $this->assertNull($result->compositeGap);
    }

    public function testDeterminesUndervaluedDirection(): void
    {
        // Gap > 5% (fairValueThreshold) = Undervalued
        // Focal P/E = 18, Peer avg = 25 => gap = 28%
        $focal = $this->createCompany(fwdPe: 18.0, evEbitda: 12.0, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: 18.0,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertEquals(GapDirection::Undervalued, $result->individualGaps[0]->direction);
        $this->assertEquals(GapDirection::Undervalued, $result->individualGaps[1]->direction);
        $this->assertEquals(GapDirection::Undervalued, $result->direction);
    }

    public function testDeterminesOvervaluedDirection(): void
    {
        // Gap < -5% (fairValueThreshold) = Overvalued
        // Focal P/E = 30, Peer avg = 25 => gap = -20%
        $focal = $this->createCompany(fwdPe: 30.0, evEbitda: 22.0, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: 18.0,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertEquals(GapDirection::Overvalued, $result->individualGaps[0]->direction);
        $this->assertEquals(GapDirection::Overvalued, $result->individualGaps[1]->direction);
        $this->assertEquals(GapDirection::Overvalued, $result->direction);
    }

    public function testDeterminesFairDirection(): void
    {
        // Gap between -5% and 5% = Fair
        // Focal P/E = 24, Peer avg = 25 => gap = 4%
        $focal = $this->createCompany(fwdPe: 24.0, evEbitda: 17.5, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: 18.0,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertEquals(GapDirection::Fair, $result->individualGaps[0]->direction);
        $this->assertEquals(GapDirection::Fair, $result->direction);
    }

    public function testCompositeNullWhenBelowMinMetrics(): void
    {
        // Default minMetricsForGap = 2, only 1 metric available
        $focal = $this->createCompany(fwdPe: 20.0, evEbitda: null, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: null,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertEquals(1, $result->metricsUsed);
        $this->assertNull($result->compositeGap);
        $this->assertNull($result->direction);
    }

    public function testCompositeCalculatedWhenAtMinMetrics(): void
    {
        // 2 metrics available (minimum)
        // fwd_pe gap = 20%, ev_ebitda gap = 33.33%
        // composite = (20 + 33.33) / 2 = 26.67%
        $focal = $this->createCompany(fwdPe: 20.0, evEbitda: 12.0, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: 18.0,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $this->thresholds);

        $this->assertEquals(2, $result->metricsUsed);
        $this->assertNotNull($result->compositeGap);
        // (20 + 33.33) / 2 ≈ 26.67
        $this->assertEqualsWithDelta(26.67, $result->compositeGap, 0.01);
    }

    public function testCustomThresholds(): void
    {
        $thresholds = new AnalysisThresholds(
            fairValueThreshold: 10.0,
            minMetricsForGap: 1,
        );

        // Gap of 8% with threshold 10% should be Fair
        $focal = $this->createCompany(fwdPe: 23.0, evEbitda: null, fcfYield: null, divYield: null);
        $peerAverages = new PeerAverages(
            fwdPe: 25.0,
            evEbitda: null,
            fcfYieldPercent: null,
            divYieldPercent: null,
            marketCapBillions: null,
            companiesIncluded: 2,
        );

        $result = $this->handler->handle($focal, $peerAverages, $thresholds);

        $this->assertEquals(GapDirection::Fair, $result->direction);
        $this->assertEquals(1, $result->metricsUsed);
    }

    private function createCompany(
        ?float $fwdPe,
        ?float $evEbitda,
        ?float $fcfYield,
        ?float $divYield
    ): CompanyData {
        return new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoney(3000000000000),
                fwdPe: $fwdPe !== null ? $this->createRatio($fwdPe) : null,
                evEbitda: $evEbitda !== null ? $this->createRatio($evEbitda) : null,
                fcfYield: $fcfYield !== null ? $this->createPercent($fcfYield) : null,
                divYield: $divYield !== null ? $this->createPercent($divYield) : null,
            ),
            financials: new FinancialsData(historyYears: 0, annualData: []),
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
            sourceLocator: SourceLocator::json('$.marketCap', (string) $value),
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
