<?php

declare(strict_types=1);

namespace tests\unit\factories;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\HistoricalExtraction;
use app\dto\PeriodValue;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\factories\DataPointFactory;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\factories\DataPointFactory
 */
final class DataPointFactoryTest extends Unit
{
    public function testFromExtractionBuildsMoneyWithProvenance(): void
    {
        $factory = new DataPointFactory();
        $locator = SourceLocator::html('td[data-test="MARKET_CAP-value"]', '1.23B');

        $extraction = new Extraction(
            datapointKey: 'valuation.market_cap',
            rawValue: 1.23,
            unit: 'currency',
            currency: 'USD',
            scale: 'billions',
            asOf: null,
            locator: $locator,
        );

        $retrievedAt = new DateTimeImmutable('2024-01-02T12:00:00Z');
        $fetchResult = new FetchResult(
            content: '<html></html>',
            contentType: 'text/html',
            statusCode: 200,
            url: 'https://finance.yahoo.com/quote/AAPL',
            finalUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: $retrievedAt,
        );

        $datapoint = $factory->fromExtraction($extraction, $fetchResult);

        $this->assertInstanceOf(DataPointMoney::class, $datapoint);
        $this->assertSame(CollectionMethod::WebFetch, $datapoint->method);
        $this->assertSame('USD', $datapoint->currency);
        $this->assertSame(DataScale::Billions, $datapoint->scale);
        $this->assertSame($retrievedAt, $datapoint->retrievedAt);
        $this->assertSame('https://finance.yahoo.com/quote/AAPL', $datapoint->sourceUrl);
        $this->assertSame('td[data-test="MARKET_CAP-value"]', $datapoint->sourceLocator?->selector);
    }

    public function testFromHistoricalExtractionMostRecentBuildsMoney(): void
    {
        $factory = new DataPointFactory();
        $locator = SourceLocator::json('$.financials.revenue', 'Historical revenue');
        $asOfOld = new DateTimeImmutable('2022-12-31');
        $asOfNew = new DateTimeImmutable('2023-12-31');

        $extraction = new HistoricalExtraction(
            datapointKey: 'financials.revenue',
            periods: [
                new PeriodValue($asOfOld, 100.0),
                new PeriodValue($asOfNew, 120.0),
            ],
            unit: 'currency',
            currency: 'USD',
            scale: 'millions',
            locator: $locator,
        );

        $retrievedAt = new DateTimeImmutable('2024-01-02T12:00:00Z');
        $fetchResult = new FetchResult(
            content: '{}',
            contentType: 'application/json',
            statusCode: 200,
            url: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL',
            finalUrl: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL',
            retrievedAt: $retrievedAt,
        );

        $datapoint = $factory->fromHistoricalExtractionMostRecent($extraction, $fetchResult);

        $this->assertInstanceOf(DataPointMoney::class, $datapoint);
        $this->assertSame(CollectionMethod::WebFetch, $datapoint->method);
        $this->assertSame(120.0, $datapoint->value);
        $this->assertSame('USD', $datapoint->currency);
        $this->assertSame(DataScale::Millions, $datapoint->scale);
        $this->assertSame($asOfNew, $datapoint->asOf);
        $this->assertSame('https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL', $datapoint->sourceUrl);
    }

    public function testNotFoundUsesNullSourceUrlAndRequiresAttemptedSources(): void
    {
        $factory = new DataPointFactory();
        $attempted = ['https://finance.yahoo.com/quote/AAPL'];

        $datapoint = $factory->notFound('currency', $attempted, 'USD');

        $this->assertInstanceOf(DataPointMoney::class, $datapoint);
        $this->assertSame(CollectionMethod::NotFound, $datapoint->method);
        $this->assertNull($datapoint->sourceUrl);
        $this->assertSame($attempted, $datapoint->attemptedSources);
    }

    public function testDerivedPercentSetsDerivedFromAndFormulaAndNullSourceUrl(): void
    {
        $factory = new DataPointFactory();

        $datapoint = $factory->derived(
            unit: 'percent',
            value: 12.5,
            derivedFrom: ['/companies/AAPL/valuation/market_cap'],
            formula: 'value / market_cap',
        );

        $this->assertInstanceOf(DataPointPercent::class, $datapoint);
        $this->assertSame(CollectionMethod::Derived, $datapoint->method);
        $this->assertNull($datapoint->sourceUrl);
        $this->assertSame(['/companies/AAPL/valuation/market_cap'], $datapoint->derivedFrom);
        $this->assertSame('value / market_cap', $datapoint->formula);
    }
}
