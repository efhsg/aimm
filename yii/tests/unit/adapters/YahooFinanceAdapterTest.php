<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\YahooFinanceAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\YahooFinanceAdapter
 */
final class YahooFinanceAdapterTest extends Unit
{
    public function testParsesMacroAndNetDebtFromJson(): void
    {
        $content = $this->loadFixture('yahoo-finance/CL-F-quote.json');

        $adapter = new YahooFinanceAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/CL%3DF',
                finalUrl: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/CL%3DF',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'macro.commodity_benchmark',
                'macro.sector_index',
                'valuation.net_debt_ebitda',
            ],
            ticker: 'CL=F',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(3, $result->extractions);

        $commodity = $result->extractions['macro.commodity_benchmark'];
        $this->assertSame(74.5, $commodity->rawValue);
        $this->assertSame('currency', $commodity->unit);
        $this->assertSame('USD', $commodity->currency);
        $this->assertSame('units', $commodity->scale);

        $sectorIndex = $result->extractions['macro.sector_index'];
        $this->assertSame(74.5, $sectorIndex->rawValue);
        $this->assertSame('number', $sectorIndex->unit);
        $this->assertNull($sectorIndex->currency);
        $this->assertNull($sectorIndex->scale);

        $netDebt = $result->extractions['valuation.net_debt_ebitda'];
        $this->assertSame(1.8, $netDebt->rawValue);
        $this->assertSame('ratio', $netDebt->unit);
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
