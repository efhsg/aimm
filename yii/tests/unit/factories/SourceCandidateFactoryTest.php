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

        // Yahoo (2 variants), StockAnalysis, Reuters, WSJ, Bloomberg, Morningstar, SeekingAlpha
        $this->assertGreaterThanOrEqual(8, count($candidates));

        // Check that all expected adapters are present
        $adapterIds = array_map(static fn ($c) => $c->adapterId, $candidates);
        $this->assertContains('yahoo_finance', $adapterIds);
        $this->assertContains('yahoo_finance_api', $adapterIds);
        $this->assertContains('stockanalysis', $adapterIds);
        $this->assertContains('reuters', $adapterIds);
        $this->assertContains('wsj', $adapterIds);
        $this->assertContains('bloomberg', $adapterIds);
        $this->assertContains('morningstar', $adapterIds);
        $this->assertContains('seeking_alpha', $adapterIds);

        // Check URLs for key adapters
        $urlsByAdapter = [];
        foreach ($candidates as $candidate) {
            $urlsByAdapter[$candidate->adapterId] = $candidate->url;
        }

        $this->assertSame('https://finance.yahoo.com/quote/AAPL', $urlsByAdapter['yahoo_finance']);
        $this->assertStringContainsString('query1.finance.yahoo.com', $urlsByAdapter['yahoo_finance_api']);
    }

    public function testMacroCandidatesUseYahooSourcesOnly(): void
    {
        $factory = new SourceCandidateFactory();
        $candidates = $factory->forMacro('BRENT');

        $this->assertCount(2, $candidates);
        $this->assertSame('yahoo_finance', $candidates[0]->adapterId);
        $this->assertStringContainsString('BZ%3DF', $candidates[0]->url);

        $this->assertSame('yahoo_finance_api', $candidates[1]->adapterId);
        $this->assertStringContainsString('BZ%3DF', $candidates[1]->url);
    }

    public function testMacroCandidatesResolveSnakeCaseKeys(): void
    {
        $factory = new SourceCandidateFactory();

        // brent_crude should resolve to BZ=F
        $brentCandidates = $factory->forMacro('brent_crude');
        $this->assertCount(2, $brentCandidates);
        $this->assertStringContainsString('BZ%3DF', $brentCandidates[0]->url);

        // wti_crude should resolve to CL=F
        $wtiCandidates = $factory->forMacro('wti_crude');
        $this->assertCount(2, $wtiCandidates);
        $this->assertStringContainsString('CL%3DF', $wtiCandidates[0]->url);

        // natural_gas should resolve to NG=F
        $gasCandidates = $factory->forMacro('natural_gas');
        $this->assertCount(2, $gasCandidates);
        $this->assertStringContainsString('NG%3DF', $gasCandidates[0]->url);
    }

    public function testMacroCandidatesIncludeRigCountAndInventorySources(): void
    {
        $factory = new SourceCandidateFactory(
            rigCountXlsxUrl: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
            eiaApiKey: 'DEMO_KEY',
            eiaInventorySeriesId: 'PET.WCRSTUS1.W',
        );

        $rigCandidates = $factory->forMacro('rig_count');
        $this->assertCount(1, $rigCandidates);
        $this->assertSame('baker_hughes_rig_count', $rigCandidates[0]->adapterId);
        $this->assertSame('rigcount.bakerhughes.com', $rigCandidates[0]->domain);

        $inventoryCandidates = $factory->forMacro('inventory');
        $this->assertCount(1, $inventoryCandidates);
        $this->assertSame('eia_inventory', $inventoryCandidates[0]->adapterId);
        $this->assertSame('api.eia.gov', $inventoryCandidates[0]->domain);
        $this->assertStringContainsString('seriesid/PET.WCRSTUS1.W', $inventoryCandidates[0]->url);
        $this->assertStringContainsString('api_key=DEMO_KEY', $inventoryCandidates[0]->url);
    }
}
