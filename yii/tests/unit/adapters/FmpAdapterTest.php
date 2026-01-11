<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\FmpAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\FmpAdapter
 */
final class FmpAdapterTest extends Unit
{
    public function testParsesQuoteEndpointForValuationMetrics(): void
    {
        $content = $this->loadFixture('fmp/quote.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
                'valuation.trailing_pe',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame('fmp', $result->adapterId);
        $this->assertSame([], $result->notFound);
        $this->assertCount(2, $result->extractions);

        $marketCap = $result->extractions['valuation.market_cap'];
        $this->assertSame(420_000_000_000.0, $marketCap->rawValue);
        $this->assertSame('currency', $marketCap->unit);
        $this->assertSame('USD', $marketCap->currency);
        $this->assertSame('units', $marketCap->scale);

        $trailingPe = $result->extractions['valuation.trailing_pe'];
        $this->assertSame(11.86, $trailingPe->rawValue);
        $this->assertSame('ratio', $trailingPe->unit);
    }

    public function testParsesQuoteEndpointForCommodityBenchmark(): void
    {
        $content = $this->loadFixture('fmp/commodity-quote.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/BZUSD?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/BZUSD?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'macro.commodity_benchmark',
            ],
            ticker: 'BZUSD',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->extractions);

        $commodity = $result->extractions['macro.commodity_benchmark'];
        $this->assertSame(78.45, $commodity->rawValue);
        $this->assertSame('currency', $commodity->unit);
        $this->assertSame('USD', $commodity->currency);
    }

    public function testParsesKeyMetricsEndpoint(): void
    {
        $content = $this->loadFixture('fmp/key-metrics.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/key-metrics/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/key-metrics/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.ev_ebitda',
                'valuation.fcf_yield',
                'valuation.div_yield',
                'valuation.net_debt_ebitda',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(4, $result->extractions);

        $evEbitda = $result->extractions['valuation.ev_ebitda'];
        $this->assertSame(5.85, $evEbitda->rawValue);
        $this->assertSame('ratio', $evEbitda->unit);

        $fcfYield = $result->extractions['valuation.fcf_yield'];
        $this->assertSame(8.3, $fcfYield->rawValue); // 0.083 * 100
        $this->assertSame('percent', $fcfYield->unit);

        $divYield = $result->extractions['valuation.div_yield'];
        $this->assertSame(3.45, $divYield->rawValue); // 0.0345 * 100
        $this->assertSame('percent', $divYield->unit);

        $netDebtEbitda = $result->extractions['valuation.net_debt_ebitda'];
        $this->assertSame(0.60, $netDebtEbitda->rawValue);
        $this->assertSame('ratio', $netDebtEbitda->unit);
    }

    public function testParsesRatiosEndpoint(): void
    {
        $content = $this->loadFixture('fmp/ratios.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/ratios/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/ratios/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.fwd_pe',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->extractions);

        $fwdPe = $result->extractions['valuation.fwd_pe'];
        $this->assertSame(11.86, $fwdPe->rawValue);
        $this->assertSame('ratio', $fwdPe->unit);
    }

    public function testParsesIncomeStatementWithHistoricalPeriods(): void
    {
        $content = $this->loadFixture('fmp/income-statement.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.revenue',
                'financials.ebitda',
                'financials.net_income',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertEmpty($result->extractions); // scalar extractions
        $this->assertCount(3, $result->historicalExtractions);

        $revenue = $result->historicalExtractions['financials.revenue'];
        $this->assertCount(2, $revenue->periods);
        $this->assertSame('currency', $revenue->unit);
        $this->assertSame('USD', $revenue->currency);

        // Newest period first (2023)
        $this->assertSame(344_582_000_000.0, $revenue->periods[0]->value);
        $this->assertSame('2023-12-31', $revenue->periods[0]->endDate->format('Y-m-d'));

        // Second period (2022)
        $this->assertSame(413_680_000_000.0, $revenue->periods[1]->value);
        $this->assertSame('2022-12-31', $revenue->periods[1]->endDate->format('Y-m-d'));

        $ebitda = $result->historicalExtractions['financials.ebitda'];
        $this->assertSame(80_000_000_000.0, $ebitda->periods[0]->value);

        $netIncome = $result->historicalExtractions['financials.net_income'];
        $this->assertSame(35_400_000_000.0, $netIncome->periods[0]->value);
    }

    public function testParsesStableIncomeStatementEndpoint(): void
    {
        $content = $this->loadFixture('fmp/income-statement.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.revenue',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertEmpty($result->extractions);
        $this->assertArrayHasKey('financials.revenue', $result->historicalExtractions);
    }

    public function testParsesCashFlowWithHistoricalAndTtm(): void
    {
        $content = $this->loadFixture('fmp/cash-flow.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/cash-flow-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/cash-flow-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.free_cash_flow',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->historicalExtractions);

        $fcf = $result->historicalExtractions['financials.free_cash_flow'];
        $this->assertCount(2, $fcf->periods);
        $this->assertSame('currency', $fcf->unit);

        $this->assertSame(34_850_000_000.0, $fcf->periods[0]->value); // 2023
        $this->assertSame(57_740_000_000.0, $fcf->periods[1]->value); // 2022
    }

    public function testParsesBalanceSheetWithDerivedNetDebt(): void
    {
        $content = $this->loadFixture('fmp/balance-sheet.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/balance-sheet-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/balance-sheet-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.net_debt',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->historicalExtractions);

        $netDebt = $result->historicalExtractions['financials.net_debt'];
        $this->assertCount(1, $netDebt->periods);
        $this->assertSame('currency', $netDebt->unit);
        $this->assertSame('USD', $netDebt->currency);

        // net_debt = totalDebt (49500000000) - cashAndCashEquivalents (32400000000) = 17100000000
        $this->assertSame(17_100_000_000.0, $netDebt->periods[0]->value);
    }

    public function testParsesBalanceSheetDirectFields(): void
    {
        $content = $this->loadFixture('fmp/balance-sheet.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/balance-sheet-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/balance-sheet-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.total_equity',
                'financials.total_debt',
                'financials.cash_and_equivalents',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(3, $result->historicalExtractions);

        $totalEquity = $result->historicalExtractions['financials.total_equity'];
        $this->assertCount(1, $totalEquity->periods);
        $this->assertSame('currency', $totalEquity->unit);
        $this->assertSame(218_000_000_000.0, $totalEquity->periods[0]->value);

        $totalDebt = $result->historicalExtractions['financials.total_debt'];
        $this->assertSame(49_500_000_000.0, $totalDebt->periods[0]->value);

        $cash = $result->historicalExtractions['financials.cash_and_equivalents'];
        $this->assertSame(32_400_000_000.0, $cash->periods[0]->value);
    }

    public function testParsesIncomeStatementNewFields(): void
    {
        $content = $this->loadFixture('fmp/income-statement.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.gross_profit',
                'financials.operating_income',
                'financials.shares_outstanding',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(3, $result->historicalExtractions);

        $grossProfit = $result->historicalExtractions['financials.gross_profit'];
        $this->assertCount(2, $grossProfit->periods);
        $this->assertSame('currency', $grossProfit->unit);
        $this->assertSame(110_226_000_000.0, $grossProfit->periods[0]->value); // 2023

        $operatingIncome = $result->historicalExtractions['financials.operating_income'];
        $this->assertSame(50_000_000_000.0, $operatingIncome->periods[0]->value); // 2023

        $sharesOutstanding = $result->historicalExtractions['financials.shares_outstanding'];
        $this->assertSame('number', $sharesOutstanding->unit);
        $this->assertSame(3_984_000_000.0, $sharesOutstanding->periods[0]->value); // 2023
    }

    public function testReturnsNotFoundForEmptyQuoteResponse(): void
    {
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '[]',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/INVALID?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/INVALID?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
            ],
            ticker: 'INVALID',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertEmpty($result->extractions);
        $this->assertSame('Empty quote response', $result->parseError);
    }

    public function testReturnsParseErrorForInvalidJson(): void
    {
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: 'not valid json',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertSame('Invalid JSON response', $result->parseError);
    }

    public function testReturnsParseErrorForNonJsonContent(): void
    {
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '<html>Error</html>',
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertSame('FMP adapter requires JSON content', $result->parseError);
    }

    public function testReturnsNotFoundForUnsupportedKeys(): void
    {
        $content = $this->loadFixture('fmp/quote.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/quote/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.market_cap',
                'unsupported.key',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['unsupported.key'], $result->notFound);
        $this->assertCount(1, $result->extractions);
        $this->assertArrayHasKey('valuation.market_cap', $result->extractions);
    }

    public function testGetAdapterIdReturnsFmp(): void
    {
        $adapter = new FmpAdapter();
        $this->assertSame('fmp', $adapter->getAdapterId());
    }

    public function testGetSupportedKeysReturnsAllMappedKeys(): void
    {
        $adapter = new FmpAdapter();
        $keys = $adapter->getSupportedKeys();

        $this->assertContains('valuation.market_cap', $keys);
        $this->assertContains('valuation.trailing_pe', $keys);
        $this->assertContains('valuation.ev_ebitda', $keys);
        $this->assertContains('valuation.fcf_yield', $keys);
        $this->assertContains('valuation.fwd_pe', $keys);
        $this->assertContains('financials.revenue', $keys);
        $this->assertContains('financials.gross_profit', $keys);
        $this->assertContains('financials.operating_income', $keys);
        $this->assertContains('financials.ebitda', $keys);
        $this->assertContains('financials.net_income', $keys);
        $this->assertContains('financials.free_cash_flow', $keys);
        $this->assertContains('financials.total_equity', $keys);
        $this->assertContains('financials.total_debt', $keys);
        $this->assertContains('financials.cash_and_equivalents', $keys);
        $this->assertContains('financials.shares_outstanding', $keys);
        $this->assertContains('financials.net_debt', $keys);
        $this->assertContains('macro.commodity_benchmark', $keys);
        // New operational metrics
        $this->assertContains('operational.roe', $keys);
        $this->assertContains('operational.roic', $keys);
        $this->assertContains('operational.roa', $keys);
        $this->assertContains('operational.gross_margin', $keys);
        $this->assertContains('operational.operating_margin', $keys);
        $this->assertContains('operational.net_margin', $keys);
        $this->assertContains('operational.current_ratio', $keys);
        $this->assertContains('operational.debt_to_equity', $keys);
        // New expense metrics
        $this->assertContains('financials.cost_of_revenue', $keys);
        $this->assertContains('financials.sga_expense', $keys);
        $this->assertContains('financials.rd_expense', $keys);
        // TTM metrics from key-metrics-ttm
        $this->assertContains('valuation.revenue_per_share_ttm', $keys);
        $this->assertContains('valuation.ev_ebitda_ttm', $keys);
        $this->assertContains('valuation.pe_ttm', $keys);
        $this->assertContains('valuation.pb_ttm', $keys);
        $this->assertContains('valuation.fcf_yield_ttm', $keys);
        $this->assertContains('valuation.div_yield_ttm', $keys);
        $this->assertContains('operational.roe_ttm', $keys);
        $this->assertContains('operational.roic_ttm', $keys);
        // TTM metrics from ratios-ttm
        $this->assertContains('operational.gross_margin_ttm', $keys);
        $this->assertContains('operational.operating_margin_ttm', $keys);
        $this->assertContains('operational.net_margin_ttm', $keys);
        $this->assertContains('operational.roa_ttm', $keys);
        // Profile metrics
        $this->assertContains('profile.sector', $keys);
        $this->assertContains('profile.industry', $keys);
        $this->assertContains('profile.country', $keys);
        $this->assertContains('profile.exchange', $keys);
        $this->assertContains('profile.employees', $keys);
        // Historical price
        $this->assertContains('benchmark.price', $keys);
        $this->assertContains('benchmark.price_history', $keys);
    }

    public function testParsesOperationalMetricsFromKeyMetrics(): void
    {
        $content = $this->loadFixture('fmp/key-metrics.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/key-metrics/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/key-metrics/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'operational.roe',
                'operational.roic',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(2, $result->extractions);

        $roe = $result->extractions['operational.roe'];
        $this->assertSame(16.2, $roe->rawValue); // 0.162 * 100
        $this->assertSame('percent', $roe->unit);

        $roic = $result->extractions['operational.roic'];
        $this->assertSame(15.5, $roic->rawValue); // 0.155 * 100
        $this->assertSame('percent', $roic->unit);
    }

    public function testParsesMarginMetricsFromRatios(): void
    {
        $content = $this->loadFixture('fmp/ratios.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/ratios/XOM?apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/ratios/XOM?apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'operational.gross_margin',
                'operational.operating_margin',
                'operational.net_margin',
                'operational.current_ratio',
                'operational.debt_to_equity',
                'operational.roa',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(6, $result->extractions);

        $grossMargin = $result->extractions['operational.gross_margin'];
        $this->assertEqualsWithDelta(32.0, $grossMargin->rawValue, 0.001); // 0.32 * 100
        $this->assertSame('percent', $grossMargin->unit);

        $operatingMargin = $result->extractions['operational.operating_margin'];
        $this->assertEqualsWithDelta(14.5, $operatingMargin->rawValue, 0.001); // 0.145 * 100
        $this->assertSame('percent', $operatingMargin->unit);

        $netMargin = $result->extractions['operational.net_margin'];
        $this->assertEqualsWithDelta(10.2, $netMargin->rawValue, 0.001); // 0.102 * 100
        $this->assertSame('percent', $netMargin->unit);

        $currentRatio = $result->extractions['operational.current_ratio'];
        $this->assertSame(1.48, $currentRatio->rawValue);
        $this->assertSame('ratio', $currentRatio->unit);

        $debtToEquity = $result->extractions['operational.debt_to_equity'];
        $this->assertSame(0.23, $debtToEquity->rawValue);
        $this->assertSame('ratio', $debtToEquity->unit);

        $roa = $result->extractions['operational.roa'];
        $this->assertEqualsWithDelta(9.5, $roa->rawValue, 0.001); // 0.095 * 100
        $this->assertSame('percent', $roa->unit);
    }

    public function testParsesExpenseMetricsFromIncomeStatement(): void
    {
        $content = $this->loadFixture('fmp/income-statement.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/api/v3/income-statement/XOM?period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'financials.cost_of_revenue',
                'financials.sga_expense',
                'financials.rd_expense',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(3, $result->historicalExtractions);

        $costOfRevenue = $result->historicalExtractions['financials.cost_of_revenue'];
        $this->assertCount(2, $costOfRevenue->periods);
        $this->assertSame('currency', $costOfRevenue->unit);
        $this->assertSame('USD', $costOfRevenue->currency);
        $this->assertSame(234_356_000_000.0, $costOfRevenue->periods[0]->value); // 2023

        $sgaExpense = $result->historicalExtractions['financials.sga_expense'];
        $this->assertSame(8_500_000_000.0, $sgaExpense->periods[0]->value); // 2023

        $rdExpense = $result->historicalExtractions['financials.rd_expense'];
        $this->assertSame(0.0, $rdExpense->periods[0]->value); // XOM has no R&D expenses
    }

    public function testReturnsRateLimitErrorForApiQuotaExceeded(): void
    {
        $adapter = new FmpAdapter();
        $rateLimitResponse = json_encode([
            'Error Message' => 'Limit Reach . Please upgrade your plan or visit our documentation for more details',
        ]);

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $rateLimitResponse,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: ['financials.revenue'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['financials.revenue'], $result->notFound);
        $this->assertSame('FMP API rate limit reached - daily quota exceeded', $result->parseError);
    }

    public function testReturnsApiErrorForGenericErrors(): void
    {
        $adapter = new FmpAdapter();
        $errorResponse = json_encode([
            'Error Message' => 'Invalid API key. Please provide a valid API key.',
        ]);

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $errorResponse,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/quote?symbol=XOM&apikey=invalid',
                finalUrl: 'https://financialmodelingprep.com/stable/quote?symbol=XOM&apikey=invalid',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: ['valuation.market_cap'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertStringStartsWith('FMP API error:', $result->parseError);
    }

    public function testParsesKeyMetricsTtmEndpoint(): void
    {
        $content = $this->loadFixture('fmp/key-metrics-ttm.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/key-metrics-ttm?symbol=XOM&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/key-metrics-ttm?symbol=XOM&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'valuation.ev_ebitda_ttm',
                'valuation.pe_ttm',
                'operational.roe_ttm',
                'operational.roic_ttm',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(4, $result->extractions);

        $evEbitda = $result->extractions['valuation.ev_ebitda_ttm'];
        $this->assertSame(5.85, $evEbitda->rawValue);
        $this->assertSame('ratio', $evEbitda->unit);

        $pe = $result->extractions['valuation.pe_ttm'];
        $this->assertSame(11.86, $pe->rawValue);
        $this->assertSame('ratio', $pe->unit);

        $roe = $result->extractions['operational.roe_ttm'];
        $this->assertEqualsWithDelta(16.2, $roe->rawValue, 0.001); // 0.162 * 100
        $this->assertSame('percent', $roe->unit);

        $roic = $result->extractions['operational.roic_ttm'];
        $this->assertEqualsWithDelta(15.5, $roic->rawValue, 0.001); // 0.155 * 100
        $this->assertSame('percent', $roic->unit);
    }

    public function testParsesRatiosTtmEndpoint(): void
    {
        $content = $this->loadFixture('fmp/ratios-ttm.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/ratios-ttm?symbol=XOM&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/ratios-ttm?symbol=XOM&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'operational.gross_margin_ttm',
                'operational.operating_margin_ttm',
                'operational.net_margin_ttm',
                'operational.roa_ttm',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(4, $result->extractions);

        $grossMargin = $result->extractions['operational.gross_margin_ttm'];
        $this->assertEqualsWithDelta(32.0, $grossMargin->rawValue, 0.001);
        $this->assertSame('percent', $grossMargin->unit);

        $operatingMargin = $result->extractions['operational.operating_margin_ttm'];
        $this->assertEqualsWithDelta(14.5, $operatingMargin->rawValue, 0.001);
        $this->assertSame('percent', $operatingMargin->unit);

        $netMargin = $result->extractions['operational.net_margin_ttm'];
        $this->assertEqualsWithDelta(10.2, $netMargin->rawValue, 0.001);
        $this->assertSame('percent', $netMargin->unit);

        $roa = $result->extractions['operational.roa_ttm'];
        $this->assertEqualsWithDelta(9.5, $roa->rawValue, 0.001);
        $this->assertSame('percent', $roa->unit);
    }

    public function testParsesProfileEndpoint(): void
    {
        $content = $this->loadFixture('fmp/profile.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/profile?symbol=XOM&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/profile?symbol=XOM&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'profile.sector',
                'profile.industry',
                'profile.country',
                'profile.exchange',
                'profile.employees',
                'profile.ceo',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(6, $result->extractions);

        $sector = $result->extractions['profile.sector'];
        $this->assertSame('Energy', $sector->rawValue);
        $this->assertSame('string', $sector->unit);

        $industry = $result->extractions['profile.industry'];
        $this->assertSame('Oil & Gas Integrated', $industry->rawValue);

        $country = $result->extractions['profile.country'];
        $this->assertSame('US', $country->rawValue);

        $exchange = $result->extractions['profile.exchange'];
        $this->assertSame('NYSE', $exchange->rawValue);

        $employees = $result->extractions['profile.employees'];
        $this->assertSame(62000.0, $employees->rawValue);
        $this->assertSame('number', $employees->unit);

        $ceo = $result->extractions['profile.ceo'];
        $this->assertSame('Darren W. Woods', $ceo->rawValue);
    }

    public function testParsesHistoricalPriceEndpoint(): void
    {
        $content = $this->loadFixture('fmp/historical-price-full.json');
        $adapter = new FmpAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://financialmodelingprep.com/stable/historical-price-full?symbol=XOM&apikey=demo',
                finalUrl: 'https://financialmodelingprep.com/stable/historical-price-full?symbol=XOM&apikey=demo',
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            ),
            datapointKeys: [
                'benchmark.price',
                'benchmark.price_history',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->extractions);
        $this->assertCount(1, $result->historicalExtractions);

        // Scalar: most recent price
        $price = $result->extractions['benchmark.price'];
        $this->assertSame(105.42, $price->rawValue);
        $this->assertSame('currency', $price->unit);
        $this->assertSame('USD', $price->currency);
        $this->assertSame('2024-01-05', $price->asOf->format('Y-m-d'));

        // Historical: time series
        $history = $result->historicalExtractions['benchmark.price_history'];
        $this->assertCount(3, $history->periods);
        $this->assertSame('currency', $history->unit);
        $this->assertSame('USD', $history->currency);

        // Newest first
        $this->assertSame(105.42, $history->periods[0]->value);
        $this->assertSame('2024-01-05', $history->periods[0]->endDate->format('Y-m-d'));

        $this->assertSame(104.50, $history->periods[1]->value);
        $this->assertSame('2024-01-04', $history->periods[1]->endDate->format('Y-m-d'));
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
