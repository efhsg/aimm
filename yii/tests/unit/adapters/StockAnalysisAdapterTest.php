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

        $this->assertSame([
            'valuation.ev_ebitda',
            'valuation.fcf_yield',
            'valuation.net_debt_ebitda',
            'valuation.price_to_book',
            'valuation.free_cash_flow_ttm',
        ], $result->notFound);
        $this->assertCount(4, $result->extractions);

        $marketCap = $result->extractions['valuation.market_cap'];
        $this->assertSame(4.04, $marketCap->rawValue);
        $this->assertSame('currency', $marketCap->unit);
        $this->assertNull($marketCap->currency);
        $this->assertSame('trillions', $marketCap->scale);

        $fwdPe = $result->extractions['valuation.fwd_pe'];
        $this->assertSame(33.14, $fwdPe->rawValue);
        $this->assertSame('ratio', $fwdPe->unit);

        $trailingPe = $result->extractions['valuation.trailing_pe'];
        $this->assertSame(36.65, $trailingPe->rawValue);
        $this->assertSame('ratio', $trailingPe->unit);

        $divYield = $result->extractions['valuation.div_yield'];
        $this->assertSame(0.38, $divYield->rawValue);
        $this->assertSame('percent', $divYield->unit);
    }

    public function testParsesFcfYieldAndFreeCashFlowWhenLabelsContainTtmSuffix(): void
    {
        $content = <<<HTML
<html>
  <body>
    <table>
      <tr><th>FCF Yield (TTM)</th><td>6.50%</td></tr>
      <tr><th>Free Cash Flow (TTM)</th><td>$12.3B</td></tr>
    </table>
  </body>
</html>
HTML;

        $adapter = new StockAnalysisAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://stockanalysis.com/stocks/test/',
                finalUrl: 'https://stockanalysis.com/stocks/test/',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.fcf_yield',
                'valuation.free_cash_flow_ttm',
            ],
            ticker: 'TEST',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(2, $result->extractions);

        $fcfYield = $result->extractions['valuation.fcf_yield'];
        $this->assertSame(6.5, $fcfYield->rawValue);
        $this->assertSame('percent', $fcfYield->unit);

        $freeCashFlow = $result->extractions['valuation.free_cash_flow_ttm'];
        $this->assertSame(12.3, $freeCashFlow->rawValue);
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
