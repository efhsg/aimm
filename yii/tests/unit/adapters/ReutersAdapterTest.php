<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\ReutersAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\ReutersAdapter
 */
final class ReutersAdapterTest extends Unit
{
    public function testParsesValuationMetricsFromHtml(): void
    {
        $content = $this->loadFixture('reuters/AAPL-profile.html');

        $adapter = new ReutersAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://www.reuters.com/companies/AAPL.O',
                finalUrl: 'https://www.reuters.com/companies/AAPL.O',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
                'valuation.fwd_pe',
                'valuation.trailing_pe',
                'valuation.ev_ebitda',
                'valuation.div_yield',
                'valuation.net_debt_ebitda',
                'valuation.price_to_book',
            ],
            ticker: 'AAPL',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(7, $result->extractions);

        $marketCap = $result->extractions['valuation.market_cap'];
        $this->assertSame(2.1, $marketCap->rawValue);
        $this->assertSame('currency', $marketCap->unit);
        $this->assertSame('USD', $marketCap->currency);
        $this->assertSame('trillions', $marketCap->scale);

        $trailingPe = $result->extractions['valuation.trailing_pe'];
        $this->assertSame(28.6, $trailingPe->rawValue);
        $this->assertSame('ratio', $trailingPe->unit);

        $divYield = $result->extractions['valuation.div_yield'];
        $this->assertSame(0.55, $divYield->rawValue);
        $this->assertSame('percent', $divYield->unit);
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
