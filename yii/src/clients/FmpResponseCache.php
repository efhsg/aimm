<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\FetchResult;

/**
 * In-memory cache for FMP responses within a single collection run.
 */
final class FmpResponseCache
{
    private const API_KEY_PARAM = 'apikey';
    private const FMP_DOMAIN = 'financialmodelingprep.com';

    /** @var array<string, FetchResult> */
    private array $responses = [];

    public function get(string $url): ?FetchResult
    {
        $cacheKey = $this->buildCacheKey($url);
        if ($cacheKey === null) {
            return null;
        }

        return $this->responses[$cacheKey] ?? null;
    }

    public function set(string $url, FetchResult $fetchResult): void
    {
        $cacheKey = $this->buildCacheKey($url);
        if ($cacheKey === null) {
            return;
        }

        $this->responses[$cacheKey] = $fetchResult;
    }

    private function buildCacheKey(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host !== self::FMP_DOMAIN) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';
        $port = $parts['port'] ?? null;

        $queryParams = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $queryParams);
        }

        unset($queryParams[self::API_KEY_PARAM]);

        $baseUrl = $scheme . '://' . $host;
        if (is_int($port)) {
            $baseUrl .= ':' . $port;
        }
        $baseUrl .= $path;

        if ($queryParams === []) {
            return $baseUrl;
        }

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $baseUrl . '?' . $query;
    }
}
