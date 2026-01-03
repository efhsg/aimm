<?php

declare(strict_types=1);

namespace tests\unit\clients;

use app\clients\UrlSanitizer;
use Codeception\Test\Unit;

/**
 * @covers \app\clients\UrlSanitizer
 */
final class UrlSanitizerTest extends Unit
{
    public function testRemovesApiKeyQueryParam(): void
    {
        $url = 'https://financialmodelingprep.com/stable/income-statement?symbol=XOM&period=annual&apikey=SECRET';

        $sanitized = UrlSanitizer::sanitize($url);

        $this->assertStringNotContainsString('apikey=', $sanitized);
        $this->assertStringContainsString('symbol=XOM', $sanitized);
        $this->assertStringContainsString('period=annual', $sanitized);
    }

    public function testRemovesApiKeySnakeCaseParam(): void
    {
        $url = 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=SECRET&foo=bar';

        $sanitized = UrlSanitizer::sanitize($url);

        $this->assertStringNotContainsString('api_key=', $sanitized);
        $this->assertStringContainsString('foo=bar', $sanitized);
    }
}
