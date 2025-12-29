<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\StockAnalysisAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\StockAnalysisAdapter
 */
final class StockAnalysisAdapterTest extends Unit
{
    public function testParsesValuationMetricsFromHtml(): void
    {
        $content = $this->loadFixture('stockanalysis/AAPL-quote.html');

        $adapter = new StockAnalysisAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://stockanalysis.com/stocks/aapl/',
                finalUrl: 'https://stockanalysis.com/stocks/aapl/',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
                'valuation.fwd_pe',
                'valuation.trailing_pe',
                'valuation.ev_ebitda',
                'valuation.div_yield',
                'valuation.fcf_yield',
                'valuation.net_debt_ebitda',
                'valuation.price_to_book',
                'valuation.free_cash_flow_ttm',
            ],
            ticker: 'AAPL',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(9, $result->extractions);

        $marketCap = $result->extractions['valuation.market_cap'];
        $this->assertSame(1.5, $marketCap->rawValue);
        $this->assertSame('currency', $marketCap->unit);
        $this->assertSame('USD', $marketCap->currency);
        $this->assertSame('trillions', $marketCap->scale);

        $fwdPe = $result->extractions['valuation.fwd_pe'];
        $this->assertSame(25.4, $fwdPe->rawValue);
        $this->assertSame('ratio', $fwdPe->unit);

        $divYield = $result->extractions['valuation.div_yield'];
        $this->assertSame(1.23, $divYield->rawValue);
        $this->assertSame('percent', $divYield->unit);

        $freeCashFlow = $result->extractions['valuation.free_cash_flow_ttm'];
        $this->assertSame(5.2, $freeCashFlow->rawValue);
        $this->assertSame('currency', $freeCashFlow->unit);
        $this->assertSame('USD', $freeCashFlow->currency);
        $this->assertSame('billions', $freeCashFlow->scale);
    }

    private function loadFixture(string $path): string
    {
        $fullPath = dirname(__DIR__, 2) . '/fixtures/' . $path;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            $this->fail('Failed to load fixture: ' . $fullPath);
        }

        return $content;
    }
}
