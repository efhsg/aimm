<?php

declare(strict_types=1);

namespace tests\unit\clients;

use app\clients\FmpResponseCache;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\clients\FmpResponseCache
 */
final class FmpResponseCacheTest extends Unit
{
    public function testCachesResponsesWithoutApiKeyInKey(): void
    {
        $cache = new FmpResponseCache();
        $fetchResult = new FetchResult(
            content: '{"ok":true}',
            contentType: 'application/json',
            statusCode: 200,
            url: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=first',
            finalUrl: 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=first',
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
        );

        $cache->set($fetchResult->url, $fetchResult);

        $cached = $cache->get(
            'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=second'
        );

        $this->assertSame($fetchResult, $cached);
    }

    public function testIgnoresNonFmpDomains(): void
    {
        $cache = new FmpResponseCache();
        $fetchResult = new FetchResult(
            content: 'nope',
            contentType: 'text/html',
            statusCode: 200,
            url: 'https://example.com/data?apikey=first',
            finalUrl: 'https://example.com/data?apikey=first',
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
        );

        $cache->set($fetchResult->url, $fetchResult);

        $cached = $cache->get('https://example.com/data?apikey=second');

        $this->assertNull($cached);
    }
}
