<?php

declare(strict_types=1);

namespace tests\unit\factories;

use app\factories\SourceCandidateFactory;
use Codeception\Test\Unit;

/**
 * @covers \app\factories\SourceCandidateFactory
 */
final class SourceCandidateFactoryTest extends Unit
{
    public function testBuildsCandidatesForTicker(): void
    {
        $factory = new SourceCandidateFactory();
        $candidates = $factory->forTicker('AAPL', 'NASDAQ');

        $this->assertCount(4, $candidates);
        $this->assertSame('yahoo_finance', $candidates[0]->adapterId);
        $this->assertSame('https://finance.yahoo.com/quote/AAPL', $candidates[0]->url);

        $this->assertSame('yahoo_finance_api', $candidates[1]->adapterId);
        $this->assertSame(
            'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL?modules=financialData,defaultKeyStatistics,price',
            $candidates[1]->url
        );

        $this->assertSame('stockanalysis', $candidates[2]->adapterId);
        $this->assertSame('https://stockanalysis.com/stocks/aapl/', $candidates[2]->url);

        $this->assertSame('reuters', $candidates[3]->adapterId);
        $this->assertSame('https://www.reuters.com/companies/AAPL.O', $candidates[3]->url);
    }

    public function testMacroCandidatesUseYahooSourcesOnly(): void
    {
        $factory = new SourceCandidateFactory();
        $candidates = $factory->forMacro('macro.oil_price');

        $this->assertCount(2, $candidates);
        $this->assertSame('yahoo_finance', $candidates[0]->adapterId);
        $this->assertStringContainsString('CL%3DF', $candidates[0]->url);

        $this->assertSame('yahoo_finance_api', $candidates[1]->adapterId);
        $this->assertStringContainsString('CL%3DF', $candidates[1]->url);
    }
}
